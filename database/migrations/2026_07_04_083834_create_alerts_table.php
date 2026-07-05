<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervision_id')->constrained('supervisions')->onDelete('cascade');
            $table->foreignId('well_id')->constrained('wells')->onDelete('cascade');
            $table->string('village');
            $table->string('component', 100);
            $table->string('issue');
            $table->enum('severity', ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']);
            $table->integer('priority_hours');
            $table->boolean('resolved')->default(false);
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};