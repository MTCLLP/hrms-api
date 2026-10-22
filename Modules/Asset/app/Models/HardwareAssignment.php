<?php

namespace Modules\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Employee\Models\Employee;

class HardwareAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'hardware_asset_id',
        'employee_id',
        'assigned_at',
        'returned_at',
        'notes',
    ];

    public function hardwareAsset()
    {
        return $this->belongsTo(HardwareAsset::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
