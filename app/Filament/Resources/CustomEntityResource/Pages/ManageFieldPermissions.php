<?php

namespace App\Filament\Resources\CustomEntityResource\Pages;

use App\Enums\FieldPermissionEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\CustomEntityResource;
use App\Models\FieldPermission;
use App\Models\ProcessRole;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class ManageFieldPermissions extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CustomEntityResource::class;

    protected static string $view = 'filament.resources.custom-entity-resource.pages.manage-field-permissions';

    protected static ?string $title = 'Permissões de Campos';

    /**
     * Papel selecionado no topo (null = "Todos os papéis", a linha genérica)
     */
    public ?string $roleId = null;

    /**
     * matrix[custom_field_id][process_status_id] = permission ('' = padrão/herdar)
     *
     * @var array<string, array<string, string>>
     */
    public array $matrix = [];

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->user_role === UserRoleEnum::ADMIN;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->loadMatrix();
    }

    public function getBreadcrumb(): string
    {
        return 'Permissões de Campos';
    }

    public function getHeading(): string
    {
        return "Permissões de Campos — {$this->record->name}";
    }

    /**
     * Trocar o papel recarrega a grade daquele papel
     */
    public function updatedRoleId($value): void
    {
        $this->roleId = $value === '' ? null : $value;

        $this->loadMatrix();
    }

    /** Campos do processo na ordem de field_order (pivô) */
    public function getFieldsProperty()
    {
        return $this->record->fields()->get();
    }

    /** Situações do processo na ordem de exibição, sem a Condicional (gateway) */
    public function getStatusesProperty()
    {
        return $this->record->processStatuses()->withoutConditional()->get();
    }

    public function getRolesProperty()
    {
        return ProcessRole::orderBy('name')->pluck('name', 'id');
    }

    public function getPermissionOptionsProperty(): array
    {
        return FieldPermissionEnum::options();
    }

    protected function loadMatrix(): void
    {
        $rows = FieldPermission::where('entity_id', $this->record->id)
            ->when(
                $this->roleId,
                fn ($q) => $q->where('process_role_id', $this->roleId),
                fn ($q) => $q->whereNull('process_role_id'),
            )
            ->get();

        $this->matrix = [];

        foreach ($this->fields as $field) {
            foreach ($this->statuses as $status) {
                $row = $rows->first(
                    fn (FieldPermission $r) => $r->custom_field_id === $field->id
                        && $r->process_status_id === $status->id
                );

                $this->matrix[$field->id][$status->id] = $row?->permission?->value ?? '';
            }
        }
    }

    /**
     * Salva a grade do papel selecionado em lote; célula "Padrão" remove a linha
     */
    public function save(): void
    {
        DB::transaction(function () {
            foreach ($this->matrix as $fieldId => $byStatus) {
                foreach ($byStatus as $statusId => $permission) {
                    $keys = [
                        'entity_id'         => $this->record->id,
                        'custom_field_id'   => $fieldId,
                        'process_status_id' => $statusId,
                        'process_role_id'   => $this->roleId,
                    ];

                    if ($permission === '') {
                        FieldPermission::where($keys)->delete();
                    } else {
                        FieldPermission::updateOrCreate($keys, ['permission' => $permission]);
                    }
                }
            }
        });

        Notification::make()
            ->title('Permissões de campos salvas com sucesso!')
            ->success()
            ->send();
    }
}
