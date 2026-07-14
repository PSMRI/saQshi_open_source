<?php

/*! SaQshi Open Source | State User Save API | user_save.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireAnyMethod(['POST', 'PUT']);

Response::error('State user create/update workflow is not enabled yet. Use this endpoint after admin approval rules are finalized.', null, 501);
