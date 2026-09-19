<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite (testes) não suporta drop de FK por nome nem drop de coluna com FK;
        // como no banco de teste a tabela está sempre vazia, recria na estrutura final
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::dropIfExists('custom_records');
            Schema::create('custom_records', function (Blueprint $table) {
                $table->uuid('id')->primary(); // UUID7
                $table->uuid('custom_record_type_id')->nullable();
                $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
                $table->json('data_json');
                $table->timestamps();
                $table->softDeletes();
            });

            return;
        }

        Schema::table('custom_records', function (Blueprint $table) {
            $table->dropForeign('custom_records_entity_id_foreign');
            $table->dropColumn('entity_id');
            $table->uuid('custom_record_type_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_records', function (Blueprint $table) {
            $table->dropColumn('custom_record_type_id');
            $table->uuid('entity_id')->nullable()->after('id');
        });
    }
};
