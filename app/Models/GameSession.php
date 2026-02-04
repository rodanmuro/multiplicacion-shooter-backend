<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo GameSession
 * Representa una sesión de juego de 5 minutos
 */
class GameSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'group_snapshot',
        'started_at',
        'finished_at',
        'final_score',
        'max_level_reached',
        'duration_seconds',
        'canvas_width',
        'canvas_height',
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
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relación: Una sesión pertenece a un usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Una sesión tiene muchos disparos
     */
    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class);
    }

    /**
     * Verifica si la sesión está activa (no ha terminado)
     */
    public function isActive(): bool
    {
        return is_null($this->finished_at);
    }
}
