<?php

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('POST');
if (SessionManager::roleId() !== 9) Response::forbidden('Only State Admin users can delete resources.');
$input = Security::jsonInput();
$id = (int)($input['resource_id'] ?? 0);
if ($id <= 0) Response::validation(['resource_id' => 'A resource is required.']);
$stmt = $con->prepare('SELECT stored_name FROM resources WHERE resource_id = ? LIMIT 1');
$stmt->bind_param('i', $id); $stmt->execute();
$resource = $stmt->get_result()->fetch_assoc();
if (!$resource) Response::notFound('Resource not found.');
$delete = $con->prepare('DELETE FROM resources WHERE resource_id = ? LIMIT 1');
$delete->bind_param('i', $id); $delete->execute();
@unlink(dirname(__DIR__, 2) . '/storage/resources/' . basename((string)$resource['stored_name']));
Response::success('Resource deleted.');
