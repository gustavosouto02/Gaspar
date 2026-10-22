<?php

namespace Tests\Unit;

use App\Enums\DemandButtonEnum;
use App\Models\CustomEntity;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\User;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandButtonEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_demand_button_enum_has_salvar_and_enviar_cases(): void
    {
        $cases = DemandButtonEnum::cases();

        $this->assertCount(2, $cases);
        $this->assertSame('Salvar', DemandButtonEnum::SALVAR->value);
        $this->assertSame('Enviar', DemandButtonEnum::ENVIAR->value);
    }

    public function test_demand_button_enum_labels(): void
    {
        $this->assertSame('Salvar', DemandButtonEnum::SALVAR->label());
        $this->assertSame('Enviar', DemandButtonEnum::ENVIAR->label());
    }

    public function test_demand_button_enum_implements_has_label(): void
    {
        $this->assertInstanceOf(HasLabel::class, DemandButtonEnum::SALVAR);
        $this->assertSame('Salvar', DemandButtonEnum::SALVAR->getLabel());
        $this->assertSame('Enviar', DemandButtonEnum::ENVIAR->getLabel());
    }

    public function test_demand_button_enum_options(): void
    {
        $options = DemandButtonEnum::options();

        $this->assertEquals([
            'Salvar' => 'Salvar',
            'Enviar' => 'Enviar',
        ], $options);
    }

    public function test_demand_button_enum_try_from_value(): void
    {
        $this->assertSame(DemandButtonEnum::SALVAR, DemandButtonEnum::tryFromValue('salvar'));
        $this->assertSame(DemandButtonEnum::SALVAR, DemandButtonEnum::tryFromValue('Salvar'));
        $this->assertSame(DemandButtonEnum::SALVAR, DemandButtonEnum::tryFromValue('SALVAR'));

        $this->assertSame(DemandButtonEnum::ENVIAR, DemandButtonEnum::tryFromValue('enviar'));
        $this->assertSame(DemandButtonEnum::ENVIAR, DemandButtonEnum::tryFromValue('Enviar'));
        $this->assertSame(DemandButtonEnum::ENVIAR, DemandButtonEnum::tryFromValue('ENVIAR'));

        $this->assertNull(DemandButtonEnum::tryFromValue('outro'));
        $this->assertNull(DemandButtonEnum::tryFromValue(null));
    }

    public function test_status_transition_casts_label_to_demand_button_enum(): void
    {
        $user = User::factory()->create();
        $entity = CustomEntity::factory()->create(['created_by' => $user->id]);
        $status = ProcessStatus::factory()->create(['created_by' => $user->id]);

        $transition = StatusTransition::create([
            'entity_id' => $entity->id,
            'from_status_id' => null,
            'to_status_id' => $status->id,
            'label' => DemandButtonEnum::SALVAR->value,
            'allowed_role_ids' => ['__requester__'],
            'display_order' => 1,
        ]);

        $transition->refresh();

        $this->assertInstanceOf(DemandButtonEnum::class, $transition->label);
        $this->assertSame(DemandButtonEnum::SALVAR, $transition->label);
        $this->assertSame('Salvar', $transition->label->value);
    }

    public function test_status_transition_accepts_demand_button_enum_instance(): void
    {
        $user = User::factory()->create();
        $entity = CustomEntity::factory()->create(['created_by' => $user->id]);
        $status = ProcessStatus::factory()->create(['created_by' => $user->id]);

        $transition = StatusTransition::create([
            'entity_id' => $entity->id,
            'from_status_id' => null,
            'to_status_id' => $status->id,
            'label' => DemandButtonEnum::ENVIAR,
            'allowed_role_ids' => ['__requester__'],
            'display_order' => 1,
        ]);

        $transition->refresh();

        $this->assertInstanceOf(DemandButtonEnum::class, $transition->label);
        $this->assertSame(DemandButtonEnum::ENVIAR, $transition->label);
        $this->assertSame('Enviar', $transition->label->value);
    }
}
