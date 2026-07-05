<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Well extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'village', 'region',
        'department', 'commune', 'status'
    ];

    public function supervisions()
    {
        return $this->hasMany(Supervision::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}