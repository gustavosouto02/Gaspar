<?php

namespace Tests\Feature;

use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Exceptions\DemandTransitionException;
use App\Filament\Resources\DemandResource\Pages\CreateDemand;
use App\Filament\Resources\DemandResource\Pages\EditDemand;
use App\Filament\Resources\DemandResource\Pages\ViewDemand;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\CustomField;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\User;
use App\Services\DemandTransitionService;
use App\Services\FieldPermissionResolver;
use Database\Seeders\AquiProcessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Matriz de permissões de campos do processo "AQUI - Contratação de serviços
 * avulsos", exercitada situação a situação pelas páginas reais do Filament.
 */
class ProcessoAquiFieldPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const H = FieldPermissionEnum::HIDDEN;

    private const R = FieldPermissionEnum::REQUIRED;

    private const O = FieldPermissionEnum::OPTIONAL;

    private const L = FieldPermissionEnum::READONLY;

    private CustomEntity $processo;

    /** @var array<string, User> */
    private array $u = [];

    /** @var array<string, CustomField> */
    private array $f = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->seed(AquiProcessSeeder::class);

        $this->processo = CustomEntity::where('name', 'AQUI - Contratação de serviços avulsos')->firstOrFail();
        $this->u = [
            'admin' => User::where('email', 'admin@gaspar.com')->firstOrFail(),
            'demandante' => User::where('email', 'adinilson.martins@inct.org.br')->firstOrFail(),
            'licitador' => User::where('email', 'adinilson.martins@gmail.com')->firstOrFail(),
            'aprovador' => User::where('email', 'nil@estudandomais.com.br')->firstOrFail(),
            'aprovadorAlto' => User::where('email', 'adinilson.martins@inct.org')->firstOrFail(),
            'adquirente' => User::where('email', 'nil@pierconsultoria.com.br')->firstOrFail(),
            'quitador' => User::where('email', 'plataforma@inct.org.br')->firstOrFail(),
        ];
        $this->f = $this->processo->fields()->get()->keyBy('key')->all();
    }

    private function situacao(string $nameOrKey): ProcessStatus
    {
        return ProcessStatus::where('system_key', $nameOrKey)->first()
            ?? ProcessStatus::where('name', $nameOrKey)->firstOrFail();
    }

    private function demandaEm(string $status, array $valores = [], ?User $responsavel = null): Demand
    {
        $demand = Demand::factory()->create([
            'entity_id' => $this->processo->id,
            'client_id' => Client::first()->id,
            'process_status_id' => $this->situacao($status)->id,
            'requested_by' => $this->u['demandante']->id,
            'created_by' => $this->u['demandante']->id,
            'assigned_to' => $responsavel?->id,
            'status' => DemandStatusEnum::ACTIVE,
            'parent_demand_id' => null,
        ]);

        foreach ($valores as $key => $value) {
            DemandFieldValue::create([
                'demand_id' => $demand->id,
                'custom_field_id' => $this->f[$key]->id,
                'value' => $value,
            ]);
        }

        return $demand;
    }

    private function valor(Demand $demand, string $key): ?string
    {
        return DemandFieldValue::where('demand_id', $demand->id)
            ->where('custom_field_id', $this->f[$key]->id)
            ->value('value');
    }

    private function acaoTransicao(Demand $demand): string
    {
        $t = StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $demand->process_status_id)
            ->firstOrFail();

        return 'transition_'.Str::slug($t->id);
    }

    /**
     * Matriz esperada (seeder, pág. 5 do E2E): [preco, fornecedor, fone, aprovacao]
     */
    public static function matrizEsperada(): array
    {
        return [
            'Nova' => ['new', [self::H, self::H, self::H, self::H]],
            'Licitando' => ['Licitando', [self::R, self::O, self::O, self::H]],
            'Aprovando' => ['Aprovando', [self::L, self::L, self::L, self::R]],
            'Aprovando alto' => ['Aprovando alto', [self::L, self::L, self::L, self::R]],
            'Adquirindo' => ['Adquirindo', [self::L, self::L, self::L, self::L]],
            'Quitando' => ['Quitando', [self::L, self::L, self::L, self::L]],
            'Encerrada' => ['closed', [self::L, self::L, self::L, self::L]],
            'Cancelada' => ['canceled', [self::L, self::L, self::L, self::L]],
            'Avaliada' => ['evaluated', [self::L, self::L, self::L, self::L]],
        ];
    }

    #[DataProvider('matrizEsperada')]
    public function test_resolver_devolve_a_matriz_para_todos_os_usuarios_nao_admin(string $status, array $esperado): void
    {
        $resolver = app(FieldPermissionResolver::class);
        $keys = ['preco', 'fornecedor', 'fone_do_fornecedor', 'aprovacao'];

        foreach ($this->u as $nome => $user) {
            if ($nome === 'admin') {
                continue;
            }
            $perms = $resolver->resolve($this->processo, $this->situacao($status), $user);
            foreach ($keys as $i => $key) {
                $this->assertSame($esperado[$i], $perms[$this->f[$key]->id], "{$status} / {$nome} / {$key}");
            }
        }
    }

    /**
     * Quem atua em cada situação, e como o formulário de edição renderiza cada campo
     */
    public static function formularioPorSituacao(): array
    {
        return [
            'Nova (demandante)' => ['new', 'demandante', ['preco' => 'hidden', 'fornecedor' => 'hidden', 'fone_do_fornecedor' => 'hidden', 'aprovacao' => 'hidden']],
            'Licitando (licitador)' => ['Licitando', 'licitador', ['preco' => 'enabled', 'fornecedor' => 'enabled', 'fone_do_fornecedor' => 'enabled', 'aprovacao' => 'hidden']],
            'Aprovando (aprovador)' => ['Aprovando', 'aprovador', ['preco' => 'disabled', 'fornecedor' => 'disabled', 'fone_do_fornecedor' => 'disabled', 'aprovacao' => 'enabled']],
            'Aprovando alto (aprovadorAlto)' => ['Aprovando alto', 'aprovadorAlto', ['preco' => 'disabled', 'fornecedor' => 'disabled', 'fone_do_fornecedor' => 'disabled', 'aprovacao' => 'enabled']],
            'Adquirindo (adquirente)' => ['Adquirindo', 'adquirente', ['preco' => 'disabled', 'fornecedor' => 'disabled', 'fone_do_fornecedor' => 'disabled', 'aprovacao' => 'disabled']],
            'Quitando (quitador)' => ['Quitando', 'quitador', ['preco' => 'disabled', 'fornecedor' => 'disabled', 'fone_do_fornecedor' => 'disabled', 'aprovacao' => 'disabled']],
        ];
    }

    #[DataProvider('formularioPorSituacao')]
    public function test_formulario_de_edicao_renderiza_cada_campo_conforme_a_matriz(string $status, string $ator, array $esperado): void
    {
        $demand = $this->demandaEm($status, ['preco' => '5000', 'fornecedor' => 'ACME', 'fone_do_fornecedor' => '1199', 'aprovacao' => 'Aprovado']);
        $this->actingAs($this->u[$ator]);

        $lw = Livewire::test(EditDemand::class, ['record' => $demand->id])->assertSuccessful();

        foreach ($esperado as $key => $estado) {
            $name = "field_data.{$key}";
            match ($estado) {
                'hidden' => $lw->assertFormFieldIsHidden($name),
                'disabled' => $lw->assertFormFieldIsVisible($name)->assertFormFieldIsDisabled($name),
                'enabled' => $lw->assertFormFieldIsVisible($name)->assertFormFieldIsEnabled($name),
            };
        }
    }

    /**
     * Fora do papel da situação o usuário não pode abrir a edição
     */
    public function test_usuarios_fora_do_papel_da_situacao_nao_editam(): void
    {
        $matriz = [
            'Licitando' => ['licitador'],
            'Aprovando' => ['aprovador'],
            'Aprovando alto' => ['aprovadorAlto'],
            'Adquirindo' => ['adquirente'],
            'Quitando' => ['quitador'],
        ];

        foreach ($matriz as $status => $autorizados) {
            $demand = $this->demandaEm($status, ['preco' => '5000']);
            foreach ($this->u as $nome => $user) {
                $pode = $nome === 'admin' || in_array($nome, $autorizados, true);
                $this->assertSame($pode, $user->can('update', $demand), "{$status} / {$nome}");
            }
        }
    }

    public function test_licitando_preco_obrigatorio_bloqueia_salvar_e_transicao(): void
    {
        $demand = $this->demandaEm('Licitando', [], $this->u['licitador']);
        $this->actingAs($this->u['licitador']);

        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->fillForm(['field_data.preco' => ''])
            ->call('save')
            ->assertHasFormErrors(['field_data.preco' => 'required']);

        $this->expectException(DemandTransitionException::class);
        $this->expectExceptionMessage('Preço');
        app(DemandTransitionService::class)->execute($demand, StatusTransition::where('from_status_id', $demand->process_status_id)->first(), $this->u['licitador']);
    }

    public function test_aprovando_aprovacao_obrigatoria_bloqueia_salvar_e_transicao(): void
    {
        foreach (['Aprovando' => 'aprovador', 'Aprovando alto' => 'aprovadorAlto'] as $status => $ator) {
            $demand = $this->demandaEm($status, ['preco' => '5000']);
            $this->actingAs($this->u[$ator]);

            Livewire::test(EditDemand::class, ['record' => $demand->id])
                ->call('save')
                ->assertHasFormErrors(['field_data.aprovacao' => 'required']);

            try {
                app(DemandTransitionService::class)->execute($demand, StatusTransition::where('from_status_id', $demand->process_status_id)->first(), $this->u[$ator]);
                $this->fail("{$status}: transição deveria ser bloqueada sem Aprovação");
            } catch (DemandTransitionException $e) {
                $this->assertStringContainsString('Aprovação', $e->getMessage());
            }
        }
    }

    /**
     * Payload adulterado (campo somente leitura/oculto enviado mesmo assim)
     * não pode alterar o valor salvo
     */
    public function test_campos_bloqueados_ignoram_payload_adulterado(): void
    {
        $casos = [
            ['Aprovando', 'aprovador', ['preco', 'fornecedor', 'fone_do_fornecedor'], ['aprovacao' => 'Aprovado']],
            ['Aprovando alto', 'aprovadorAlto', ['preco', 'fornecedor', 'fone_do_fornecedor'], ['aprovacao' => 'Aprovado']],
            ['Licitando', 'licitador', ['aprovacao'], ['preco' => '5000']],
            ['Adquirindo', 'adquirente', ['preco', 'fornecedor', 'fone_do_fornecedor', 'aprovacao'], []],
            ['Quitando', 'quitador', ['preco', 'fornecedor', 'fone_do_fornecedor', 'aprovacao'], []],
        ];

        foreach ($casos as [$status, $ator, $bloqueados, $extra]) {
            $original = ['preco' => '5000', 'fornecedor' => 'ACME', 'fone_do_fornecedor' => '1199', 'aprovacao' => 'Não aprovado'];
            $demand = $this->demandaEm($status, $original);
            $this->actingAs($this->u[$ator]);

            $lw = Livewire::test(EditDemand::class, ['record' => $demand->id]);
            foreach ($bloqueados as $key) {
                $lw->set("data.field_data.{$key}", $key === 'aprovacao' ? 'Aprovado' : '999999');
            }
            foreach ($extra as $key => $value) {
                $lw->set("data.field_data.{$key}", $value);
            }
            $lw->call('save')->assertHasNoFormErrors();

            foreach ($bloqueados as $key) {
                $this->assertSame($original[$key], $this->valor($demand, $key), "{$status}: {$key} foi alterado");
            }
        }
    }

    public function test_nova_demandante_nao_grava_campos_ocultos_na_criacao(): void
    {
        $this->actingAs($this->u['demandante']);

        Livewire::test(CreateDemand::class)
            ->fillForm([
                'entity_id' => $this->processo->id,
                'client_id' => Client::first()->id,
                'title' => 'Coffee break',
            ])
            ->assertFormFieldIsHidden('field_data.preco')
            ->assertFormFieldIsHidden('field_data.aprovacao')
            ->set('data.field_data.preco', '123')
            ->set('data.field_data.aprovacao', 'Aprovado')
            ->call('create')
            ->assertHasNoFormErrors();

        $demand = Demand::where('title', 'Coffee break')->firstOrFail();
        $this->assertSame($this->situacao('new')->id, $demand->process_status_id);
        $this->assertSame(0, $demand->fieldValues()->count());
    }

    public function test_situacoes_finais_mostram_tudo_somente_leitura_na_visualizacao(): void
    {
        foreach (['closed', 'canceled', 'evaluated'] as $status) {
            $demand = $this->demandaEm($status, ['preco' => '5000', 'fornecedor' => 'ACME', 'fone_do_fornecedor' => '1199', 'aprovacao' => 'Aprovado']);
            $this->actingAs($this->u['demandante']);

            $lw = Livewire::test(ViewDemand::class, ['record' => $demand->id])->assertSuccessful();
            foreach (array_keys($this->f) as $key) {
                $lw->assertFormFieldIsVisible("field_data.{$key}")->assertFormFieldIsDisabled("field_data.{$key}");
            }
            $lw->assertFormSet(['field_data.preco' => '5000', 'field_data.aprovacao' => 'Aprovado']);
        }
    }

    /**
     * Fluxo completo pela UI: Nova → Licitando → Aprovando (reprova) → Licitando
     * → Aprovando (aprova) → Adquirindo → Quitando → Encerrada
     */
    public function test_fluxo_completo_pela_interface_respeita_a_matriz_em_cada_etapa(): void
    {
        $demand = $this->demandaEm('new');
        $demand->update(['assigned_to' => null]);

        // Nova → Licitando (demandante)
        $this->actingAs($this->u['demandante']);
        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->callAction($this->acaoTransicao($demand))
            ->assertHasNoActionErrors();
        $demand->refresh();
        $this->assertSame('Licitando', $demand->processStatus->name);

        // Licitando: tenta enviar sem preço → bloqueia
        $this->actingAs($this->u['licitador']);
        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->callAction($this->acaoTransicao($demand));
        $this->assertSame('Licitando', $demand->fresh()->processStatus->name, 'enviou sem preço');

        // Licitando: preenche e envia (preço 5000 → Aprovando)
        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->fillForm(['field_data.preco' => '5000', 'field_data.fornecedor' => 'ACME', 'field_data.fone_do_fornecedor' => '1199'])
            ->callAction($this->acaoTransicao($demand));
        $demand->refresh();
        $this->assertSame('Aprovando', $demand->processStatus->name);
        $this->assertSame('5000', $this->valor($demand, 'preco'));
        $this->assertSame('ACME', $this->valor($demand, 'fornecedor'));

        // Aprovando: reprova → volta para Licitando
        $this->actingAs($this->u['aprovador']);
        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->assertFormSet(['field_data.preco' => '5000'])
            ->fillForm(['field_data.aprovacao' => 'Não aprovado'])
            ->callAction($this->acaoTransicao($demand));
        $demand->refresh();
        $this->assertSame('Licitando', $demand->processStatus->name);

        // Licitando de novo: aprovação continua oculta, preço editável
        $this->actingAs($this->u['licitador']);
        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->assertFormFieldIsHidden('field_data.aprovacao')
            ->assertFormFieldIsEnabled('field_data.preco')
            ->fillForm(['field_data.preco' => '4000'])
            ->callAction($this->acaoTransicao($demand));
        $demand->refresh();
        $this->assertSame('Aprovando', $demand->processStatus->name);

        // Aprovando de novo: a reprovação anterior foi descartada no retorno
        $this->actingAs($this->u['aprovador']);
        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->assertFormSet(['field_data.aprovacao' => null])
            ->fillForm(['field_data.aprovacao' => 'Aprovado'])
            ->callAction($this->acaoTransicao($demand));
        $demand->refresh();
        $this->assertSame('Adquirindo', $demand->processStatus->name);

        // Adquirindo → Quitando → Encerrada, sem conseguir alterar nada
        foreach (['adquirente' => 'Quitando', 'quitador' => 'Encerrada'] as $ator => $proxima) {
            $this->actingAs($this->u[$ator]);
            Livewire::test(EditDemand::class, ['record' => $demand->id])
                ->set('data.field_data.preco', '1')
                ->set('data.field_data.aprovacao', 'Não aprovado')
                ->callAction($this->acaoTransicao($demand));
            $demand->refresh();
            $this->assertSame($proxima, $demand->processStatus->name);
        }

        $this->assertSame('4000', $this->valor($demand, 'preco'));
        $this->assertSame('Aprovado', $this->valor($demand, 'aprovacao'));
    }

    private function enviar(Demand $demand, User $user): void
    {
        $this->travel(1)->minutes();
        app(DemandTransitionService::class)->execute(
            $demand->refresh(),
            StatusTransition::where('from_status_id', $demand->process_status_id)->firstOrFail(),
            $user,
        );
        $demand->refresh();
    }

    private function preencher(Demand $demand, string $key, string $value): void
    {
        $this->travel(1)->minutes();
        DemandFieldValue::updateOrCreate(
            ['demand_id' => $demand->id, 'custom_field_id' => $this->f[$key]->id],
            ['value' => $value],
        );
    }

    /**
     * Reprovada e re-licitada abaixo de 2.000, a demanda vai direto para
     * Adquirindo sem carregar a "Não aprovado" da rodada anterior
     */
    public function test_reprovacao_e_descartada_e_nao_chega_em_adquirindo(): void
    {
        $demand = $this->demandaEm('Licitando', ['preco' => '5000'], $this->u['licitador']);

        $this->enviar($demand, $this->u['licitador']);
        $this->assertSame('Aprovando', $demand->processStatus->name);

        $this->preencher($demand, 'aprovacao', 'Não aprovado');
        $this->enviar($demand, $this->u['aprovador']);
        $this->assertSame('Licitando', $demand->processStatus->name);
        $this->assertNull($this->valor($demand, 'aprovacao'));
        $this->assertSame('5000', $this->valor($demand, 'preco'), 'preço visível em Licitando deve ser mantido');

        $log = \App\Models\ActivityLog::where('auditable_id', $demand->id)->where('event', 'transition')->orderByDesc('created_at')->first();
        $this->assertSame(['Aprovação'], $log->new_values['discarded_fields']);

        $this->preencher($demand, 'preco', '1500');
        $this->enviar($demand, $this->u['licitador']);
        $this->assertSame('Adquirindo', $demand->processStatus->name);
        $this->assertNull($this->valor($demand, 'aprovacao'));
    }

    public function test_retorno_manual_de_aprovando_para_licitando_descarta_aprovacao(): void
    {
        $demand = $this->demandaEm('Licitando', ['preco' => '5000'], $this->u['licitador']);
        $this->enviar($demand, $this->u['licitador']);
        $this->preencher($demand, 'aprovacao', 'Aprovado');

        $this->actingAs($this->u['aprovador']);
        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->callAction('return_previous_status')
            ->assertHasNoActionErrors();

        $demand->refresh();
        $this->assertSame('Licitando', $demand->processStatus->name);
        $this->assertNull($this->valor($demand, 'aprovacao'));
        $this->assertSame('5000', $this->valor($demand, 'preco'));
    }

    /**
     * Valor que já existia quando a demanda saiu da situação (ex.: campo
     * escondido só "para frente") não pode ser apagado ao voltar a ela
     */
    public function test_valor_oculto_que_ja_existia_na_situacao_e_preservado(): void
    {
        // Fornecedor passa a ser oculto em Aprovando (dado interno da licitação)
        \App\Models\FieldPermission::where('entity_id', $this->processo->id)
            ->where('custom_field_id', $this->f['fornecedor']->id)
            ->where('process_status_id', $this->situacao('Aprovando')->id)
            ->update(['permission' => self::H]);

        $demand = $this->demandaEm('Licitando', ['preco' => '5000', 'fornecedor' => 'ACME'], $this->u['licitador']);

        $this->enviar($demand, $this->u['licitador']);              // → Aprovando (1ª vez)
        $this->preencher($demand, 'aprovacao', 'Não aprovado');
        $this->enviar($demand, $this->u['aprovador']);              // → Licitando
        $this->preencher($demand, 'fornecedor', 'ACME 2');           // update: created_at não muda
        $this->enviar($demand, $this->u['licitador']);              // → Aprovando (já percorrida)

        $this->assertSame('Aprovando', $demand->processStatus->name);
        $this->assertSame('ACME 2', $this->valor($demand, 'fornecedor'));
    }

    public function test_avanco_para_situacao_nova_nao_descarta_nada(): void
    {
        $demand = $this->demandaEm('Licitando', ['preco' => '5000', 'aprovacao' => 'Não aprovado'], $this->u['licitador']);

        $this->assertSame([], app(DemandTransitionService::class)->discardValuesFromLaterSteps($demand, $this->situacao('Aprovando')));
        $this->assertSame('Não aprovado', $this->valor($demand, 'aprovacao'));
    }

    /**
     * Ranking OPTIONAL > REQUIRED: o "piso" do ADMIN rebaixa REQUIRED para OPTIONAL
     */
    public function test_admin_tem_obrigatorios_rebaixados_para_opcional(): void
    {
        $perms = app(FieldPermissionResolver::class)->resolve($this->processo, $this->situacao('Licitando'), $this->u['admin']);
        $this->assertSame(self::O, $perms[$this->f['preco']->id]);

        $nova = app(FieldPermissionResolver::class)->resolve($this->processo, $this->situacao('new'), $this->u['admin']);
        $this->assertSame(self::O, $nova[$this->f['preco']->id]);
    }
}
