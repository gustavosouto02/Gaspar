<?php

namespace Tests\Unit;

use App\Services\ProcessKanbanBoard;
use PHPUnit\Framework\TestCase;

class ProcessFlowOrderTest extends TestCase
{
    public function test_fluxo_linear_ignora_display_order_quando_ha_transicoes(): void
    {
        $nodes = ['nova' => 6, 'a' => 1, 'b' => 2];

        $this->assertSame(['nova', 'a', 'b'], ProcessKanbanBoard::flowOrder($nodes, [['nova', 'a'], ['a', 'b']], 'nova'));
    }

    public function test_ramificacao_deixa_irmas_na_mesma_camada_por_display_order(): void
    {
        $nodes = ['nova' => 0, 'x' => 5, 'y' => 3, 'fim' => 9];
        $edges = [['nova', 'x'], ['nova', 'y'], ['x', 'fim'], ['y', 'fim']];

        $this->assertSame(['nova', 'y', 'x', 'fim'], ProcessKanbanBoard::flowOrder($nodes, $edges, 'nova'));
    }

    public function test_camada_e_o_maior_caminho(): void
    {
        $nodes = ['nova' => 0, 'a' => 1, 'b' => 2, 'fim' => 3];
        $edges = [['nova', 'fim'], ['nova', 'a'], ['a', 'b'], ['b', 'fim']];

        $this->assertSame(['nova', 'a', 'b', 'fim'], ProcessKanbanBoard::flowOrder($nodes, $edges, 'nova'));
    }

    public function test_loop_de_retorno_nao_altera_a_ordem(): void
    {
        $nodes = ['nova' => 0, 'a' => 1, 'b' => 2];
        $edges = [['nova', 'a'], ['a', 'b'], ['b', 'a'], ['b', 'nova']];

        $this->assertSame(['nova', 'a', 'b'], ProcessKanbanBoard::flowOrder($nodes, $edges, 'nova'));
    }

    public function test_inalcancaveis_vao_ao_fim_por_display_order_e_destinos_fora_da_versao_sao_ignorados(): void
    {
        $nodes = ['nova' => 0, 'a' => 1, 'solta2' => 8, 'solta1' => 7];
        $edges = [['nova', 'a'], ['a', 'fora'], ['solta1', 'a']];

        $this->assertSame(['nova', 'a', 'solta1', 'solta2'], ProcessKanbanBoard::flowOrder($nodes, $edges, 'nova'));
    }

    public function test_sem_situacao_inicial_usa_display_order(): void
    {
        $this->assertSame(['b', 'a'], ProcessKanbanBoard::flowOrder(['a' => 2, 'b' => 1], [['a', 'b']], null));
    }
}
