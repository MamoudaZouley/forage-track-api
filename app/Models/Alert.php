<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'supervision_id', 'well_id', 'village', 'component',
        'issue', 'severity', 'priority_hours', 'resolved', 'resolved_at'
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function supervision()
    {
        return $this->belongsTo(Supervision::class);
    }

    public function well()
    {
        return $this->belongsTo(Well::class);
    }
}