<?php

use App\Services\CodeGeneratorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabelas que receberão a coluna sequencial 'code' e seus respectivos prefixos.
     *
     * @var array<string, string>
     */
    private array $entities = [
        'custom_entities' => 'PRC',
        'macroprocesses' => 'MAC',
        'custom_fields' => 'CMP',
        'users' => 'USR',
        'clients' => 'CLI',
        'suppliers' => 'FOR',
        'process_roles' => 'PAP',
        'process_statuses' => 'SIT',
        'custom_record_types' => 'TPC',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $codeGenerator = app(CodeGeneratorService::class);

        foreach ($this->entities as $table => $prefix) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'code')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('code')->nullable()->after('id');
                });
            }

            $records = DB::table($table)
                ->whereNull('code')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get(['id', 'created_at']);

            foreach ($records as $record) {
                $year = $record->created_at ? Carbon::parse($record->created_at)->year : (int) date('Y');
                $code = $codeGenerator->generate($table, 'code', $prefix, $year);
                DB::table($table)->where('id', $record->id)->update(['code' => $code]);
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique('code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys($this->entities) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'code')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropUnique(['code']);
                    $blueprint->dropColumn('code');
                });
            }
        }
    }
};

