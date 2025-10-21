<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Shot;
use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class FinishSessionTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleAuth(string $sub): void
    {
        $this->mock(GoogleAuthService::class, function ($mock) use ($sub) {
            $mock->shouldReceive('verifyToken')->andReturn([
                'sub' => $sub,
                'email' => 'user@example.com',
                'name' => 'Test User',
                'picture' => null,
                'email_verified' => true,
            ]);
        });
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer test_token'];
    }

    private function makeUser(string $sub): User
    {
        return User::create([
            'google_id' => $sub,
            'email' => 'user@example.com',
            'name' => 'Test User',
            'picture' => null,
            'profile' => User::PROFILE_STUDENT,
        ]);
    }

    private function makeActiveSession(User $user): GameSession
    {
        return GameSession::create([
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(3),
            'finished_at' => null,
            'final_score' => 0,
            'max_level_reached' => 1,
            'duration_seconds' => 0,
            'canvas_width' => 1200,
            'canvas_height' => 800,
        ]);
    }

    public function test_can_finish_session_and_get_stats(): void
    {
        $user = $this->makeUser('finish-sub-1');
        $this->mockGoogleAuth('finish-sub-1');
        $session = $this->makeActiveSession($user);

        // Crear shots: 3 correctos, 2 incorrectos
        foreach ([true, true, true, false, false] as $idx => $isCorrect) {
            Shot::create([
                'game_session_id' => $session->id,
                'shot_at' => now()->addMilliseconds($idx * 100),
                'coordinate_x' => 100 + $idx,
                'coordinate_y' => 200 + $idx,
                'factor_1' => 2,
                'factor_2' => 3,
                'correct_answer' => 6,
                'card_value' => $isCorrect ? 6 : 5,
                'is_correct' => $isCorrect,
            ]);
        }

        $payload = [
            'finished_at' => now()->toISOString(),
            'final_score' => 120,
            'max_level_reached' => 4,
            'duration_seconds' => 180,
        ];

        $response = $this->putJson("/api/sessions/{$session->id}/finish", $payload, $this->authHeader());

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('success', true)
                ->where('data.id', $session->id)
                ->where('data.total_shots', 5)
                ->where('data.correct_shots', 3)
                ->where('data.wrong_shots', 2)
                ->where('data.accuracy', 60.0)
                ->etc()
            );
    }

    public function test_cannot_finish_already_finished_session(): void
    {
        $user = $this->makeUser('finish-sub-2');
        $this->mockGoogleAuth('finish-sub-2');
        $session = GameSession::create([
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinute(),
            'final_score' => 50,
            'max_level_reached' => 2,
            'duration_seconds' => 240,
            'canvas_width' => 1200,
            'canvas_height' => 800,
        ]);

        $payload = [
            'finished_at' => now()->toISOString(),
            'final_score' => 80,
            'max_level_reached' => 3,
            'duration_seconds' => 300,
        ];

        $response = $this->putJson("/api/sessions/{$session->id}/finish", $payload, $this->authHeader());
        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    public function test_cannot_finish_other_users_session(): void
    {
        $owner = $this->makeUser('finish-owner');
        $intruder = $this->makeUser('finish-intruder');
        $this->mockGoogleAuth('finish-intruder');

        $session = $this->makeActiveSession($owner);

        $payload = [
            'finished_at' => now()->toISOString(),
            'final_score' => 10,
            'max_level_reached' => 1,
            'duration_seconds' => 60,
        ];

        $response = $this->putJson("/api/sessions/{$session->id}/finish", $payload, $this->authHeader());
        $response->assertStatus(403)
                 ->assertJson(['success' => false]);
    }
}

