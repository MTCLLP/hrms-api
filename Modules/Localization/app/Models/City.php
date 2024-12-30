<?php

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;

use Modules\RBAC\Models\User;
use Modules\Organization\Models\Branch;

class City extends Model
{
    protected $fillable = [
        'name',
        'state_id',
        'country_id',
        'is_active',
        'is_trashed',
        'created_by'
    ];

    public function country(){
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state(){
        return $this->belongsTo(State::class, 'state_id');
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

    public function enquiryRateList()
    {
        return $this->hasOne(EnquiryRateList::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function enquiries()
    {
        return $this->hasMany(Enquiry::class, 'location_id');
    }
}
