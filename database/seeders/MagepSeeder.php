<?php

namespace Database\Seeders;

use App\Models\CustomEntity;
use App\Models\ProcessStatus;
use Illuminate\Database\Seeder;

class MagepSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = \App\Models\User::first()->id ?? null;

        // 1. Criar MAGEP (Macroprocesso bloqueado)
        $magep = CustomEntity::firstOrCreate(
            ['name' => 'MAGEP - Macroprocesso de Gestão de Projetos'],
            [
                'is_active' => false,
                'description' => 'Macroprocesso fundamental para gestão de projetos',
                'created_by' => $adminId,
            ]
        );

        // 2. Processo de Gerenciamento de Projetos
        $gerenciamento = CustomEntity::firstOrCreate(
            ['name' => 'Processo de Gerenciamento de Projetos'],
            [
                'is_active' => true,
                'description' => 'Iniciação, planejamento, execução e encerramento',
                'created_by' => $adminId,
            ]
        );
        $this->syncStatuses($gerenciamento, [
            'Iniciação', 'Planejamento', 'Execução', 'Encerramento', 'Cancelado', 'Suspenso'
        ]);

        // 3. Processo de Execução de Projetos
        $execucao = CustomEntity::firstOrCreate(
            ['name' => 'Processo de Execução de Projetos'],
            [
                'is_active' => true,
                'description' => 'Backlog, a fazer, fazendo, feito, finalizado',
                'created_by' => $adminId,
            ]
        );
        $this->syncStatuses($execucao, [
            'Backlog', 'A Fazer', 'Fazendo', 'Feito', 'Finalizado'
        ]);
    }

    private function syncStatuses(CustomEntity $process, array $statusNames)
    {
        $order = 2; // "Nova" is order 1
        
        foreach ($statusNames as $name) {
            $status = ProcessStatus::firstOrCreate(
                ['name' => $name], 
                [
                    'color' => 'gray',
                    'created_by' => \App\Models\User::first()->id ?? null,
                ]
            );
            if (!$process->processStatuses()->where('process_status_id', $status->id)->exists()) {
                $process->processStatuses()->attach($status->id, ['display_order' => $order++]);
            }
        }
    }
}
