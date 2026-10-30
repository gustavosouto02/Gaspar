<?php

namespace Tests\Unit;

use App\Enums\FieldPermissionEnum;
use App\Enums\UserRoleEnum;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\FieldPermission;
use App\Models\ProcessMember;
use App\Models\ProcessRole;
use App\Models\ProcessStatus;
use App\Models\User;
use App\Services\FieldPermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldPermissionResolverTest extends TestCase
{
    use RefreshDatabase;

    private FieldPermissionResolver $resolver;

    private CustomEntity $entity;

    private CustomField $field;

    private ProcessStatus $status;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new FieldPermissionResolver();
        $this->entity = CustomEntity::factory()->create();
        $this->field = CustomField::factory()->create();
        $this->entity->fields()->attach($this->field->id, ['field_order' => 0]);
        $this->status = ProcessStatus::factory()->create();
        $this->user = User::factory()->create(['user_role' => UserRoleEnum::EXECUTOR]);
    }

    private function addUserToRole(ProcessRole $role): void
    {
        ProcessMember::create([
            'entity_id' => $this->entity->id,
            'user_id' => $this->user->id,
            'process_role_id' => $role->id,
        ]);
    }

    private function permission(?string $roleId, FieldPermissionEnum $permission): void
    {
        FieldPermission::create([
            'entity_id' => $this->entity->id,
            'custom_field_id' => $this->field->id,
            'process_status_id' => $this->status->id,
            'process_role_id' => $roleId,
            'permission' => $permission,
        ]);
    }

    private function resolved(): FieldPermissionEnum
    {
        return $this->resolver->resolve($this->entity, $this->status, $this->user)[$this->field->id];
    }

    public function test_role_row_beats_generic_row(): void
    {
        $role = ProcessRole::factory()->create();
        $this->addUserToRole($role);

        $this->permission(null, FieldPermissionEnum::OPTIONAL);
        $this->permission($role->id, FieldPermissionEnum::HIDDEN);

        // A linha do papel vence mesmo sendo menos permissiva que a genérica
        $this->assertSame(FieldPermissionEnum::HIDDEN, $this->resolved());
    }

    public function test_user_with_multiple_roles_gets_most_permissive(): void
    {
        $roleA = ProcessRole::factory()->create();
        $roleB = ProcessRole::factory()->create();
        $this->addUserToRole($roleA);
        $this->addUserToRole($roleB);

        $this->permission($roleA->id, FieldPermissionEnum::HIDDEN);
        $this->permission($roleB->id, FieldPermissionEnum::READONLY);

        $this->assertSame(FieldPermissionEnum::READONLY, $this->resolved());
    }

    public function test_role_row_of_other_role_is_ignored(): void
    {
        $otherRole = ProcessRole::factory()->create();
        $this->permission($otherRole->id, FieldPermissionEnum::HIDDEN);
        $this->permission(null, FieldPermissionEnum::READONLY);

        // Usuário não tem o papel: vale a genérica
        $this->assertSame(FieldPermissionEnum::READONLY, $this->resolved());
    }

    public function test_fallback_defaults_to_readonly(): void
    {
        $this->assertSame(FieldPermissionEnum::READONLY, $this->resolved());

        $this->field->update(['is_required' => true]);

        $this->assertSame(FieldPermissionEnum::READONLY, $this->resolved());
    }

    public function test_final_statuses_default_to_readonly(): void
    {
        foreach (['closed', 'evaluated', 'canceled'] as $systemKey) {
            $this->status = ProcessStatus::findBySystemKey($systemKey);

            $this->assertSame(
                FieldPermissionEnum::READONLY,
                $this->resolved(),
                "Situação {$systemKey} deveria ter padrão somente leitura"
            );
        }
    }

    public function test_configured_row_beats_final_status_default(): void
    {
        $this->status = ProcessStatus::findBySystemKey('closed');
        $this->permission(null, FieldPermissionEnum::OPTIONAL);

        $this->assertSame(FieldPermissionEnum::OPTIONAL, $this->resolved());
    }

    public function test_admin_is_never_below_optional(): void
    {
        $this->user = User::factory()->create(['user_role' => UserRoleEnum::ADMIN]);

        $this->permission(null, FieldPermissionEnum::HIDDEN);

        // ADMIN nunca fica bloqueado pela matriz
        $this->assertSame(FieldPermissionEnum::OPTIONAL, $this->resolved());
    }

    public function test_admin_required_is_elevated_to_optional(): void
    {
        $this->user = User::factory()->create(['user_role' => UserRoleEnum::ADMIN]);

        $this->permission(null, FieldPermissionEnum::REQUIRED);

        $this->assertSame(FieldPermissionEnum::OPTIONAL, $this->resolved());
    }

    public function test_manage_field_permissions_page_defaults_to_readonly_without_padrao_option(): void
    {
        $admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->entity->processStatuses()->attach($this->status->id, ['display_order' => 1]);

        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Resources\CustomEntityResource\Pages\ManageFieldPermissions::class, [
            'record' => $this->entity->id,
        ])
            ->assertSuccessful()
            ->assertDontSeeHtml('<option value="">Padrão</option>')
            ->assertSet("matrix.{$this->field->id}.{$this->status->id}", FieldPermissionEnum::READONLY->value)
            ->call('save');

        $this->assertDatabaseHas('field_permissions', [
            'entity_id' => $this->entity->id,
            'custom_field_id' => $this->field->id,
            'process_status_id' => $this->status->id,
            'process_role_id' => null,
            'permission' => FieldPermissionEnum::READONLY->value,
        ]);
    }
}
