<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('well_id')->constrained('wells')->onDelete('cascade');
            $table->string('supervisor_name');
            $table->string('supervisor_username', 100);
            $table->date('visit_date');
            $table->dateTime('submission_time')->nullable();
            $table->enum('overall_status', ['operational', 'not_working', 'suspended']);
            $table->decimal('duration_minutes', 5, 1)->nullable();
            $table->integer('week_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisions');
    }
};