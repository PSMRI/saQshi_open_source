<?php

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) Response::notFound('Resource not found.');
$stmt = $con->prepare('SELECT original_name, stored_name, mime_type, file_size FROM resources WHERE resource_id = ? AND status = \'PUBLISHED\' LIMIT 1');
$stmt->bind_param('i', $id); $stmt->execute();
$resource = $stmt->get_result()->fetch_assoc();
if (!$resource) Response::notFound('Resource not found.');
$path = dirname(__DIR__, 2) . '/storage/resources/' . basename((string)$resource['stored_name']);
if (!is_file($path)) Response::notFound('The resource file is no longer available.');
$increment = $con->prepare('UPDATE resources SET download_count = download_count + 1 WHERE resource_id = ?');
$increment->bind_param('i', $id);
$increment->execute();
header_remove('Content-Type');
header('Content-Type: ' . (string)$resource['mime_type']);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', (string)$resource['original_name']) . '"; filename*=UTF-8\'\'' . rawurlencode((string)$resource['original_name']));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
