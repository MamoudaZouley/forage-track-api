<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wells', function (Blueprint $table) {
            $table->string('supervisor')->nullable()->after('status');
            $table->string('zone')->nullable()->after('supervisor');
            $table->string('department')->nullable()->change();
            $table->string('commune')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wells', function (Blueprint $table) {
            $table->dropColumn(['supervisor', 'zone']);
        });
    }
};