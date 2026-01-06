<?php

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Leave\Models\Holiday;

class CalendarYear extends Model
{


    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['year','is_active', 'is_trashed'];

    public static function active()
    {
        return static::where('is_active', 1)->firstOrFail();
    }

    public function holidays() {
        return $this->hasMany(Holiday::class, 'calendar_year_id', 'id');
    }

    // protected static function newFactory(): CalendarYearFactory
    // {
    //     // return CalendarYearFactory::new();
    // }
}
