<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Leave\Models\LeaveRequest;
use Modules\Employee\Models\Employee;


class LeaveApproval extends Model
{

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['leaverequest_id','status','approver_id','start_date','end_date','total_days','isPaidLeave','remarks', "is_active", "is_trashed"];

    public function scopeOrdered($query, $value){
        return $query->orderBy('created_at', $value);
    }

	public function scopeActive($query, $value){
        return $query->where('is_active', $value);
    }

	public function scopeTrashed($query, $value){
        return $query->where('is_trashed', $value);
	}

    // Relationship to LeaveRequest
    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class, 'leaverequest_id', 'id');
    }

    // Relationship to Employee (Approver)
    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approver_id', 'id');
    }


}
