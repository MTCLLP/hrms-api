<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;

use Modules\RBAC\Models\User;
use Modules\Employee\Models\Employee;
use Modules\Leave\Models\LeaveType;

use App\Scopes\CreatedByScope;

class LeaveBalance extends Model
{

    protected $fillable = ["leavetype_id","employee_id","balance_amount","is_active", "is_trashed", "created_by"];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leavetype_id', 'id');
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
