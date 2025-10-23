<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo User
 * Representa un usuario autenticado con Google OAuth
 */
class User extends Model
{
    use HasFactory;

    // Constantes de perfiles
    const PROFILE_STUDENT = 'student';
    const PROFILE_TEACHER = 'teacher';
    const PROFILE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'google_id',
        'email',
        'name',
        'lastname',
        'picture',
        'profile',
        'group'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relación: Un usuario tiene muchos logins
     */
    public function logins(): HasMany
    {
        return $this->hasMany(UserLogin::class);
    }

    /**
     * Relación: Un usuario tiene muchas sesiones de juego
     */
    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    /**
     * Verifica si el usuario es administrador
     */
    public function isAdmin(): bool
    {
        return $this->profile === self::PROFILE_ADMIN;
    }

    /**
     * Verifica si el usuario es profesor
     */
    public function isTeacher(): bool
    {
        return $this->profile === self::PROFILE_TEACHER;
    }

    /**
     * Verifica si el usuario es estudiante
     */
    public function isStudent(): bool
    {
        return $this->profile === self::PROFILE_STUDENT;
    }
}
