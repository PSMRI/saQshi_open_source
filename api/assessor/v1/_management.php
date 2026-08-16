<?php

/** Access gate for Mentor/Assessor master and School/Facility mapping. */
require_once __DIR__ . '/../../auth_api.php';

if (!in_array(SessionManager::roleId(), [4, 5, 8, 9, 11], true)) {
    Response::forbidden('Mentor/Assessor management access is required.');
}

