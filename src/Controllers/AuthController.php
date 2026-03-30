<?php

declare(strict_types=1);

namespace App\Controllers;

use Firebase\JWT\JWT;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function __construct(private PDO $pdo) {}

    // ── Helpers ───────────────────────────────────────────────────

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    // ── POST /api/register ────────────────────────────────────────
    public function register(Request $request, Response $response): Response
    {
        $body     = (array) $request->getParsedBody();
        $username = trim($body['username'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$username || !$email || !$password) {
            return $this->json($response, ['success' => false, 'message' => 'All fields are required'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, ['success' => false, 'message' => 'Invalid email address'], 400);
        }

        if (strlen($password) < 6) {
            return $this->json($response, ['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                return $this->json($response, ['success' => false, 'message' => 'Username or email already exists'], 409);
            }

            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, email, password) VALUES (?, ?, ?)'
            );
            $stmt->execute([$username, $email, $hashed]);
            $insertId = (int) $this->pdo->lastInsertId();

            return $this->json($response, [
                'success' => true,
                'message' => 'Account created successfully.',
                'data'    => ['id' => $insertId, 'username' => $username, 'email' => $email],
            ], 201);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/login ───────────────────────────────────────────
    public function login(Request $request, Response $response): Response
    {
        $body     = (array) $request->getParsedBody();
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            return $this->json($response, ['success' => false, 'message' => 'Email and password are required'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                return $this->json($response, ['success' => false, 'message' => 'Invalid email or password'], 401);
            }

            $secret  = $_ENV['JWT_SECRET'] ?? '';
            $payload = [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'iat'      => time(),
                'exp'      => time() + 86400, // 24h
            ];
            $token = JWT::encode($payload, $secret, 'HS256');

            return $this->json($response, [
                'success' => true,
                'message' => 'Login successful',
                'data'    => [
                    'token' => $token,
                    'user'  => [
                        'id'       => $user['id'],
                        'username' => $user['username'],
                        'email'    => $user['email'],
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/logout ──────────────────────────────────────────
    public function logout(Request $request, Response $response): Response
    {
        $token = $request->getAttribute('token');

        try {
            $stmt = $this->pdo->prepare('INSERT INTO token_blacklist (token) VALUES (?)');
            $stmt->execute([$token]);

            return $this->json($response, ['success' => true, 'message' => 'Logged out successfully']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }
}
