<?php
// ============================================================
//  responsegenerator.php — Shared JSON response helpers
// ============================================================

function jsonResponse(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function ok(array $body): void       { jsonResponse(200, $body); }
function created(array $body): void  { jsonResponse(201, $body); }

function badRequest(string $msg): void  { jsonResponse(400, ['message' => $msg]); }
function unauthorized(string $msg = 'Unauthorized.'): void { jsonResponse(401, ['message' => $msg]); }
function forbidden(string $msg = 'Forbidden.'): void       { jsonResponse(403, ['message' => $msg]); }
function serverError(string $msg = 'Internal server error.'): void { jsonResponse(500, ['message' => $msg]); }

function setCorsHeaders(): void
{
    // FIX: wildcard origin + credentials is invalid per CORS spec — browsers reject it.
    // Reflect the actual request origin for credentialed requests.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    header('Vary: Origin');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
