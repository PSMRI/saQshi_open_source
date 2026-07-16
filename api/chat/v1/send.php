<?php

/**
 * SaQshi API
 * chat/v1/send.php
 * Purpose: send endpoint/support workflow.
 */


require_once __DIR__ . '/_common.php';

Security::requireMethod('POST');

chatHandle(function () use ($con) {
    $payload = chatPayload();
    $result = ChatAssistantService::send(
        $con,
        chatUserId(),
        chatFacilityId(),
        (string)($payload['message'] ?? ''),
        (string)($payload['context_page'] ?? '')
    );

    Response::success('Assistant response generated', $result);
});
