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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('number')->nullable()->after('id');
            $table->date('start_date_forecast')->nullable()->after('description');
            $table->date('end_date_forecast')->nullable()->after('start_date_forecast');
            $table->date('end_date')->nullable()->after('end_date_forecast');
            $table->string('status')->nullable()->after('end_date');
            $table->string('contact_name')->nullable()->after('status');
            $table->string('manager_name')->nullable()->after('contact_name');
            $table->string('sponsor_name')->nullable()->after('manager_name');
            $table->string('scrum_master_name')->nullable()->after('sponsor_name');
            $table->string('product_owner_name')->nullable()->after('scrum_master_name');
            $table->decimal('budget', 15, 2)->nullable()->after('product_owner_name');
            $table->decimal('spent', 15, 2)->nullable()->after('budget');
            $table->string('sponsor_evaluation')->nullable()->after('spent');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'number', 'start_date_forecast', 'end_date_forecast', 'end_date',
                'status', 'contact_name', 'manager_name', 'sponsor_name', 
                'scrum_master_name', 'product_owner_name', 'budget', 'spent', 'sponsor_evaluation'
            ]);
        });
    }
};
