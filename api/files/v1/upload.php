<?php

/**
 * upload.php
 * -------------------------------------------------------
 * Authenticated file upload endpoint.
 *
 * Used for:
 * - assessment evidence
 * - gap closure evidence
 * - supporting documents
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';

Security::requireMethod('POST');

try {
    $facId = SessionManager::facilityId();
    $userId = SessionManager::userId();

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if ($userId <= 0) {
        Response::error('User session not found');
    }

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        Response::validation([
            'file' => 'File is required'
        ]);
    }

    $file = $_FILES['file'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        Response::validation([
            'file' => 'File upload failed'
        ]);
    }

    $maxSize = 10 * 1024 * 1024;

    if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxSize) {
        Response::validation([
            'file' => 'File size must be between 1 byte and 10 MB'
        ]);
    }

    $originalName = (string)($file['name'] ?? 'upload');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'webp',
        'pdf',
        'doc', 'docx',
        'xls', 'xlsx', 'csv'
    ];

    if (!in_array($extension, $allowedExtensions, true)) {
        Response::validation([
            'file' => 'Unsupported file type'
        ]);
    }

    $mimeType = '';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = (string)finfo_file($finfo, $file['tmp_name']);
        }
    }

    if ($mimeType === '') {
        $mimeType = (string)($file['type'] ?? '');
    }

    $mimeMap = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel']
    ];

    $mimeAllowed = in_array($mimeType, $mimeMap[$extension] ?? [], true);

    if (!$mimeAllowed) {
        Response::validation([
            'file' => 'Unsupported file content type'
        ]);
    }

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) && @getimagesize($file['tmp_name']) === false) {
        Response::validation([
            'file' => 'Uploaded image content is invalid'
        ]);
    }

    $category = preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '',
        (string)($_POST['category'] ?? 'general')
    );

    if ($category === '') {
        $category = 'general';
    }

    $rootPath = dirname(__DIR__, 3);
    $relativeDir = 'uploads/assessment/' . $category . '/' . date('Y/m');
    $targetDir = $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
        Response::serverError('Unable to create upload folder');
    }

    $safeBaseName = preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '_',
        pathinfo($originalName, PATHINFO_FILENAME)
    );

    $safeBaseName = trim($safeBaseName, '_') ?: 'evidence';
    $storedName = $safeBaseName . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        Response::serverError('Unable to save uploaded file');
    }

    $url = '/' . $relativeDir . '/' . $storedName;

    Response::success('File uploaded successfully', [
        'url' => $url,
        'file_url' => $url,
        'path' => $url,
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'mime_type' => $mimeType,
        'size' => (int)$file['size'],
        'category' => $category
    ]);

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
