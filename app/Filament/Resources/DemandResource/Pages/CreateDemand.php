<?php

namespace App\Filament\Resources\DemandResource\Pages;

use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Filament\Resources\DemandResource;
use App\Models\CustomEntity;
use App\Models\DemandFieldValue;
use App\Notifications\DemandActivityNotification;
use App\Services\FieldPermissionResolver;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateDemand extends CreateRecord
{
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * Antes de criar, extrai field_data e configura o status:
     * - Rascunho (Salvar): status = DRAFT, sem SLA
     * - Envio (Enviar): status = ACTIVE, calcula SLA
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->fieldData = $data['field_data'] ?? [];
        unset($data['field_data']);

        // O select de situação é disabled; garante o preenchimento com a situação
        // "Nova" mesmo quando o processo veio pré-selecionado via URL (o default
        // por query string não dispara o afterStateUpdated do select de processo)
        if (empty($data['process_status_id']) && ! empty($data['entity_id'])) {
            $entity = CustomEntity::find($data['entity_id']);
            $nova = $entity?->processStatuses()->where('system_key', 'new')->first()
                ?? $entity?->processStatuses()->orderBy('display_order')->first();
            $data['process_status_id'] = $nova?->id;
        }

        if ($this->isDraft) {
            $data['status'] = DemandStatusEnum::DRAFT->value;
        } else {
            $data['status'] = DemandStatusEnum::ACTIVE->value;

            // Calcula SLA somente ao enviar para atendimento
            if (! empty($data['entity_id']) && empty($data['sla_due_at'])) {
                $entity = CustomEntity::find($data['entity_id']);
                if ($entity && $entity->sla_hours) {
                    $data['sla_due_at'] = now()->addHours($entity->sla_hours);
                }
            }
        }

        return $data;
    }

    /**
     * Após criar: salva campos customizados sempre.
     * AutoAssign só roda ao enviar para atendimento (não no rascunho).
     */
    protected function afterCreate(): void
    {
        $this->saveFieldValues($this->getRecord()->id, $this->getRecord()->entity_id);

        if (! $this->isDraft) {
            $record = $this->getRecord();
            $record->autoAssign();
            $record->refresh();

            try {
                if ($record->assignee && $record->assignee->id !== auth()->id()) {
                    $record->assignee->notify(new DemandActivityNotification($record, 'Uma nova demanda foi atribuída a você para atendimento.'));
                }

                if ($record->requester) {
                    $record->requester->notify(new DemandActivityNotification($record, 'Sua demanda foi cadastrada com sucesso e enviada para atendimento.'));
                }
            } catch (\Throwable $e) {
                Log::warning("Falha ao enviar notificação de criação de demanda: {$e->getMessage()}");
            }
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
