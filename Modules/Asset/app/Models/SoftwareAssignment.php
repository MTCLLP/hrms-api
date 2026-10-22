<?php

namespace Modules\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Employee\Models\Employee;

class SoftwareAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'software_license_id',
        'employee_id',
        'hardware_asset_id',
        'assigned_at',
        'returned_at',
        'notes',
    ];

    public function softwareLicense()
    {
        return $this->belongsTo(SoftwareLicense::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function hardwareAsset()
    {
        return $this->belongsTo(HardwareAsset::class);
    }
}
