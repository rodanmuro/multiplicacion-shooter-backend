<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calcular estadísticas de disparos
        $totalShots = $this->shots->count();
        $correctShots = $this->shots->where('is_correct', true)->count();
        $wrongShots = $this->shots->where('is_correct', false)->count();
        $accuracy = $this->calculateAccuracy($totalShots, $correctShots);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'final_score' => $this->final_score,
            'max_level_reached' => $this->max_level_reached,
            'duration_seconds' => $this->duration_seconds,
            'canvas_width' => $this->canvas_width,
            'canvas_height' => $this->canvas_height,
            // Estadísticas calculadas
            'total_shots' => $totalShots,
            'correct_shots' => $correctShots,
            'wrong_shots' => $wrongShots,
            'accuracy' => $accuracy,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Calcula la precisión en porcentaje
     *
     * @param int $total
     * @param int $correct
     * @return float
     */
    private function calculateAccuracy(int $total, int $correct): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($correct / $total) * 100, 2);
    }
}
