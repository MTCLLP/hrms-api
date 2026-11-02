<?php

namespace Modules\Localization\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Modules\Localization\Models\CalendarYear;

trait BelongsToCalendarYear
{
    protected static function bootBelongsToCalendarYear()
    {
        static::addGlobalScope('activeYear', function (Builder $builder) {
            $table = $builder->getModel()->getTable();
            if (Schema::hasColumn($table, 'calendar_year_id')) {
                $activeYear = CalendarYear::where('is_active', 1)->first();
                if ($activeYear) {
                    $builder->where($table . '.calendar_year_id', $activeYear->id);
                }
            }
        });

        static::creating(function ($model) {
            if (Schema::hasColumn($model->getTable(), 'calendar_year_id')) {
                if (empty($model->calendar_year_id)) {
                    $activeYear = CalendarYear::where('is_active', 1)->first();
                    if ($activeYear) {
                        $model->calendar_year_id = $activeYear->id;
                    }
                }
            }
        });
    }

    public function calendarYear()
    {
        return $this->belongsTo(CalendarYear::class);
    }
}
