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
        // 1. Add model_class and is_system columns to custom_record_types
        Schema::table('custom_record_types', function (Blueprint $table) {
            $table->string('model_class')->nullable()->after('is_active');
            $table->boolean('is_system')->default(false)->after('model_class');
        });

        // 2. Add custom_data JSON column to permanent registration tables
        Schema::table('users', function (Blueprint $table) {
            $table->json('custom_data')->nullable();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->json('custom_data')->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->json('custom_data')->nullable();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->json('custom_data')->nullable();
        });

        Schema::table('macroprocesses', function (Blueprint $table) {
            $table->json('custom_data')->nullable();
        });

        // 3. Seed system entries for each permanent model
        $systemTypes = [
            ['name' => 'Usuários', 'slug' => 'usuarios', 'model_class' => 'App\\Models\\User'],
            ['name' => 'Clientes', 'slug' => 'clientes', 'model_class' => 'App\\Models\\Client'],
            ['name' => 'Projetos', 'slug' => 'projetos', 'model_class' => 'App\\Models\\Project'],
            ['name' => 'Fornecedores', 'slug' => 'fornecedores', 'model_class' => 'App\\Models\\Supplier'],
            ['name' => 'Macroprocessos', 'slug' => 'macroprocessos', 'model_class' => 'App\\Models\\Macroprocess'],
        ];

        foreach ($systemTypes as $type) {
            DB::table('custom_record_types')->insert([
                'id' => (string) Str::uuid7(),
                'name' => $type['name'],
                'slug' => $type['slug'],
                'is_active' => true,
                'model_class' => $type['model_class'],
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove system entries
        DB::table('custom_record_types')->where('is_system', true)->delete();

        // Remove custom_data columns
        Schema::table('macroprocesses', function (Blueprint $table) {
            $table->dropColumn('custom_data');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('custom_data');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('custom_data');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('custom_data');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('custom_data');
        });

        // Remove columns from custom_record_types
        Schema::table('custom_record_types', function (Blueprint $table) {
            $table->dropColumn(['model_class', 'is_system']);
        });
    }
};
