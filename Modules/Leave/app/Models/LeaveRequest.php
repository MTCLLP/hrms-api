<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveApproval;
use App\Scopes\CreatedByScope;

class LeaveRequest extends Model
{
    protected $fillable = ["employee_id","start_date","end_date","leavetype_id","is_half_day","leave_description","status","comments","is_active", "is_trashed", "created_by"];


    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leavetype_id', 'id');
    }

    public function leaveBalance()
    {
        return $this->hasMany(LeaveBalance::class, 'employee_id', 'employee_id')
                    ->whereColumn('leavetype_id', 'leavetype_id');
    }

    public function leaveApprovals()
    {
        return $this->hasMany(LeaveApproval::class, 'leaverequest_id', 'id');
    }


    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supervisedBy() {
        return $this->belongsTo(User::class, 'supervised_by');
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
