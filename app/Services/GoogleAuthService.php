<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Exception;

/**
 * Servicio para verificar tokens de Google OAuth
 */
class GoogleAuthService
{
    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient([
            'client_id' => config('services.google.client_id')
        ]);
    }

    /**
     * Verifica un token JWT de Google y retorna los datos del usuario
     *
     * @param string $token JWT token de Google
     * @return array Datos del usuario decodificados del token
     * @throws Exception Si el token es inválido
     */
    public function verifyToken(string $token): array
    {
        try {
            // Verificar el token con Google
            $payload = $this->client->verifyIdToken($token);

            if (!$payload) {
                throw new Exception('Token inválido o expirado');
            }

            // Retornar datos del usuario
            return [
                'sub' => $payload['sub'],           // Google ID
                'email' => $payload['email'],
                'name' => $payload['name'],
                'picture' => $payload['picture'] ?? null,
                'given_name' => $payload['given_name'] ?? null,
                'family_name' => $payload['family_name'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
            ];

        } catch (Exception $e) {
            throw new Exception('Error al verificar token de Google: ' . $e->getMessage());
        }
    }
}
