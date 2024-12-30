<?php

namespace Modules\Employee\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\RBAC\Models\User;
use Modules\Employee\Models\ReportingMethod;
use Modules\Employee\Models\Employee;
use App\Scopes\CreatedByScope;

class JobReporting extends Model
{
    protected $fillable = ["superior_id", "subordinate_id", "reporting_method_id", "is_active", "is_trashed", "created_by"];

        // Get superior details
    public function superiorDetails()
    {
        return $this->belongsTo(Employee::class, 'superior_id');
    }

    // Get subordinate details
    public function subordinateDetails()
    {
        return $this->belongsTo(Employee::class, 'subordinate_id');
    }

        public function reportingMethod()
        {
            return $this->belongsTo(ReportingMethod::class, 'reporting_method_id', 'id');
        }

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



}
