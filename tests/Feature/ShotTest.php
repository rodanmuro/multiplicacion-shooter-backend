<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Shot;
use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ShotTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleAuth(string $sub): void
    {
        // Mockear verificación de token para evitar llamadas externas
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

    private function makeUser(string $sub = 'google-sub-1'): User
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
            'started_at' => now(),
            'finished_at' => null,
            'final_score' => 0,
            'max_level_reached' => 1,
            'duration_seconds' => 0,
        ]);
    }

    public function test_user_can_register_correct_shot(): void
    {
        $user = $this->makeUser('sub-123');
        $this->mockGoogleAuth('sub-123');
        $session = $this->makeActiveSession($user);

        $payload = [
            'shot_at' => now()->toISOString(),
            'coordinate_x' => 400.25,
            'coordinate_y' => 300.75,
            'factor_1' => 7,
            'factor_2' => 6,
            'correct_answer' => 42,
            'card_value' => 42,
            'is_correct' => true,
        ];

        $response = $this->postJson("/api/sessions/{$session->id}/shots", $payload, $this->authHeader());

        $response->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('success', true)
                ->has('data.id')
                ->where('data.game_session_id', $session->id)
                ->where('data.is_correct', true)
                ->etc()
            );

        $this->assertDatabaseHas('shots', [
            'game_session_id' => $session->id,
            'factor_1' => 7,
            'factor_2' => 6,
            'correct_answer' => 42,
            'card_value' => 42,
            'is_correct' => true,
        ]);
    }

    public function test_user_can_register_incorrect_shot(): void
    {
        $user = $this->makeUser('sub-234');
        $this->mockGoogleAuth('sub-234');
        $session = $this->makeActiveSession($user);

        $payload = [
            'shot_at' => now()->toISOString(),
            'coordinate_x' => 200.0,
            'coordinate_y' => 150.0,
            'factor_1' => 5,
            'factor_2' => 9,
            'correct_answer' => 45,
            'card_value' => 40,
            'is_correct' => false,
        ];

        $response = $this->postJson("/api/sessions/{$session->id}/shots", $payload, $this->authHeader());
        $response->assertCreated();

        $this->assertDatabaseHas('shots', [
            'game_session_id' => $session->id,
            'factor_1' => 5,
            'factor_2' => 9,
            'correct_answer' => 45,
            'card_value' => 40,
            'is_correct' => false,
        ]);
    }

    public function test_cannot_register_shot_in_finished_session(): void
    {
        $user = $this->makeUser('sub-345');
        $this->mockGoogleAuth('sub-345');

        $session = GameSession::create([
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
            'final_score' => 10,
            'max_level_reached' => 2,
            'duration_seconds' => 300,
        ]);

        $payload = [
            'shot_at' => now()->toISOString(),
            'coordinate_x' => 100.0,
            'coordinate_y' => 100.0,
            'factor_1' => 3,
            'factor_2' => 3,
            'correct_answer' => 9,
            'card_value' => 9,
            'is_correct' => true,
        ];

        $response = $this->postJson("/api/sessions/{$session->id}/shots", $payload, $this->authHeader());
        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    public function test_cannot_register_shot_in_other_users_session(): void
    {
        $owner = $this->makeUser('sub-owner');
        $intruder = $this->makeUser('sub-intruder');

        // Autenticar como intruso
        $this->mockGoogleAuth('sub-intruder');

        $session = $this->makeActiveSession($owner);

        $payload = [
            'shot_at' => now()->toISOString(),
            'coordinate_x' => 250.0,
            'coordinate_y' => 250.0,
            'factor_1' => 2,
            'factor_2' => 8,
            'correct_answer' => 16,
            'card_value' => 16,
            'is_correct' => true,
        ];

        $response = $this->postJson("/api/sessions/{$session->id}/shots", $payload, $this->authHeader());
        $response->assertStatus(403)
                 ->assertJson(['success' => false]);
    }

    public function test_session_not_found_returns_404(): void
    {
        $user = $this->makeUser('sub-404');
        $this->mockGoogleAuth('sub-404');

        $nonExistingSessionId = 999999;

        $payload = [
            'shot_at' => now()->toISOString(),
            'coordinate_x' => 500.0,
            'coordinate_y' => 400.0,
            'factor_1' => 4,
            'factor_2' => 8,
            'correct_answer' => 32,
            'card_value' => 32,
            'is_correct' => true,
        ];

        $response = $this->postJson("/api/sessions/{$nonExistingSessionId}/shots", $payload, $this->authHeader());
        $response->assertStatus(404)
                 ->assertJson(['success' => false]);
    }

    public function test_validation_errors_return_422(): void
    {
        $user = $this->makeUser('sub-422');
        $this->mockGoogleAuth('sub-422');
        $session = $this->makeActiveSession($user);

        // Payload con varios errores de validación
        $invalidPayload = [
            // 'shot_at' faltante para forzar required
            'coordinate_x' => -10,      // fuera de rango
            'coordinate_y' => 900,      // fuera de rango
            'factor_1' => 0,            // fuera de rango (min 1)
            'factor_2' => 13,           // fuera de rango (max 12)
            'correct_answer' => -1,     // fuera de rango (min 0)
            'card_value' => 1000,       // válido por sí, pero combinamos errores suficientes
            // 'is_correct' faltante para forzar required
        ];

        $response = $this->postJson("/api/sessions/{$session->id}/shots", $invalidPayload, $this->authHeader());

        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors']);

        // Asegurar que no se creó ningún registro de shot
        $this->assertDatabaseCount('shots', 0);
    }
}
