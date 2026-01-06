<?php

namespace Modules\Localization\Traits;

use Illuminate\Database\Eloquent\Builder;
use Modules\Localization\Models\CalendarYear;
trait BelongsToCalendarYear
{
    protected static function bootBelongsToCalendarYear()
    {
        static::addGlobalScope('activeYear', function (Builder $builder) {
            $builder->where('calendar_year_id', CalendarYear::active()->id);
        });

        static::creating(function ($model) {
            if (empty($model->calendar_year_id)) {
                $model->calendar_year_id = CalendarYear::active()->id;
            }
        });
    }

    public function calendarYear()
    {
        return $this->belongsTo(CalendarYear::class);
    }
}
