<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TrainController
{
    public function __construct(private PDO $pdo) {}

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Slim's getParsedBody() only auto-parses POST requests.
     * For PUT with multipart/form-data or application/x-www-form-urlencoded,
     * we need to read the raw body ourselves.
     */
    private function getBody(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (!empty($parsed) && is_array($parsed)) {
            return $parsed;
        }

        $raw = (string) $request->getBody();
        if (!empty($raw)) {
            parse_str($raw, $data);
            if (!empty($data)) {
                return $data;
            }
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }

        return [];
    }

    // ── GET /api/trains ───────────────────────────────────────────
    public function index(Request $request, Response $response): Response
    {
        try {
            $stmt   = $this->pdo->query('SELECT * FROM trains ORDER BY id DESC');
            $trains = $stmt->fetchAll();

            return $this->json($response, [
                'success' => true,
                'count'   => count($trains),
                'data'    => $trains,
            ]);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── GET /api/trains/{id} ──────────────────────────────────────
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM trains WHERE id = ?');
            $stmt->execute([$args['id']]);
            $train = $stmt->fetch();

            if (!$train) {
                return $this->json($response, ['success' => false, 'message' => 'Train not found'], 404);
            }

            return $this->json($response, ['success' => true, 'data' => $train]);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/trains ──────────────────────────────────────────
    public function store(Request $request, Response $response): Response
    {
        $body      = (array) $request->getParsedBody();
        $trainName = trim($body['train_name'] ?? '');
        $price     = $body['price'] ?? '';
        $route     = trim($body['route'] ?? '');

        if (!$trainName || $price === '' || !$route) {
            return $this->json($response, [
                'success' => false,
                'message' => 'train_name, price, and route are required',
            ], 400);
        }

        if (!is_numeric($price) || (float) $price < 0) {
            return $this->json($response, ['success' => false, 'message' => 'price must be a valid non-negative number'], 400);
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO trains (train_name, price, route) VALUES (?, ?, ?)'
            );
            $stmt->execute([$trainName, $price, $route]);
            $insertId = (int) $this->pdo->lastInsertId();

            return $this->json($response, [
                'success' => true,
                'message' => 'Train created',
                'data'    => [
                    'id'         => $insertId,
                    'train_name' => $trainName,
                    'price'      => $price,
                    'route'      => $route,
                ],
            ], 201);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── PUT /api/trains/{id} ──────────────────────────────────────
    public function update(Request $request, Response $response, array $args): Response
    {
        $body      = $this->getBody($request);
        $trainName = trim($body['train_name'] ?? '');
        $price     = $body['price'] ?? '';
        $route     = trim($body['route'] ?? '');

        if (!$trainName || $price === '' || !$route) {
            return $this->json($response, ['success' => false, 'message' => 'train_name, price, and route are required'], 400);
        }

        if (!is_numeric($price) || (float) $price < 0) {
            return $this->json($response, ['success' => false, 'message' => 'price must be a valid non-negative number'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id FROM trains WHERE id = ?');
            $stmt->execute([$args['id']]);
            if (!$stmt->fetch()) {
                return $this->json($response, ['success' => false, 'message' => 'Train not found'], 404);
            }

            $stmt2 = $this->pdo->prepare(
                'UPDATE trains SET train_name = ?, price = ?, route = ? WHERE id = ?'
            );
            $stmt2->execute([$trainName, $price, $route, $args['id']]);

            return $this->json($response, [
                'success' => true,
                'message' => 'Train updated',
                'data'    => [
                    'id'         => (int) $args['id'],
                    'train_name' => $trainName,
                    'price'      => $price,
                    'route'      => $route,
                ],
            ]);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── DELETE /api/trains/{id} ───────────────────────────────────
    public function destroy(Request $request, Response $response, array $args): Response
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM trains WHERE id = ?');
            $stmt->execute([$args['id']]);
            if (!$stmt->fetch()) {
                return $this->json($response, ['success' => false, 'message' => 'Train not found'], 404);
            }

            $stmt2 = $this->pdo->prepare('DELETE FROM trains WHERE id = ?');
            $stmt2->execute([$args['id']]);

            return $this->json($response, ['success' => true, 'message' => 'Train deleted']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }
}
