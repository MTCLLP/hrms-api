<?php

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;

use Modules\RBAC\Entities\User;
use Modules\HR\Entities\Organization\Branch;
use Modules\Company\Entities\Company;

use App\Scopes\CreatedByScope;

class Country extends Model
{
    protected $fillable = [
        'name',
        'code',
        'iso3_code',
        'numeric_code',
        'is_active',
        'is_trashed',
        'created_by'
    ];

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function states() {
        return $this->hasMany(State::class, 'country_id');
    }

    public function cities() {
        return $this->hasMany(City::class, 'country_id');
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
