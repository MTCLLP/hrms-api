<?php

namespace Modules\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles; // laravel spatie permission
use Illuminate\Support\Facades\Hash; // laravel password hashing
use Laravel\Sanctum\HasApiTokens; // laravel sanctum
use Modules\Employee\Models\Employee;

class User extends Authenticatable
{
    use Notifiable;
    use HasRoles; // laravel spatie permission
    use HasApiTokens; // laravel passport

    protected $guard_name = 'api';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'mobile', 'password'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Mutator function to encrypt all password fields
     */
    public function setPasswordAttribute($password)
    {
        if (!empty($password)) {
            // Only hash if not already hashed
            if (!Hash::needsRehash($password)) {
                $this->attributes['password'] = Hash::make($password);
            } else {
                $this->attributes['password'] = $password;
            }
        }
    }
    //Accessor function for full name
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
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

    //Additional attribute to be appended
    protected $appends = ['full_name'];

    public function profile()
    {
        return $this->hasOne(Profile::class,'user_id');
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

}
