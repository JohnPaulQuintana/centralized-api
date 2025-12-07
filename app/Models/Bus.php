<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bus extends Model
{
    use HasFactory;
    protected $fillable = ["bus_name","driver_name","license_plate","is_active"];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Relationship with bus paths
    public function paths(): HasMany
    {
        return $this->hasMany(BusPath::class);
    }

    // Get latest position
    public function getCurrentPositionAttribute()
    {
        return $this->paths()->latest()->first();
    }

    // Get path travelled (last 50 points)
    public function getPathTravelledAttribute()
    {
        return $this->paths()
            ->orderBy('created_at', 'asc')
            // ->limit(50)
            ->get(['latitude', 'longitude', 'speed', 'passenger_count']);
    }
}
