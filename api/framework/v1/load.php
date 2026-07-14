<?php

require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';

try {
    $frameworkCode = $_GET['framework'] ?? 'sample-framework';
    $engine = FrameworkEngine::load($frameworkCode);

    Response::success('Framework loaded successfully', $engine->toArray());

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}