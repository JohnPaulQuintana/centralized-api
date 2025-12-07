<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusStop extends Model
{
    use HasFactory;

    protected $fillable = ['name','latitude','longitude'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float'
    ];
    
}
