<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisions', function (Blueprint $table) {
            $table->integer('kobo_id')->unique()->nullable()->after('id');
            $table->string('well_code')->nullable()->after('well_id');
        });
    }

    public function down(): void
    {
        Schema::table('supervisions', function (Blueprint $table) {
            $table->dropColumn(['kobo_id', 'well_code']);
        });
    }
};