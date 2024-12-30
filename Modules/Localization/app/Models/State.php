<?php

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\RBAC\Models\User;
use Modules\Organization\Models\Branch;

class State extends Model
{
    protected $fillable = [
        'name',
        'code',
        'iso_code',
        'is_active',
        'is_trashed',
        'country_id',
        'created_by'
    ];

    public function country(){
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function cities(){
        return $this->hasMany(City::class, 'state_id');
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
        return $this->hasMany(Branch::class);
    }

    public function companies()
    {
        return $this->hasMany(Company::class);
    }
}
