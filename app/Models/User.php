<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_CANDIDAT = 'Candidat';
    public const ROLE_SYSTEME = 'Système';
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_COMMISSION = 'Commission';
    public const ROLE_PRESIDENT = 'Président';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }

    public function candidature(): HasOne
    {
        return $this->hasOne(Candidature::class);
    }

    public function commissionAssignments(): HasMany
    {
        return $this->hasMany(CommissionUser::class);
    }

    public function isCandidat(): bool
    {
        return $this->role === self::ROLE_CANDIDAT;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
