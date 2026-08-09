<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supervision extends Model
{
    use HasFactory;

   protected $fillable = [
        'kobo_id', 'well_id', 'well_code', 'supervisor_name', 'supervisor_username',
        'visit_date', 'submission_time', 'overall_status',
        'duration_minutes', 'week_number',
        'pump_working', 'pump_condition', 'inverter_working', 'water_flow'
    ];

    public function well()
    {
        return $this->belongsTo(Well::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}