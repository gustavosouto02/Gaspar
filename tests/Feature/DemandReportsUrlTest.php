<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Filament\Pages\DemandReports;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemandReportsUrlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_role' => UserRoleEnum::ADMIN, 'is_active' => true]);
        $this->actingAs($this->admin);
    }

    /** O Filament preenche o formulário com todas as chaves vazias: o que importa é nenhum filtro ter valor. */
    private function semValores(?array $filtros): bool
    {
        return array_filter(Arr::dot($filtros ?? []), fn ($valor) => filled($valor)) === [];
    }

    /** @return array<string, array{0: string}> */
    public static function urlsMalformadas(): array
    {
        return [
            'filtros como texto' => ['tableFilters=abc'],
            'processo como lista' => ['tableFilters[entity_id][value][]=x'],
            'processo como texto' => ['tableFilters[entity_id]=x'],
            'situacoes como texto' => ['tableFilters[process_status_id][values]=x'],
            'periodo como lista' => ['tableFilters[periodo][from][]=x'],
            'filtro desconhecido' => ['tableFilters[nao_existe][value]=1'],
        ];
    }

    #[DataProvider('urlsMalformadas')]
    public function test_url_com_filtros_malformados_responde_200_e_ignora_o_filtro(string $query): void
    {
        $this->get('/admin/demand-reports?'.$query)->assertOk();

        parse_str($query, $params);
        Livewire::withQueryParams($params)->test(DemandReports::class)
            ->assertOk()
            ->assertSet('tableFilters', fn (?array $filtros) => $this->semValores($filtros));
    }

    public function test_url_com_filtro_valido_continua_aplicando_e_descarta_so_o_invalido(): void
    {
        $processo = CustomEntity::factory()->create();

        Livewire::withQueryParams(['tableFilters' => [
            'entity_id' => ['value' => $processo->id],
            'status' => ['values' => 'x'],
        ]])->test(DemandReports::class)
            ->assertSet('tableFilters.entity_id.value', $processo->id)
            ->assertSet('tableFilters.status.values', fn ($valores) => blank($valores));
    }

    public function test_filtros_da_url_prevalecem_sobre_a_consulta_salva_no_recarregamento(): void
    {
        $salvo = CustomEntity::factory()->create();
        $daUrl = CustomEntity::factory()->create();
        $report = SavedReport::create([
            'user_id' => $this->admin->id, 'name' => 'Salva',
            'filters_json' => ['entity_id' => ['value' => $salvo->id]],
        ]);

        Livewire::withQueryParams([
            'saved_report_id' => $report->id,
            'tableFilters' => ['entity_id' => ['value' => $daUrl->id]],
        ])->test(DemandReports::class)
            ->assertSet('tableFilters.entity_id.value', $daUrl->id);
    }

    public function test_abrir_a_consulta_salva_so_pelo_id_aplica_os_filtros_dela(): void
    {
        $salvo = CustomEntity::factory()->create();
        $dentro = Demand::factory()->create(['entity_id' => $salvo->id]);
        $fora = Demand::factory()->create(['entity_id' => CustomEntity::factory()->create()->id]);
        $report = SavedReport::create([
            'user_id' => $this->admin->id, 'name' => 'Salva',
            'filters_json' => ['entity_id' => ['value' => $salvo->id]],
        ]);

        Livewire::withQueryParams(['saved_report_id' => $report->id])->test(DemandReports::class)
            ->assertSet('tableFilters.entity_id.value', $salvo->id)
            ->assertCanSeeTableRecords(collect([$dentro]))
            ->assertCanNotSeeTableRecords(collect([$fora]));
    }
}
