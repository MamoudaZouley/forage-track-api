<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supervisions', function (Blueprint $table) {
            $table->decimal('meter_reading', 10, 2)->nullable()->after('water_flow');
            $table->decimal('weekly_consumption', 10, 2)->nullable()->after('meter_reading');
        });
    }

    public function down(): void
    {
        Schema::table('supervisions', function (Blueprint $table) {
            $table->dropColumn(['meter_reading', 'weekly_consumption']);
        });
    }
    
};
