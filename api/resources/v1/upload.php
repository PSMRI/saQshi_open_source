<?php

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('POST');
if (SessionManager::roleId() !== 9) Response::forbidden('Only State Admin users can upload resources.');

$title = trim((string)($_POST['title'] ?? ''));
$type = strtoupper(trim((string)($_POST['resource_type'] ?? '')));
$description = trim((string)($_POST['description'] ?? ''));
$facilityTypeId = (int)($_POST['applicable_facility_type_id'] ?? 0);
$allowedTypes = ['LETTER', 'FORM', 'DOCUMENT', 'GUIDELINE', 'OTHER'];
if ($title === '' || mb_strlen($title) > 255) Response::validation(['title' => 'Enter a resource name of up to 255 characters.']);
if (!in_array($type, $allowedTypes, true)) Response::validation(['resource_type' => 'Select a valid resource type.']);
$facilityTypes = json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/config/masters/facility_types.json'), true);
$validFacilityTypeIds = is_array($facilityTypes) ? array_map(static fn($item): int => (int)($item['fac_type_id'] ?? 0), $facilityTypes) : [];
if ($facilityTypeId !== 0 && !in_array($facilityTypeId, $validFacilityTypeIds, true)) Response::validation(['applicable_facility_type_id' => 'Select a valid facility type or Others.']);
if (mb_strlen($description) > 4000) Response::validation(['description' => 'Description cannot exceed 4,000 characters.']);
if (!isset($_FILES['file']) || !is_array($_FILES['file'])) Response::validation(['file' => 'Choose a file to upload.']);
$uploadError = (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload limit.',
        UPLOAD_ERR_FORM_SIZE => 'The file exceeds the 500 MB resource limit.',
        UPLOAD_ERR_PARTIAL => 'The file upload was incomplete. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server upload folder is unavailable.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'This upload was blocked by the server.'
    ];
    Response::validation(['file' => $messages[$uploadError] ?? 'The file could not be uploaded.']);
}

$file = $_FILES['file'];
if ((int)$file['size'] < 1 || (int)$file['size'] > 500 * 1024 * 1024) Response::validation(['file' => 'Files must be between 1 byte and 500 MB.']);
$originalName = basename((string)$file['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($originalName === '' || mb_strlen($originalName) > 255) Response::validation(['file' => 'The file name is invalid.']);
if (!preg_match('/^[a-z0-9]{1,20}$/', $extension)) $extension = 'bin';
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string)($finfo->file((string)$file['tmp_name']) ?: 'application/octet-stream');

$storage = dirname(__DIR__, 2) . '/storage/resources';
if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) Response::serverError('Unable to prepare resource storage.');
$storedName = bin2hex(random_bytes(16)) . '.' . $extension;
$destination = $storage . DIRECTORY_SEPARATOR . $storedName;
if (!move_uploaded_file((string)$file['tmp_name'], $destination)) Response::serverError('Unable to save the uploaded file.');

$uploader = SessionManager::userId();
$stmt = $con->prepare('INSERT INTO resources (title, resource_type, description, original_name, stored_name, mime_type, file_size, applicable_facility_type_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$size = (int)$file['size'];
$stmt->bind_param('ssssssiii', $title, $type, $description, $originalName, $storedName, $mimeType, $size, $facilityTypeId, $uploader);
if (!$stmt->execute()) {
    @unlink($destination);
    Response::serverError('Unable to save the resource record.');
}
Response::created('Resource published for all users.', ['resource_id' => (int)$con->insert_id]);
