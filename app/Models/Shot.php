<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Shot
 * Registra cada disparo que impacta un card durante una sesión
 */
class Shot extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'game_session_id',
        'shot_at',
        'coordinate_x',
        'coordinate_y',
        'factor_1',
        'factor_2',
        'correct_answer',
        'card_value',
        'is_correct',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shot_at' => 'datetime',
            'coordinate_x' => 'float',
            'coordinate_y' => 'float',
            'is_correct' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relación: Un disparo pertenece a una sesión de juego
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }
}

