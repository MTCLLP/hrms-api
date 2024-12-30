<?php

namespace Modules\Employee\Models;

use Illuminate\Database\Eloquent\Model;

use Modules\Employee\Models\Organization\Employee;
use Modules\Employee\Models\JobOpening;
use Modules\RBAC\Models\User;
use App\Scopes\CreatedByScope;

class JobTitle extends Model
{
    protected $fillable = ["name","is_active", "is_trashed", "created_by"];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'jobTitle_id');
    }

    public function jobOpenings()
    {
        return $this->hasMany(JobOpening::class, 'job_title_id');
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
