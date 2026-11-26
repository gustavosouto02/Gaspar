<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Filament\Resources\DemandResource;
use App\Filament\Resources\DemandResource\Pages\Concerns\AdvancesSubmittedDemand;
use App\Filament\Resources\DemandResource\Pages\Concerns\HasDemandHeaderActions;
use App\Filament\Resources\DemandResource\Widgets\DemandEvaluationWidget;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\DemandComment;
use App\Models\DemandFieldValue;
use App\Services\FieldPermissionResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\Locked;

class EditDemand extends EditRecord
{
    use AdvancesSubmittedDemand;
    use HasDemandHeaderActions;

    protected static string $resource = DemandResource::class;

    public string $new_treatment = '';

    public function addTreatment(): void
    {
        if (empty(trim($this->new_treatment))) {
            return;
        }

        DemandComment::create([
            'demand_id' => $this->record->id,
            'user_id' => auth()->id(),
            'comment' => $this->new_treatment,
        ]);

        $this->new_treatment = '';

        Notification::make()
            ->title('Comentário registrado com sucesso!')
            ->success()
            ->send();
    }

    protected static string $view = 'filament.resources.demand-resource.pages.edit-demand';

    /**
     * Ao carregar o form para edição, injeta os valores salvos em
     * demand_field_values de volta no namespace field_data.{key}. Só campos atuais do
     * processo e visíveis ao usuário: os fora do processo viram texto (histórico).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (DemandResource::fieldDataForFill($this->getRecord(), auth()->user()) as $key => $value) {
            $data['field_data'][$key] = $value;
        }

        return $data;
    }

    private array $fieldData = [];

    private ?string $newTreatment = null;

    // Só o servidor liga (em submitDraft): o navegador não pode forjar o envio chamando save()
    #[Locked]
    public bool $isSubmittingDraft = false;

    /**
     * Salvar alterações de um rascunho ignora a obrigatoriedade dos campos
     * dinâmicos; "Enviar para atendimento" valida normalmente
     * (lido pela closure de required() em buildDynamicFieldSchema)
     */
    public function isSavingDraft(): bool
    {
        return $this->record->status === DemandStatusEnum::DRAFT && ! $this->isSubmittingDraft;
    }

    /**
     * Antes de salvar, extrai field_data e new_treatment.
     * Se estiver enviando um rascunho para atendimento, atualiza status e SLA.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->fieldData = $data['field_data'] ?? [];
        unset($data['field_data']);

        $this->newTreatment = $data['new_treatment'] ?? null;
        unset($data['new_treatment']);

        // Definidos pelo sistema ou pelo fluxo, nunca pelo salvar: campo oculto ou desabilitado
        // não é garantia, então o que vier do navegador é descartado
        unset(
            $data['entity_id'],
            $data['process_status_id'],
            $data['status'],
            $data['priority'],
            $data['requested_by'],
            $data['created_by'],
            $data['parent_demand_id'],
        );

        if ($this->isSubmittingDraft) {
            $data['status'] = DemandStatusEnum::ACTIVE->value;

            // O SLA conta a partir do envio, mesmo que o rascunho tenha um prazo antigo gravado
            $data['sla_due_at'] = Demand::slaDeadlineFor($this->record->entity_id);
        }

        return $data;
    }

    /**
     * Após salvar, atualiza demand_field_values, comentários, autoAssign, etc.
     */
    protected function afterSave(): void
    {
        $this->saveFieldValues(
            $this->getRecord()->id,
            $this->getRecord()->entity_id
        );

        if ($this->newTreatment) {
            DemandComment::create([
                'demand_id' => $this->getRecord()->id,
                'user_id' => auth()->id(),
                'comment' => $this->newTreatment,
            ]);

            $this->form->fill(['new_treatment' => null] + $this->form->getState());
        }

        if ($this->isSubmittingDraft) {
            $record = $this->getRecord();
            $advanced = $this->advanceSubmittedDemand($record);

            if ($this->submissionFailed) {
                return;
            }

            $this->notifySubmission(
                $record,
                $advanced,
                'Uma nova demanda foi enviada e atribuída a você.',
                'Sua demanda foi enviada para atendimento com sucesso.',
            );
        }

        // Se a pesquisa de satisfação foi respondida e a demanda está encerrada, avalia automaticamente
        $record = $this->getRecord()->fresh();
        if (
            $record->satisfaction_rating
            && $record->status === DemandStatusEnum::CLOSED
        ) {
            $record->updateQuietly([
                'status' => DemandStatusEnum::EVALUATED,
            ]);

            Notification::make()
                ->title('Obrigado pela sua avaliação!')
                ->body('A demanda foi marcada como Avaliada.')
                ->success()
                ->send();
        }
    }

