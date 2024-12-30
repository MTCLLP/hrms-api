<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;

use Modules\RBAC\Models\User;
use Modules\Leave\Models\LeaveType;
use Modules\Employee\Models\Employee;

use App\Scopes\CreatedByScope;


class LeaveEntitlement extends Model
{
    protected $fillable = ["leavetype_id","employee_id","ent_amount","ent_start_date","ent_end_date","is_active", "is_trashed", "created_by"];

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leavetype_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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
