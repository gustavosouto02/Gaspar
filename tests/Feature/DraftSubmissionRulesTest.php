<?php

namespace Tests\Feature;

use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Exceptions\DemandTransitionException;
use App\Filament\Resources\DemandResource\Pages\CreateDemand;
use App\Filament\Resources\DemandResource\Pages\EditDemand;
use App\Filament\Resources\DemandResource\Pages\ViewDemand;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\FieldPermission;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use App\Models\User;
use App\Notifications\DemandActivityNotification;
use App\Services\DemandTransitionService;
use Database\Seeders\AquiProcessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Regras do envio de rascunho: quem pode enviar, o que acontece quando o envio falha
 * e a partir de quando o prazo (SLA) é contado. Usa o processo AQUI (SLA de 120h).
 */
class DraftSubmissionRulesTest extends TestCase
{
    use RefreshDatabase;

    private const NAO_ENVIADA = 'A demanda foi salva como rascunho, mas não foi enviada';

    private CustomEntity $processo;

    private User $admin;

    private User $demandante;

    private User $licitador;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->seed(AquiProcessSeeder::class);

        $this->processo = CustomEntity::where('name', 'AQUI - Contratação de serviços avulsos')->firstOrFail();
        $this->admin = User::where('email', 'admin@gaspar.com')->firstOrFail();
        $this->demandante = User::where('email', 'adinilson.martins@inct.org.br')->firstOrFail();
        $this->licitador = User::where('email', 'adinilson.martins@gmail.com')->firstOrFail();
    }

    private function situacao(string $nameOrKey): ProcessStatus
    {
        return ProcessStatus::where('system_key', $nameOrKey)->first()
            ?? ProcessStatus::where('name', $nameOrKey)->firstOrFail();
    }

    private function rascunho(array $atributos = []): Demand
    {
        return Demand::factory()->create($atributos + [
            'entity_id' => $this->processo->id,
            'client_id' => Client::first()->id,
            'process_status_id' => $this->situacao('new')->id,
            'requested_by' => $this->demandante->id,
            'created_by' => $this->demandante->id,
            'status' => DemandStatusEnum::DRAFT,
        ]);
    }

    private function transicaoDeNova(): StatusTransition
    {
        return StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $this->situacao('new')->id)
            ->firstOrFail();
    }

    private function acaoDeNova(): string
    {
        return 'transition_'.Str::slug($this->transicaoDeNova()->id);
    }

    private function dadosDeCriacao(string $titulo): array
    {
        return [
            'entity_id' => $this->processo->id,
            'client_id' => Client::first()->id,
            'title' => $titulo,
            'project_id' => 'none',
            'supplier_id' => 'none',
        ];
    }

    private function botoesDoRodape(string $pagina, Demand $demand): array
    {
        $component = Livewire::test($pagina, ['record' => $demand->id])->instance();

        return array_values(array_map(
            fn ($action) => $action->getName(),
            array_filter($component->getCachedFormActions(), fn ($action) => $action->isVisible()),
        ));
    }

    private function titulosDosAvisos(): array
    {
        return array_column(session('filament.notifications', []), 'title');
    }

    /** Falha técnica depois de a transição já ter gravado a demanda */
    private function quebrarNoRegistroDaTransicao(): void
    {
        ActivityLog::creating(function (ActivityLog $log) {
            if ($log->event === 'transition') {
                throw new RuntimeException('SQLSTATE detalhe interno');
            }
        });
    }

    private function assertContinuaRascunho(Demand $demand): void
    {
        $demand->refresh();

        $this->assertSame(DemandStatusEnum::DRAFT, $demand->status);
        $this->assertSame($this->situacao('new')->id, $demand->process_status_id);
        $this->assertNull($demand->assigned_to);
        $this->assertNull($demand->sla_due_at);
        $this->assertNull($demand->started_at);
    }

    // ----- Só quem salvou o rascunho o envia -----

    public static function naoAutores(): array
    {
        return ['administrador' => ['admin'], 'membro que poderia disparar a transição' => ['licitador']];
    }

    #[DataProvider('naoAutores')]
    public function test_quem_nao_salvou_o_rascunho_nao_ve_os_botoes_de_envio(string $quem): void
    {
        $this->transicaoDeNova()->update(['allowed_role_ids' => []]);
        $demand = $this->rascunho();
        $this->actingAs($this->{$quem});

        $this->assertSame(['save', 'cancel'], $this->botoesDoRodape(EditDemand::class, $demand));
        $this->assertSame([], $this->botoesDoRodape(ViewDemand::class, $demand));

        $this->actingAs($this->demandante);
        $this->assertSame(['enviar_para_atendimento', 'save', 'cancel'], $this->botoesDoRodape(EditDemand::class, $demand));
        $this->assertSame([$this->acaoDeNova()], $this->botoesDoRodape(ViewDemand::class, $demand));
    }

    #[DataProvider('naoAutores')]
    public function test_quem_nao_salvou_o_rascunho_nao_envia_chamando_o_metodo_direto(string $quem): void
    {
        $this->transicaoDeNova()->update(['allowed_role_ids' => []]);
        $demand = $this->rascunho();
        $this->actingAs($this->{$quem});

        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->call('submitDraft')
            ->assertForbidden();

        $this->assertContinuaRascunho($demand);
        Notification::assertNothingSent();
    }

    public function test_servico_de_transicao_recusa_rascunho_de_outra_pessoa(): void
    {
        $demand = $this->rascunho();

        try {
            app(DemandTransitionService::class)->execute($demand, $this->transicaoDeNova(), $this->admin);
            $this->fail('O envio deveria ser recusado');
        } catch (DemandTransitionException $e) {
            $this->assertSame('Apenas quem salvou o rascunho pode enviá-lo.', $e->getMessage());
        }

        $this->assertContinuaRascunho($demand);
    }

    public function test_payload_adulterado_nao_tira_o_rascunho_de_rascunho_nem_troca_a_autoria(): void
    {
        $demand = $this->rascunho();
        $this->actingAs($this->admin);

        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->set('data.status', DemandStatusEnum::ACTIVE->value)
            ->set('data.process_status_id', $this->situacao('Licitando')->id)
            ->set('data.created_by', $this->admin->id)
            ->set('data.requested_by', $this->admin->id)
            ->set('data.title', 'Editado pelo administrador')
            ->call('save')
            ->assertHasNoFormErrors();

        $demand->refresh();
        $this->assertSame('Editado pelo administrador', $demand->title);
        $this->assertSame($this->demandante->id, $demand->created_by);
        $this->assertSame($this->demandante->id, $demand->requested_by);
        $this->assertContinuaRascunho($demand);
    }

    public function test_navegador_nao_liga_o_modo_de_envio(): void
    {
        $demand = $this->rascunho();
        $this->actingAs($this->admin);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(EditDemand::class, ['record' => $demand->id])->set('isSubmittingDraft', true);
    }

    public function test_demanda_ja_enviada_nao_e_reenviada_pelo_envio_de_rascunho(): void
    {
        $demand = $this->rascunho(['status' => DemandStatusEnum::ACTIVE]);
        $this->actingAs($this->demandante);

        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->call('submitDraft')
            ->assertForbidden();

        $this->assertSame($this->situacao('new')->id, $demand->fresh()->process_status_id);
    }

    public function test_sem_criador_o_rascunho_e_de_quem_demandou(): void
    {
        $demand = new Demand(['requested_by' => $this->demandante->id]);

        $this->assertTrue($demand->canBeSubmittedBy($this->demandante));
        $this->assertFalse($demand->canBeSubmittedBy($this->admin));
    }

    public function test_a_autoria_da_nova_demanda_e_de_quem_esta_salvando(): void
    {
        $this->actingAs($this->demandante);

        Livewire::test(CreateDemand::class)
            ->fillForm($this->dadosDeCriacao('Autoria'))
            ->set('data.created_by', $this->admin->id)
            ->callAction('salvar');

        $this->assertSame($this->demandante->id, Demand::where('title', 'Autoria')->firstOrFail()->created_by);
    }

    // ----- Envio recusado ou com falha -----

    private function recusarEnvio(): void
    {
        // Condicional sem "senão": o serviço recusa a transição
        $this->transicaoDeNova()->update([
            'to_status_id' => $this->situacao('conditional')->id,
            'default_to_status_id' => null,
        ]);
    }

    public function test_envio_recusado_na_criacao_mostra_so_o_aviso_de_erro_e_nao_preenche_o_prazo(): void
    {
        $this->recusarEnvio();
        $this->actingAs($this->demandante);

        Livewire::test(CreateDemand::class)
            ->fillForm($this->dadosDeCriacao('Recusada'))
            ->call('create');

        $this->assertSame([self::NAO_ENVIADA], $this->titulosDosAvisos());
        $this->assertContinuaRascunho(Demand::where('title', 'Recusada')->firstOrFail());
        Notification::assertNothingSent();
    }

    public function test_envio_recusado_na_edicao_mostra_so_o_aviso_de_erro(): void
    {
        $this->recusarEnvio();
        $demand = $this->rascunho();
        $this->actingAs($this->demandante);

        Livewire::test(EditDemand::class, ['record' => $demand->id])->call('submitDraft');

        $this->assertSame([self::NAO_ENVIADA], $this->titulosDosAvisos());
        $this->assertContinuaRascunho($demand);
        Notification::assertNothingSent();
    }

    public function test_falha_inesperada_na_criacao_deixa_um_unico_rascunho_sem_vazar_detalhes(): void
    {
        Exceptions::fake();
        $this->quebrarNoRegistroDaTransicao();
        $this->actingAs($this->demandante);

        Livewire::test(CreateDemand::class)
            ->fillForm($this->dadosDeCriacao('Falhou'))
            ->call('create')
            ->assertRedirect();

        $this->assertSame([self::NAO_ENVIADA], $this->titulosDosAvisos());
        $this->assertStringNotContainsString('SQLSTATE', json_encode(session('filament.notifications')));
        $this->assertSame(1, Demand::where('title', 'Falhou')->count());
        $this->assertContinuaRascunho(Demand::where('title', 'Falhou')->firstOrFail());
        Exceptions::assertReported(RuntimeException::class);
        Notification::assertNothingSent();
    }

    public function test_falha_inesperada_no_envio_do_rascunho_o_mantem_em_rascunho(): void
    {
        Exceptions::fake();
        $this->quebrarNoRegistroDaTransicao();
        $demand = $this->rascunho();
        $this->actingAs($this->demandante);

        Livewire::test(EditDemand::class, ['record' => $demand->id])->call('submitDraft');

        $this->assertSame([self::NAO_ENVIADA], $this->titulosDosAvisos());
        $this->assertContinuaRascunho($demand);
        Exceptions::assertReported(RuntimeException::class);
        Notification::assertNothingSent();
    }

    public function test_falha_inesperada_no_botao_enviar_da_visualizacao_nao_altera_a_demanda(): void
    {
        Exceptions::fake();
        $this->quebrarNoRegistroDaTransicao();
        $demand = $this->rascunho();
        $this->actingAs($this->demandante);

        Livewire::test(ViewDemand::class, ['record' => $demand->id])->callAction($this->acaoDeNova());

        $this->assertSame(['Não foi possível executar a transição'], $this->titulosDosAvisos());
        $this->assertContinuaRascunho($demand);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_falha_ao_gravar_os_campos_nao_deixa_demanda_pela_metade(): void
    {
        FieldPermission::where('entity_id', $this->processo->id)
            ->where('process_status_id', $this->situacao('new')->id)
            ->update(['permission' => FieldPermissionEnum::OPTIONAL]);
        DemandFieldValue::creating(fn () => throw new RuntimeException('falha ao gravar o campo'));
        $this->actingAs($this->demandante);
        $antes = Demand::count();

        try {
            Livewire::test(CreateDemand::class)
                ->fillForm($this->dadosDeCriacao('Pela metade') + ['field_data' => ['preco' => '10']])
                ->call('create');
            $this->fail('A falha deveria interromper a criação');
        } catch (RuntimeException $e) {
            $this->assertSame('falha ao gravar o campo', $e->getMessage());
        }

        $this->assertSame($antes, Demand::count());
        Notification::assertNothingSent();
    }

    public function test_transacao_revertida_nao_dispara_email(): void
    {
        $demand = $this->rascunho();

        try {
            DB::transaction(function () use ($demand) {
                app(DemandTransitionService::class)->execute($demand, $this->transicaoDeNova(), $this->demandante);

                throw new RuntimeException('desfaz tudo');
            });
        } catch (RuntimeException) {
            // esperado
        }

        Notification::assertNothingSent();
        $this->assertContinuaRascunho($demand);
    }

    // ----- Prazo (SLA) conta a partir do envio -----

    public function test_rascunho_nao_tem_prazo_e_o_sla_conta_a_partir_do_envio(): void
    {
        $this->freezeSecond();
        $this->actingAs($this->demandante);

        Livewire::test(CreateDemand::class)
            ->fillForm($this->dadosDeCriacao('Prazo'))
            ->callAction('salvar');

        $demand = Demand::where('title', 'Prazo')->firstOrFail();
        $this->assertNull($demand->sla_due_at);
        $this->assertNull($demand->started_at);

        $this->travel(10)->days();

        Livewire::test(EditDemand::class, ['record' => $demand->id])
            ->assertDontSee('(Vencido)')
            ->call('submitDraft');

        $demand->refresh();
        $this->assertEquals(now()->addHours(120), $demand->sla_due_at);
        $this->assertEquals(now(), $demand->started_at);
    }

    public static function caminhosDeEnvio(): array
    {
        return ['Enviar para atendimento' => [EditDemand::class], 'Enviar na visualização' => [ViewDemand::class]];
    }

    #[DataProvider('caminhosDeEnvio')]
    public function test_prazo_antigo_gravado_no_rascunho_nao_vale_e_e_recalculado_no_envio(string $pagina): void
    {
        $this->freezeSecond();
        // Rascunho salvo antes da mudança: tem prazo contado desde a criação, já passado
        $demand = $this->rascunho(['sla_due_at' => now()->subDays(3)]);
        $this->actingAs($this->demandante);

        $this->assertNull($demand->deadline);
        $component = Livewire::test($pagina, ['record' => $demand->id])->assertDontSee('(Vencido)');

        $pagina === EditDemand::class
            ? $component->call('submitDraft')
            : $component->callAction($this->acaoDeNova());

        $this->assertEquals(now()->addHours(120), $demand->fresh()->sla_due_at);
    }

    public function test_demanda_e_subdemanda_criadas_ja_enviadas_tem_prazo(): void
    {
        $this->freezeSecond();
        $mae = $this->rascunho(['status' => DemandStatusEnum::ACTIVE]);
        $this->actingAs($this->demandante);

        Livewire::test(CreateDemand::class)
            ->fillForm($this->dadosDeCriacao('Enviada'))
            ->call('create');
        Livewire::test(CreateDemand::class)
            ->fillForm($this->dadosDeCriacao('Subdemanda enviada'))
            ->set('data.parent_demand_id', $mae->id)
            ->call('create');

        foreach (['Enviada', 'Subdemanda enviada'] as $titulo) {
            $this->assertEquals(now()->addHours(120), Demand::where('title', $titulo)->firstOrFail()->sla_due_at, $titulo);
        }
        // Criação fora das telas (factory/seeder) sem ser rascunho também ganha prazo
        $this->assertNotNull($mae->sla_due_at);
    }

    // ----- E-mails sem duplicidade -----

    public function test_demandante_recebe_um_unico_email_quando_outra_pessoa_envia_o_rascunho_que_salvou(): void
    {
        $this->transicaoDeNova()->update(['allowed_role_ids' => []]);
        // Quem salvou não é o demandante
        $demand = $this->rascunho(['created_by' => $this->licitador->id]);
        $this->actingAs($this->licitador);

        Livewire::test(EditDemand::class, ['record' => $demand->id])->call('submitDraft');

        $this->assertSame('Licitando', $demand->fresh()->processStatus->name);
        Notification::assertSentToTimes($this->demandante, DemandActivityNotification::class, 1);
    }

    public function test_demandante_que_tambem_e_responsavel_recebe_um_unico_aviso_da_transicao(): void
    {
        $demand = $this->rascunho(['status' => DemandStatusEnum::ACTIVE]);
        // A próxima etapa volta para o próprio demandante
        StatusTransition::where('entity_id', $this->processo->id)
            ->where('from_status_id', $this->situacao('Licitando')->id)
            ->update(['allowed_role_ids' => ['__requester__']]);

        app(DemandTransitionService::class)->execute($demand, $this->transicaoDeNova(), $this->admin);

        $this->assertSame($this->demandante->id, $demand->fresh()->assigned_to);
        Notification::assertSentToTimes($this->demandante, DemandActivityNotification::class, 1);
    }
}
