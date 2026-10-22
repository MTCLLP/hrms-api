<?php

namespace Modules\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftwareLicense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'license_key',
        'total_seats',
        'expiry_date',
    ];
}
