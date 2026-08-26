<?php

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) Response::notFound('Preview not found.');
$stmt = $con->prepare("SELECT stored_name, mime_type FROM resources WHERE resource_id = ? AND status = 'PUBLISHED' LIMIT 1");
$stmt->bind_param('i', $id); $stmt->execute();
$resource = $stmt->get_result()->fetch_assoc();
if (!$resource || !in_array((string)$resource['mime_type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) Response::notFound('An image preview is not available.');
$path = dirname(__DIR__, 2) . '/storage/resources/' . basename((string)$resource['stored_name']);
if (!is_file($path)) Response::notFound('Preview file not found.');
header_remove('Content-Type');
header('Content-Type: ' . (string)$resource['mime_type']);
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
