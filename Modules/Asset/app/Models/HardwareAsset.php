<?php

namespace Modules\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class HardwareAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'system_name',
        'type',
        'serial_number',
        'mac_address',
        'status',
    ];
}
