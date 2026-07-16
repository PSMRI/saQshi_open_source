<?php

/**
 * SaQshi API
 * chat/v1/history.php
 * Purpose: history endpoint/support workflow.
 */


require_once __DIR__ . '/_common.php';

Security::requireMethod('GET');

chatHandle(function () use ($con) {
    Response::success('Chat history fetched', [
        'history' => ChatAssistantService::history($con, chatUserId(), chatFacilityId())
    ]);
});
