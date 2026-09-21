<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in web server, used by the transport tests.
 *
 * Routes:
 *   /echo          Reports method, headers, query and the body as JSON.
 *   /form          Parses a multipart/form-data body with PHP's own parser and
 *                  reports the fields and files as JSON.
 *   /status/{code} Responds with the given status and an UptimeRobot error body.
 *   /sleep/{ms}    Waits before responding.
 */

$path = parse_url(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$segments = explode('/', trim($path, '/'));
$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : '';

$headers = [];

foreach ($_SERVER as $key => $value) {
    if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    }
}

if (is_string($_SERVER['CONTENT_TYPE'] ?? null)) {
    $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
}

if (is_string($_SERVER['CONTENT_LENGTH'] ?? null)) {
    $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
}

switch ($segments[0]) {
    case 'echo':
        $body = (string) file_get_contents('php://input');
        header('Content-Type: application/json');
        header('X-Custom: one');
        header('X-Custom: two', false);
        header('X-RateLimit-Limit: 20');
        header('X-RateLimit-Remaining: 19');
        header('X-RateLimit-Reset: 60');
        echo json_encode([
            'method' => $method,
            'query' => $_SERVER['QUERY_STRING'] ?? '',
            'headers' => $headers,
            'bodyLength' => strlen($body),
            'body' => strlen($body) <= 1024 ? $body : null,
        ]);
        break;

    case 'form':
        // PHP parses forms into $_POST and $_FILES for POST only; any other
        // method needs request_parse_body() (PHP 8.4+).
        [$fields, $files] = $method === 'POST' ? [$_POST, $_FILES] : request_parse_body();
        $received = [];

        foreach ($files as $name => $file) {
            if (is_array($file) && is_string($file['tmp_name'] ?? null)) {
                $received[$name] = [
                    'name' => $file['name'] ?? null,
                    'type' => $file['type'] ?? null,
                    'size' => $file['size'] ?? null,
                    'sha256' => hash_file('sha256', $file['tmp_name']),
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['method' => $method, 'fields' => $fields, 'files' => $received]);
        break;

    case 'status':
        $code = (int) ($segments[1] ?? 500);
        http_response_code($code);
        header('Content-Type: application/json');
        header('X-RateLimit-Limit: 20');
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: 60');

        if ($code === 429) {
            header('Retry-After: 2');
        }

        echo json_encode(['message' => "Status {$code}", 'code' => '000-004']);
        break;

    case 'sleep':
        usleep(((int) ($segments[1] ?? 0)) * 1000);
        echo 'late';
        break;

    default:
        http_response_code(404);
        echo 'not found';
}
