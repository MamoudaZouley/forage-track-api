<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wells', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('village');
            $table->string('region', 100);
            $table->string('department', 100);
            $table->string('commune', 100);
            $table->enum('status', ['operational', 'not_working', 'suspended'])
                  ->default('operational');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wells');
    }
};