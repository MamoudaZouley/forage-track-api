<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisions', function (Blueprint $table) {
            $table->decimal('duration_minutes', 8, 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('supervisions', function (Blueprint $table) {
            $table->decimal('duration_minutes', 5, 1)->nullable()->change();
        });
    }
};