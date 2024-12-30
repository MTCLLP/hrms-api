<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;

use Modules\RBAC\Models\User;
use Modules\Leave\Models\LeaveSetting;
use Modules\Leave\Models\LeaveEntitlement;
use Modules\Leave\Models\LeaveRequest;

use App\Scopes\CreatedByScope;

class LeaveType extends Model
{
    protected $fillable = ["type_name","description","leave_count","is_active", "is_trashed", "created_by"];

    public function leaveSetting()
    {
        return $this->hasMany(LeaveSetting::class, 'leavetype_id');
    }

    public function leaveEntitlement()
    {
        return $this->hasMany(LeaveEntitlement::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'leavetype_id', 'id');
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
