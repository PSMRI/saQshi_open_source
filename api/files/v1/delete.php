<?php

/**
 * delete.php
 * -------------------------------------------------------
 * Delete an uploaded file owned by the local uploads folder.
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';

Security::requireAnyMethod(['POST', 'DELETE']);

try {
    $facId = SessionManager::facilityId();
    $userId = SessionManager::userId();

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if ($userId <= 0) {
        Response::error('User session not found');
    }

    $request = Security::jsonInput();
    $url = trim((string)($request['url'] ?? $request['file_url'] ?? $request['path'] ?? ''));

    if ($url === '') {
        Response::validation([
            'url' => 'File URL is required'
        ]);
    }

    $path = parse_url($url, PHP_URL_PATH);
    $path = is_string($path) ? ltrim($path, '/\\') : '';

    if ($path === '' || strpos(str_replace('\\', '/', $path), 'uploads/') !== 0) {
        Response::validation([
            'url' => 'Only local uploaded files can be deleted'
        ]);
    }

    $rootPath = dirname(__DIR__, 3);
    $fullPath = realpath($rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    $uploadsPath = realpath($rootPath . DIRECTORY_SEPARATOR . 'uploads');

    if (!$fullPath || !$uploadsPath || strpos($fullPath, $uploadsPath) !== 0) {
        Response::validation([
            'url' => 'Uploaded file was not found'
        ]);
    }

    if (is_file($fullPath) && !unlink($fullPath)) {
        Response::serverError('Unable to delete uploaded file');
    }

    Response::success('File deleted successfully', [
        'url' => '/' . str_replace('\\', '/', $path)
    ]);

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
