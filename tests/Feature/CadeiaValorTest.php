<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Enums\ValueChainFunctionEnum;
use App\Filament\Pages\ControlPanel;
use App\Filament\Resources\MacroprocessResource;
use App\Filament\Resources\MacroprocessResource\Pages\CreateMacroprocess;
use App\Filament\Widgets\ValueChainWidget;
use App\Models\Macroprocess;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T3 — Visualização da Cadeia de Valor no Painel de Controle.
 */
class CadeiaValorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->actingAs($this->admin);
    }

    private function macro(string $nome, string $sigla, ?string $funcao, bool $ativo = true): Macroprocess
    {
        return Macroprocess::create([
            'name' => $nome, 'acronym' => $sigla, 'description' => 'Descrição',
            'is_active' => $ativo, 'value_chain_function' => $funcao,
            'created_by' => $this->admin->id, 'manager_id' => $this->admin->id,
        ]);
    }

    public function test_agrupa_macroprocessos_ativos_por_funcao_e_linka_para_a_tela(): void
    {
        $gestao = $this->macro('Gestão da Estratégia', 'GESTRA', ValueChainFunctionEnum::DEFINIR_DIRETRIZES->value);
        $compras = $this->macro('Compras', 'COMP', ValueChainFunctionEnum::SUPORTAR->value);
        $inativo = $this->macro('Antigo', 'ANT', ValueChainFunctionEnum::SUPORTAR->value, false);

        $groups = Livewire::test(ValueChainWidget::class)->instance()->getGroups();

        $this->assertSame(['GESTRA - Gestão da Estratégia'], $groups[ValueChainFunctionEnum::DEFINIR_DIRETRIZES->value]->pluck('label')->all());
        $this->assertSame(['COMP - Compras'], $groups[ValueChainFunctionEnum::SUPORTAR->value]->pluck('label')->all());
        $this->assertTrue($groups[ValueChainFunctionEnum::ENTREGAR->value]->isEmpty());
        $this->assertTrue($groups['unclassified']->isEmpty());

        Livewire::test(ValueChainWidget::class)
            ->assertSee('Cadeia de Valor')
            ->assertSee(MacroprocessResource::getUrl('view', ['record' => $gestao]), false)
            ->assertSee(MacroprocessResource::getUrl('view', ['record' => $compras]), false)
            ->assertDontSee($inativo->name)
            ->assertDontSee('Não classificado');
    }

    public function test_sem_classificacao_ou_valor_legado_aparece_em_nao_classificado(): void
    {
        $this->macro('Sem Função', 'SEM', null);
        $this->macro('Legado', 'LEG', 'Finalístico');

        $groups = Livewire::test(ValueChainWidget::class)->instance()->getGroups();

        $this->assertEqualsCanonicalizing(['SEM - Sem Função', 'LEG - Legado'], $groups['unclassified']->pluck('label')->all());

        Livewire::test(ValueChainWidget::class)->assertSee('Não classificado');
    }

    public function test_widget_aparece_no_painel_de_controle_depois_dos_graficos(): void
    {
        $widgets = array_values(Livewire::test(ControlPanel::class)->instance()->getVisibleWidgets());

        $this->assertSame(ValueChainWidget::class, end($widgets));
        $this->get('/admin/painel-de-controle')->assertOk()->assertSeeLivewire(ValueChainWidget::class);
    }

    public function test_formulario_so_aceita_as_funcoes_do_enum(): void
    {
        Livewire::test(CreateMacroprocess::class)
            ->assertFormFieldExists('value_chain_function', function (Select $field): bool {
                return array_keys($field->getOptions()) === array_column(ValueChainFunctionEnum::cases(), 'value');
            });
    }
}
