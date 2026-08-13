<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('components_changed')->nullable()->after('work_performed');
            $table->integer('qty_pump')->nullable()->after('components_changed');
            $table->integer('qty_controller')->nullable()->after('qty_pump');
            $table->integer('qty_solar_panel')->nullable()->after('qty_controller');
            $table->integer('qty_pipes')->nullable()->after('qty_solar_panel');
            $table->integer('qty_taps')->nullable()->after('qty_pipes');
            $table->integer('qty_tank')->nullable()->after('qty_taps');
            $table->integer('qty_other')->nullable()->after('qty_tank');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn([
                'components_changed', 'qty_pump', 'qty_controller',
                'qty_solar_panel', 'qty_pipes', 'qty_taps', 'qty_tank', 'qty_other'
            ]);
        });
    }
};