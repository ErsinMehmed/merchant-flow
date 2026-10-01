<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST'),
        getenv('DB_PORT'),
        getenv('DB_DATABASE'),
    );

    $connection = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    $connection->query('SELECT 1');

    echo json_encode(['status' => 'ok', 'service' => 'php']);
} catch (Throwable $exception) {
    error_log('PHP database health check failed.');
    http_response_code(503);
    echo json_encode(['status' => 'unavailable', 'service' => 'php']);

    if (PHP_SAPI === 'cli') {
        exit(1);
    }
}
