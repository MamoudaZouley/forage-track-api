<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervision_history', function (Blueprint $table) {
            $table->id();
            $table->integer('kobo_id')->unique();
            $table->string('well_code')->nullable();
            $table->string('village')->nullable();
            $table->string('supervisor_username')->nullable();
            $table->date('visit_date');
            $table->tinyInteger('week_number')->nullable(); // 1-4 (semaine du mois)
            $table->string('overall_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_history');
    }
};