<?php

namespace Modules\Employee\Models;

use Illuminate\Database\Eloquent\Model;

use Modules\RBAC\Models\User;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Branch;
use Modules\Employee\Models\EmployeeContact;
use Modules\Employee\Models\EmployeeExperience;
use Modules\Employee\Models\EmployeeAddress;
use Modules\Employee\Models\EmployeeEmail;
use Modules\Employee\Models\EmployeeDependant;
use Modules\Employee\Models\JobTitle;
use Modules\Leave\Models\LeaveEntitlement;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveBalance;
use Modules\Employee\Models\JobReporting;
use App\Scopes\CreatedByScope;

class Employee extends Model
{
    protected $fillable = [
        "department_id",
        "branch_id",//New
        "hire_date",
        "termination_date",
        "user_id",
        "dob",
        "gender",
        "jobTitle_id",
        "profile_image",
        "is_active",
        "is_trashed",
        "created_by"
        ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'department_id');
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'jobTitle_id');
    }

    public function contacts()
    {
        return $this->hasMany(EmployeeContact::class, 'employee_id');
    }

    public function experiences()
    {
        return $this->hasMany(EmployeeExperience::class);
    }

    public function addresses()
    {
        return $this->hasMany(EmployeeAddress::class);
    }

    public function emails()
    {
        return $this->hasMany(EmployeeEmail::class);
    }

    public function dependants()
    {
        return $this->hasMany(EmployeeDependant::class);
    }

    public function leaveEntitlement()
    {
        return $this->hasMany(LeaveEntitlement::class, 'employee_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id', 'id');
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class, 'employee_id', 'id');
    }

    public function subordinates()
    {
        return $this->hasMany(JobReporting::class, 'superior_id')
                    ->with('subordinateDetails'); // Eager load subordinate details
    }

    // Get superiors for the employee
    public function superiors()
    {
        return $this->hasMany(JobReporting::class, 'subordinate_id')
                    ->with('superiorDetails'); // Eager load superior details
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
