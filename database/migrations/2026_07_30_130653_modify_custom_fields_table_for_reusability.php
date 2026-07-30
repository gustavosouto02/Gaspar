<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the pivot table
        Schema::create('custom_entity_custom_field', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('custom_entity_id')->constrained('custom_entities')->onDelete('cascade');
            $table->foreignUuid('custom_field_id')->constrained('custom_fields')->onDelete('cascade');
            $table->integer('field_order')->default(0);
            $table->timestamps();

            $table->unique(['custom_entity_id', 'custom_field_id'], 'entity_field_unique');
        });

        // 2. Migrate existing data from custom_fields to the pivot table
        $fields = DB::table('custom_fields')->get();
        foreach ($fields as $field) {
            if ($field->entity_id) {
                DB::table('custom_entity_custom_field')->insert([
                    'id' => (string) Str::uuid7(),
                    'custom_entity_id' => $field->entity_id,
                    'custom_field_id' => $field->id,
                    'field_order' => $field->field_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Drop old columns from custom_fields
        Schema::table('custom_fields', function (Blueprint $table) {
            // Depending on database engine, dropping foreign key might require specific name
            // By convention it is table_column_foreign.
            $table->dropForeign(['entity_id']);
            $table->dropColumn('entity_id');
            $table->dropColumn('field_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restore columns in custom_fields
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->foreignUuid('entity_id')->nullable()->constrained('custom_entities')->onDelete('cascade');
            $table->integer('field_order')->default(0);
        });

        // 2. Migrate data back from pivot table (take the first entity found for each field)
        $pivots = DB::table('custom_entity_custom_field')->get();
        foreach ($pivots as $pivot) {
            DB::table('custom_fields')
                ->where('id', $pivot->custom_field_id)
                ->update([
                    'entity_id' => $pivot->custom_entity_id,
                    'field_order' => $pivot->field_order,
                ]);
        }

        // 3. Drop the pivot table
        Schema::dropIfExists('custom_entity_custom_field');
    }
};
