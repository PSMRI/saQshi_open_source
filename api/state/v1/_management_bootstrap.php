<?php

/** Restricted bootstrap for User Administration and Mentor/Assessor Management. */
require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

$roleId = SessionManager::roleId();
if (!in_array($roleId, [4, 5, 8, 9, 11], true)) {
    Response::forbidden('Management access is not available for this role.');
}

