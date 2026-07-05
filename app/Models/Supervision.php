<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supervision extends Model
{
    use HasFactory;

    protected $fillable = [
        'well_id', 'supervisor_name', 'supervisor_username',
        'visit_date', 'submission_time', 'overall_status',
        'duration_minutes', 'week_number'
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