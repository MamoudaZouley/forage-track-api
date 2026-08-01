<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->integer('kobo_id')->unique();
            $table->foreignId('well_id')->nullable()->constrained('wells')->onDelete('set null');
            $table->string('well_code');
            $table->string('village')->nullable();
            $table->string('region')->nullable();
            $table->string('technician_username')->nullable();
            $table->string('team_leader_name')->nullable();
            $table->date('visit_date');
            $table->string('maintenance_type')->nullable();
            $table->string('request_source')->nullable();
            $table->string('work_performed')->nullable();
            $table->text('work_description')->nullable();
            $table->text('parts_used')->nullable();
            $table->decimal('work_duration', 5, 1)->nullable();
            $table->string('final_result')->nullable();
            $table->string('pump_condition_before')->nullable();
            $table->string('pump_condition_after')->nullable();
            $table->string('water_flow_before')->nullable();
            $table->string('water_flow_after')->nullable();
            $table->boolean('needs_followup')->default(false);
            $table->text('observations')->nullable();
            $table->timestamp('submission_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};