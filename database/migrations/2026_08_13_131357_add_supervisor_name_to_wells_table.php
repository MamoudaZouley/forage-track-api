<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wells', function (Blueprint $table) {
            $table->string('supervisor_name')->nullable()->after('supervisor');
        });
    }

    public function down(): void
    {
        Schema::table('wells', function (Blueprint $table) {
            $table->dropColumn('supervisor_name');
        });
    }
};