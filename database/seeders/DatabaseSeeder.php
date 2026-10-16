<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed principal da aplicação Gaspar.
     * Executa o processo de homologação E2E: "AQUI - Contratação de serviços avulsos".
     */
    public function run(): void
    {
        $this->call(AquiProcessSeeder::class);
    }
}
