<?php

/**
 * bootstrap.php
 * -------------------------------------------------------
 * Global bootstrap for all SaQshi APIs.
 * Loads security, session, response and common settings.
 * -------------------------------------------------------
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

/*
|--------------------------------------------------------------------------
| Core Classes
|--------------------------------------------------------------------------
*/


require_once __DIR__ . '/core/Security.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/ErrorHandler.php';
require_once __DIR__ . '/core/Env.php';
require_once __DIR__ . '/core/SessionManager.php';
require_once __DIR__ . '/core/Csrf.php';
require_once __DIR__ . '/core/Event.php';

Security::headers();
ErrorHandler::register();
Env::load();
SessionManager::start();
Event::traceRequest();

/*
|--------------------------------------------------------------------------
| Apply Security Headers
|--------------------------------------------------------------------------
*/

Security::headers();

/*
|--------------------------------------------------------------------------
| Error Reporting
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/*
|--------------------------------------------------------------------------
| Default JSON Response
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=UTF-8');
