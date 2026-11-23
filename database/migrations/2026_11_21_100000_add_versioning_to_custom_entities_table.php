<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('custom_entities', function (Blueprint $table) {
            // Aponta para a versão 1 do processo (a própria versão 1 aponta para si mesma)
            $table->uuid('root_entity_id')->nullable()->index()->after('id');
            $table->unsignedInteger('version')->default(1)->after('root_entity_id');
            $table->boolean('is_current')->default(true)->index()->after('version');
            $table->uuid('previous_version_id')->nullable()->after('is_current');
        });

        // Todo processo existente vira a versão 1 vigente dele mesmo (inclui os excluídos logicamente)
        DB::table('custom_entities')->update([
            'root_entity_id' => DB::raw('id'),
            'version' => 1,
            'is_current' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_entities', function (Blueprint $table) {
            $table->dropIndex(['root_entity_id']);
            $table->dropIndex(['is_current']);
            $table->dropColumn(['root_entity_id', 'version', 'is_current', 'previous_version_id']);
        });
    }
};