    /** Envio recusado: fica só o aviso de erro, sem o "Salvo" padrão */
    protected function getSavedNotification(): ?Notification
    {
        return $this->submissionFailed ? null : parent::getSavedNotification();
    }

    public function submitDraft(): void
    {
        // Método público do Livewire: pode ser chamado direto, sem o botão
        abort_unless(
            $this->record->status === DemandStatusEnum::DRAFT && $this->record->canBeSubmittedBy(auth()->user()),
            403,
            'Apenas quem salvou o rascunho pode enviá-lo.',
        );

        $this->isSubmittingDraft = true;

        // A propriedade sobrevive entre requisições: sem desligar, um envio recusado pela
        // validação faria o "Salvar" seguinte enviar a demanda
        try {
            $this->save();
        } finally {
            $this->isSubmittingDraft = false;
        }
    }

    protected function getFormActions(): array
    {
        $isDraft = $this->record->status === DemandStatusEnum::DRAFT;

        // No rascunho em "Nova", "Enviar para atendimento" já é a transição: sem "Enviar" repetido
        $actions = $this->getWorkflowActions(
            withTransitions: ! ($isDraft && $this->record->processStatus?->isNew())
        );

        // No rascunho, o botão principal "Enviar para atendimento" é só de quem o salvou
        if ($isDraft && $this->record->canBeSubmittedBy(auth()->user())) {
            $actions[] = Action::make('enviar_para_atendimento')
                ->label('Enviar para atendimento')
                ->color('primary')
                ->action(function () {
                    $this->submitDraft();

                    if ($this->submissionFailed) {
                        return redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                    }

                    Notification::make()
                        ->title('Demanda enviada para atendimento!')
                        ->success()
                        ->send();

                    // Redireciona para atualizar a tela e botões; quem enviou pode já não poder editar
                    $page = auth()->user()->can('update', $this->record) ? 'edit' : 'view';

                    return redirect($this->getResource()::getUrl($page, ['record' => $this->record]));
                });
        }

        // Botão padrão de salvar alterações
        $actions[] = $this->getSaveFormAction()->label('Salvar');

        $actions[] = $this->getCancelFormAction();

        return [...$actions, ...$this->getReturnActions()];
    }

    private function saveFieldValues(string $demandId, string $entityId): void
    {
        if (empty($this->fieldData)) {
            return;
        }

        $entity = CustomEntity::find($entityId);
        if (! $entity) {
            return;
        }
        $fields = $entity->fields()->get()->keyBy('key');

        $blockedFieldIds = $this->blockedFieldIds($entity);

        foreach ($this->fieldData as $key => $value) {
            $field = $fields->get($key);
            if (! $field) {
                continue;
            }

            // Garantia no servidor: campos somente leitura/ocultos para o
            // usuário são descartados mesmo que venham no payload
            if (in_array($field->id, $blockedFieldIds, true)) {
                continue;
            }

            DemandFieldValue::updateOrCreate(
                ['demand_id' => $demandId, 'custom_field_id' => $field->id],
                ['value' => is_array($value) ? json_encode($value) : (string) $value]
            );
        }

        // Valores de campos que saíram do processo (desvinculados ou excluídos) são
        // mantidos: a demanda guarda o que foi preenchido na época.
    }

    /**
     * Ids dos campos READONLY/HIDDEN para o usuário na situação atual da demanda
     *
     * @return array<string>
     */
    private function blockedFieldIds(CustomEntity $entity): array
    {
        $status = $this->getRecord()->processStatus;
        $user = auth()->user();

        if (! $status || ! $user) {
            return [];
        }

        $permissions = app(FieldPermissionResolver::class)->resolve($entity, $status, $user);

        return array_keys(array_filter(
            $permissions,
            fn (FieldPermissionEnum $permission) => in_array($permission, [
                FieldPermissionEnum::READONLY,
                FieldPermissionEnum::HIDDEN,
            ], true)
        ));
    }

    protected function getFooterWidgets(): array
    {
        return [
            DemandEvaluationWidget::class,
        ];
    }
}
