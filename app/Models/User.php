<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;          // Add korun
use Tymon\JWTAuth\Contracts\JWTSubject;         // Add korun
use Modules\Core\app\Traits\HasUuid;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, HasUuid;

    // UUID setup
    public $incrementing = false; 
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',   // Module theke niye asha
        'avatar',  // Module theke niye asha
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    // JWT methods
    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }
}