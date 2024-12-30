<?php

namespace Modules\Employee\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use App\Scopes\CreatedByScope;

class EmployeeExperience extends Model
{
    protected $fillable = ["employee_id","company_name","designation","start_date","end_date","is_active", "is_trashed", "created_by"];

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
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
