<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorBusDailyAnalytic extends Model
{
    protected $fillable = [
        'bus_id',
        'operator_id',
        'date',
        'total_distance_km',
        'total_passengers',
        'avg_speed',
        'location_points',
        'last_lat',
        'last_lng',
        'started_at',
    ];
}
