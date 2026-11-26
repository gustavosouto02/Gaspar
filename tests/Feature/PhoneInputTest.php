<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'user_role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]));
    }

    #[DataProvider('telas')]
    public function test_telefone_eh_obrigatorio_e_exige_ddd(string $tela): void
    {
        Livewire::test($tela)
            ->call('create')
            ->assertHasFormErrors(['phone' => 'required']);

        foreach (['98888-0000', '(00) 98888-0000', '(61) 8888', '+351 912 345 678'] as $invalido) {
            Livewire::test($tela)
                ->set('data.phone', $invalido)
                ->call('create')
                ->assertHasFormErrors(['phone' => 'regex']);
        }

        foreach (['(61) 98888-0000', '(61) 3333-0000'] as $valido) {
            Livewire::test($tela)
                ->set('data.phone', $valido)
                ->call('create')
                ->assertHasNoFormErrors(['phone']);
        }
    }

    public static function telas(): array
    {
        return [
            'cliente' => [CreateClient::class],
            'fornecedor' => [CreateSupplier::class],
            'usuario' => [CreateUser::class],
        ];
    }

    #[DataProvider('telefonesAntigos')]
    public function test_telefone_antigo_com_ddd_abre_formatado(string $gravado, string $esperado): void
    {
        $client = Client::create(['name' => 'Cliente Antigo', 'phone' => $gravado]);

        Livewire::test(EditClient::class, ['record' => $client->id])
            ->assertFormSet(['phone' => $esperado]);
    }

    public static function telefonesAntigos(): array
    {
        return [
            ['61988880000', '(61) 98888-0000'],
            ['61 3333-0000', '(61) 3333-0000'],
            ['+55 (61) 98888-0000', '(61) 98888-0000'],
            // sem DDD não há o que completar
            ['98888-0000', '98888-0000'],
        ];
    }
}
