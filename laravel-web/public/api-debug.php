<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'GymXBook API Tester ready',
    'tester' => [
        'web_page' => 'https://web.gymxbook.com/api-tester',
        'alternative' => 'https://web.gymxbook.com/test-api',
    ],
    'api_base' => 'https://web.gymxbook.com/api/v1',
    'important_endpoints' => [
        'dashboard' => '/dashboard',
        'me' => '/me',
        'members' => '/members',
    ],
    'timestamp' => date('c'),
], JSON_PRETTY_PRINT);
