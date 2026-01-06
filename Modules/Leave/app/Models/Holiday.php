<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Modules\RBAC\Models\User;
use App\Scopes\CreatedByScope;
use Modules\Localization\Models\CalendarYear;
use Modules\Localization\Traits\BelongsToCalendarYear;

class Holiday extends Model
{
    use BelongsToCalendarYear;
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ["name", "date", "description", "is_active", "is_trashed", "created_by"];

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function calendarYear() {
        return $this->belongsTo(CalendarYear::class, 'calendar_year_id');
    }

	public function scopeOrdered($query, $value){
        return $query->orderBy('created_at', $value);
    }

	public function scopeActive($query, $value){
        return $query->where('is_active', $value);
    }

	public function scopeTrashed($query, $value){
        return $query->where('is_trashed', $value);
	}
}
