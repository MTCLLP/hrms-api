<?php

namespace Modules\Employee\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\RBAC\Models\User;
use Modules\Employee\Models\JobReporting;
use App\Scopes\CreatedByScope;

class ReportingMethod extends Model
{
    protected $fillable = ["method", "is_active", "is_trashed", "created_by"];

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
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

    public function jobReportings()
    {
        return $this->hasMany(JobReporting::class, 'reporting_method');
    }

}
