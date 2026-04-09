<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/ai_assistant.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);
$message = trim((string) ($payload['message'] ?? ''));

if ($message === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Message is required.']);
    exit;
}

$response = ai_assistant_help_reply($conn, current_user(), $message);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>