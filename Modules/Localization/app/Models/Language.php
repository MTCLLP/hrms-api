<?php

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;


use Modules\RBAC\Models\User;

use App\Scopes\CreatedByScope;

class Language extends Model
{


    protected $fillable = [
		'name',
		'code',
		'is_active',
		'is_trashed',
		'created_by'
	];

	public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cuisine(){
        return $this->belongsToMany(Cuisine::class,'cuisine_language');
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
