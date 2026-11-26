<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierRequiredFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_campos_basicos_do_fornecedor_sao_obrigatorios(): void
    {
        $this->actingAs(User::factory()->create([
            'user_role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]));

        Livewire::test(CreateSupplier::class)
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'phone' => 'required',
                'email' => 'required',
                'business' => 'required',
                'description' => 'required',
            ])
            ->assertSee('O campo telefone é obrigatório.');

        $this->assertDatabaseCount('suppliers', 0);
    }
}
