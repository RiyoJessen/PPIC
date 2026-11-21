<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Emulate Apache "mod_rewrite" functionality from the built-in PHP web server
$publicPath = realpath(__DIR__ . '/public');
$requestedFile = ($publicPath !== false && $uri !== '/') ? realpath($publicPath . $uri) : false;

if (
    $requestedFile !== false &&
    is_file($requestedFile) &&
    str_starts_with($requestedFile, $publicPath . DIRECTORY_SEPARATOR) &&
    strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION)) !== 'php'
) {
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($docRoot === $publicPath) {
        return false;
    }

    $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'json'  => 'application/json; charset=UTF-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'map'   => 'application/json',
    ];

    $contentType = $mimeTypes[$ext] ?? (function_exists('mime_content_type') ? (mime_content_type($requestedFile) ?: 'application/octet-stream') : 'application/octet-stream');
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($requestedFile));
    readfile($requestedFile);
    return true;
}

require_once __DIR__ . '/public/index.php';
