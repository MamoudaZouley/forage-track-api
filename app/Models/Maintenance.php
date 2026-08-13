<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'kobo_id', 'well_id', 'well_code', 'village', 'region',
        'technician_username', 'team_leader_name', 'visit_date',
        'maintenance_type', 'request_source', 'work_performed',
        'work_description', 'parts_used', 'work_duration',
        'final_result', 'pump_condition_before', 'pump_condition_after',
        'water_flow_before', 'water_flow_after', 'needs_followup',
        'components_changed', 'qty_pump', 'qty_controller',
        'qty_solar_panel', 'qty_pipes', 'qty_taps', 'qty_tank', 'qty_other',
        'observations', 'submission_time',
    ];

    protected $casts = [
        'needs_followup' => 'boolean',
        'submission_time' => 'datetime',
    ];

    public function well()
    {
        return $this->belongsTo(Well::class);
    }
}