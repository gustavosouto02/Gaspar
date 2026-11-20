<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabelas que passam a ter um único Gestor (usuário com perfil GESTOR).
     * A coluna é nullable porque já existem registros; a obrigatoriedade é do formulário.
     * Cadastros (custom_record_types) têm um ou mais gestores, via pivô custom_record_type_manager.
     *
     * @var list<string>
     */
    private array $tables = [
        'macroprocesses',
        'custom_entities',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'manager_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('custom_record_type_manager')) {
            Schema::create('custom_record_type_manager', function (Blueprint $table) {
                $table->uuid('custom_record_type_id');
                $table->uuid('user_id');
                $table->timestamps();

                $table->foreign('custom_record_type_id')->references('id')->on('custom_record_types')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

                $table->unique(['custom_record_type_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_record_type_manager');

        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'manager_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('manager_id');
            });
        }
    }
};
