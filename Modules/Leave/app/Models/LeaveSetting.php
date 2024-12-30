<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Leave\Models\LeaveType;
use Modules\RBAC\Models\User;

use App\Scopes\CreatedByScope;

class LeaveSetting extends Model
{
    protected $fillable = ["leavetype_id","accrual_method","accrual_rate","maximum_accrual","allow_negative_bal","is_active", "is_trashed", "created_by"];

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leavetype_id');
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
