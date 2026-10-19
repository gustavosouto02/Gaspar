<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CodeGeneratorService
{
    /**
     * Gera o próximo código sequencial no formato PREFIX-YYYY-NNNN.
     * Exemplo: DEM-2026-0001
     * 
     * O número é estritamente incremental e persistente: mesmo que registros
     * sejam excluídos do banco de dados, o número nunca é reciclado.
     */
    public function generate(string $table, string $column, string $prefix, ?int $year = null): string
    {
        $prefix = strtoupper(trim($prefix));
        $year = $year ?: (int) date('Y');

        if (! Schema::hasTable('sequential_counters')) {
            return $this->generateFallback($table, $column, $prefix, $year);
        }

        return DB::transaction(function () use ($table, $column, $prefix, $year) {
            $counter = DB::table('sequential_counters')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                // Descobre o maior sequencial existente na tabela de dados para inicializar
                $maxExisting = $this->getMaxExistingSequence($table, $column, $prefix, $year);

                DB::table('sequential_counters')->insert([
                    'prefix' => $prefix,
                    'year' => $year,
                    'current_sequence' => $maxExisting,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $currentSeq = $maxExisting;
            } else {
                $currentSeq = (int) $counter->current_sequence;
            }

            $nextSeq = $currentSeq + 1;

            // Garante que não colida com nenhum registro preexistente na tabela
            do {
                $code = sprintf('%s-%d-%04d', $prefix, $year, $nextSeq);
                $exists = DB::table($table)->where($column, $code)->exists();
                if ($exists) {
                    $nextSeq++;
                }
            } while ($exists);

            // Atualiza o contador persistente
            DB::table('sequential_counters')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->update([
                    'current_sequence' => $nextSeq,
                    'updated_at' => now(),
                ]);

            return $code;
        });
    }

    private function getMaxExistingSequence(string $table, string $column, string $prefix, int $year): int
    {
        $pattern = "{$prefix}-{$year}-%";

        $latest = DB::table($table)
            ->where($column, 'like', $pattern)
            ->orderBy($column, 'desc')
            ->value($column);

        if (! $latest) {
            return 0;
        }

        $parts = explode('-', $latest);
        return (int) end($parts);
    }

    private function generateFallback(string $table, string $column, string $prefix, int $year): string
    {
        $nextSeq = $this->getMaxExistingSequence($table, $column, $prefix, $year) + 1;

        do {
            $code = sprintf('%s-%d-%04d', $prefix, $year, $nextSeq);
            $exists = DB::table($table)->where($column, $code)->exists();
            if ($exists) {
                $nextSeq++;
            }
        } while ($exists);

        return $code;
    }
}
