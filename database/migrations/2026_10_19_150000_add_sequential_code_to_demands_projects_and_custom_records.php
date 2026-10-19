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
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Adiciona coluna code em demands
        if (! Schema::hasColumn('demands', 'code')) {
            Schema::table('demands', function (Blueprint $table) {
                $table->string('code')->nullable()->after('id');
            });
        }

        // 2. Adiciona coluna code em custom_records
        if (! Schema::hasColumn('custom_records', 'code')) {
            Schema::table('custom_records', function (Blueprint $table) {
                $table->string('code')->nullable()->after('id');
            });
        }

        // 3. Garante que a coluna number existe em projects
        if (! Schema::hasColumn('projects', 'number')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('number')->nullable()->after('id');
            });
        }

        // 4. Popula registros existentes sem código
        $codeGenerator = app(CodeGeneratorService::class);

        // Demands
        $demands = DB::table('demands')
            ->whereNull('code')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'created_at']);

        foreach ($demands as $demand) {
            $year = $demand->created_at ? Carbon::parse($demand->created_at)->year : (int) date('Y');
            $code = $codeGenerator->generate('demands', 'code', 'DEM', $year);
            DB::table('demands')->where('id', $demand->id)->update(['code' => $code]);
        }

        // Projects
        $projects = DB::table('projects')
            ->whereNull('number')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'created_at']);

        foreach ($projects as $project) {
            $year = $project->created_at ? Carbon::parse($project->created_at)->year : (int) date('Y');
            $code = $codeGenerator->generate('projects', 'number', 'PRJ', $year);
            DB::table('projects')->where('id', $project->id)->update(['number' => $code]);
        }

        // Custom Records
        $customRecords = DB::table('custom_records')
            ->whereNull('code')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'created_at']);

        foreach ($customRecords as $record) {
            $year = $record->created_at ? Carbon::parse($record->created_at)->year : (int) date('Y');
            $code = $codeGenerator->generate('custom_records', 'code', 'REC', $year);
            DB::table('custom_records')->where('id', $record->id)->update(['code' => $code]);
        }

        // 5. Adiciona índices únicos
        Schema::table('demands', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::table('custom_records', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unique('number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        Schema::table('custom_records', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['number']);
        });
    }
};
