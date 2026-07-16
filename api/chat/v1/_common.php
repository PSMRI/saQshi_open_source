<?php

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/ChatAssistantService.php';

/**
 * Handles chat payload processing for this API workflow.
 */
function chatPayload(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($data) ? $data : [];
}

/**
 * Handles chat user id processing for this API workflow.
 */
function chatUserId(): int
{
    return (int)($_SESSION['u_id'] ?? 0);
}

/**
 * Handles chat facility id processing for this API workflow.
 */
function chatFacilityId(): int
{
    return (int)($_SESSION['fac_id'] ?? 0);
}

/**
 * Handles chat handle processing for this API workflow.
 */
function chatHandle(callable $fn): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        Response::validation(['message' => $e->getMessage()]);
    } catch (Throwable $e) {
        Response::serverError($e->getMessage());
    }
}
