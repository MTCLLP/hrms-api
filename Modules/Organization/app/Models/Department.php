<?php

namespace Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Employee\Models\Employee;
use Modules\RBAC\Models\User;
use App\Scopes\CreatedByScope;
use Modules\Organization\Models\Branch;

class Department extends Model
{
    protected $fillable = ["name", "branch_id", "is_active", "is_trashed", "created_by"];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function jobOpenings()
    {
        return $this->hasMany(JobOpening::class, 'department_id');
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

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_departments', 'department_id', 'branch_id');
    }
}