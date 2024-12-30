<?php

namespace Modules\RBAC\Models;
use Illuminate\Database\Eloquent\Model;
use Modules\RBAC\Models\User;


class Otp extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'otp_type',
        'otp',
        'expires_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

}
