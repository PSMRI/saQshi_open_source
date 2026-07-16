<?php

require_once dirname(__DIR__, 2) . '/auth_api.php';
require_once dirname(__DIR__, 2) . '/assets/conn/db.php';
require_once dirname(__DIR__, 2) . '/service/CertificationService.php';

if (!function_exists('respond')) {
    /**
     * Handles respond processing for this API workflow.
     */
    function respond(array $data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/**
 * Handles certification payload processing for this API workflow.
 */
function certificationPayload(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

/**
 * Handles certification filters processing for this API workflow.
 */
function certificationFilters(): array
{
    $filters = [
        'fac_nin' => $_GET['fac_nin'] ?? null,
        'fac_id' => $_GET['fac_id'] ?? null,
        'dist_id' => $_GET['dist_id'] ?? null,
        'block_id' => $_GET['block_id'] ?? null,
        'certification_type' => $_GET['certification_type'] ?? $_GET['cert_type'] ?? null,
        'status' => $_GET['status'] ?? $_GET['Cert_status'] ?? null
    ];

    if (empty($filters['fac_id']) && empty($filters['fac_nin'])) {
        $sessionFacilityId = SessionManager::facilityId();
        if ($sessionFacilityId > 0) {
            $filters['fac_id'] = $sessionFacilityId;
        }
    }

    return $filters;
}

/**
 * Handles certification handle processing for this API workflow.
 */
function certificationHandle(callable $fn): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        $decoded = json_decode($e->getMessage(), true);
        respond([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => is_array($decoded) ? $decoded : $e->getMessage()
        ], 422);
    } catch (DomainException $e) {
        respond(['status' => 'error', 'message' => $e->getMessage()], 409);
    } catch (OutOfBoundsException $e) {
        respond(['status' => 'error', 'message' => $e->getMessage()], 404);
    } catch (Throwable $e) {
        respond(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
