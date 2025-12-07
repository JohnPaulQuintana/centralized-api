<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusPath extends Model
{
    use HasFactory;
    protected $fillable = ['bus_id','latitude','longitude','speed','passenger_count'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed' => 'float',
        'passenger_count' => 'integer'
    ];

    // Relationship with bus
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }
}
