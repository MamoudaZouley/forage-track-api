<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisions', function (Blueprint $table) {
            $table->string('pump_working')->nullable()->after('overall_status');
            $table->string('pump_condition')->nullable()->after('pump_working');
            $table->string('inverter_working')->nullable()->after('pump_condition');
            $table->string('water_flow')->nullable()->after('inverter_working');
        });
    }

    public function down(): void
    {
        Schema::table('supervisions', function (Blueprint $table) {
            $table->dropColumn(['pump_working', 'pump_condition', 'inverter_working', 'water_flow']);
        });
    }
};