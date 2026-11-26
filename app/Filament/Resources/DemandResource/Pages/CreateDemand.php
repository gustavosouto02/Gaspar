<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Filament\Resources\DemandResource;
use App\Filament\Resources\DemandResource\Pages\Concerns\AdvancesSubmittedDemand;
use App\Models\CustomEntity;
use App\Models\DemandFieldValue;
use App\Services\FieldPermissionResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateDemand extends CreateRecord
{
    use AdvancesSubmittedDemand;

    protected static string $resource = DemandResource::class;

    private bool $isDraft = false;

    /**
     * Salvar como rascunho ignora a obrigatoriedade dos campos dinâmicos
     * (lido pela closure de required() em buildDynamicFieldSchema)
     */
    public function isSavingDraft(): bool
    {
        return $this->isDraft;
    }

    /**
     * Botão principal: "Enviar para atendimento" — submissão oficial.
     */
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Enviar para atendimento');
    }

    /**
     * Esconde o "Criar e criar outro" padrão do Filament.
     */
    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }

    /**
     * Botões do rodapé: Enviar, Salvar (rascunho) e Cancelar.
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            Action::make('salvar')
                ->label('Salvar')
                ->color('gray')
                ->action(function () {
                    $this->isDraft = true;
                    $this->create();
                }),
            $this->getCancelFormAction(),
        ];
    }

    /** Envio recusado: fica só o aviso de erro, sem o "Criado" padrão */
    protected function getCreatedNotification(): ?Notification
    {
        return $this->submissionFailed ? null : parent::getCreatedNotification();
    }

    protected function getRedirectUrl(): string
    {
        // Depois de enviada, quem abriu pode já não ter o que editar nesta situação
        $page = auth()->user()->can('update', $this->getRecord()) ? 'edit' : 'view';

        return $this->getResource()::getUrl($page, ['record' => $this->getRecord()]);
    }

    /**
     * Antes de criar, extrai field_data e configura o status:
     * - Rascunho (Salvar): status = DRAFT, sem SLA
     * - Envio (Enviar): status = ACTIVE (o SLA é calculado pelo model na criação)
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->fieldData = $data['field_data'] ?? [];
        unset($data['field_data']);

        // Garantia no servidor (vale também para subdemandas): nova demanda só nasce
        // na versão vigente do processo, mesmo que o payload traga outro entity_id
        if (! empty($data['entity_id']) && ! CustomEntity::current()->whereKey($data['entity_id'])->exists()) {
            throw ValidationException::withMessages([
                'data.entity_id' => 'Este processo não está na versão vigente. Selecione a versão vigente do processo.',
            ]);
        }

        // O select de situação é disabled; garante o preenchimento com a situação
        // "Nova" mesmo quando o processo veio pré-selecionado via URL (o default
        // por query string não dispara o afterStateUpdated do select de processo)
        if (empty($data['process_status_id']) && ! empty($data['entity_id'])) {
            $entity = CustomEntity::find($data['entity_id']);
            $data['process_status_id'] = $entity?->initialProcessStatus()?->id;
        }

        // A autoria é sempre de quem está salvando (é ela que libera o envio do rascunho)
        $data['created_by'] = auth()->id();

        $data['status'] = $this->isDraft
            ? DemandStatusEnum::DRAFT->value
            : DemandStatusEnum::ACTIVE->value;

        return $data;
    }

    /**
     * Após criar: salva campos customizados sempre.
     * Ao enviar para atendimento (não no rascunho), avança para a próxima situação.
     */
    protected function afterCreate(): void
    {
        $this->saveFieldValues($this->getRecord()->id, $this->getRecord()->entity_id);

        if (! $this->isDraft) {
            $record = $this->getRecord();
            $advanced = $this->advanceSubmittedDemand($record);

            if ($this->submissionFailed) {
                return;
            }

            $this->notifySubmission(
                $record,
                $advanced,
                'Uma nova demanda foi atribuída a você para atendimento.',
                'Sua demanda foi cadastrada com sucesso e enviada para atendimento.',
            );
        }
    }

    private array $fieldData = [];

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
}
