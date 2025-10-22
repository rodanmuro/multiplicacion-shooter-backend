<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_session_id' => $this->game_session_id,
            'shot_at' => $this->shot_at?->toIso8601String(),
            'coordinate_x' => $this->coordinate_x,
            'coordinate_y' => $this->coordinate_y,
            'factor_1' => $this->factor_1,
            'factor_2' => $this->factor_2,
            'correct_answer' => $this->correct_answer,
            'card_value' => $this->card_value,
            'is_correct' => $this->is_correct,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
