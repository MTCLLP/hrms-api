<?php

namespace Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\RBAC\Models\User;
use App\Scopes\CreatedByScope;
use Modules\Organization\Models\Department;
use Modules\Project\Models\Enquiry;
use Modules\Localization\Models\City;
use Modules\Localization\Models\State;
use Modules\Localization\Models\Country;
use Modules\Organization\Models\Employee;

class Branch extends Model
{

    protected $fillable = ["name","address","contact_no","city_id", "state_id", "country_id","is_active", "is_trashed", "created_by"];

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

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'branch_departments', 'branch_id', 'department_id');
    }

    public function enquiry()
    {
        return $this->hasOne(Enquiry::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }

}
