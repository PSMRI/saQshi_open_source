<?php

require_once __DIR__ . '/_common.php';

Security::requireMethod('POST');

chatHandle(function () use ($con) {
    ChatAssistantService::clear($con, chatUserId(), chatFacilityId());
    Response::success('Chat history cleared', ['history' => []]);
});
