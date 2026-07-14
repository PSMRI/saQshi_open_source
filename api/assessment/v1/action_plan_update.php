<?php

/**
 * action_plan_update.php
 * -------------------------------------------------------
 * Update existing action plan.
 *
 * Method : POST
 *
 * Body
 * {
 *   "id":15,
 *   "user_action_plan":"Arrange training",
 *   "achievability":"ACHIEVABLE",
 *   "responsible_person":"Dr Kumar",
 *   "priority":"HIGH",
 *   "target_date":"2026-08-15",
 *   "status":"IN_PROGRESS"
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('POST');

try {

    $request = Security::jsonInput();

    $userId = SessionManager::userId();

    if ($userId <= 0) {
        Response::error('User session not found');
    }

    $id = (int)($request['id'] ?? 0);

    if ($id <= 0) {
        Response::validation([
            'id' => 'Action plan id is required'
        ]);
    }

    /*
     * Validate action plan
     */
    $sql = "
        SELECT *
        FROM assessment_action_plan
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        Response::serverError($con->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $plan = $stmt->get_result()->fetch_assoc();

    if (!$plan) {
        Response::error("Action plan not found");
    }

    $userActionPlan = trim(
        $request['user_action_plan']
        ?? $plan['user_action_plan']
    );

    $achievability = strtoupper(
        trim(
            $request['achievability']
            ?? $plan['achievability']
        )
    );

    $priority = strtoupper(
        trim(
            $request['priority']
            ?? $plan['priority']
        )
    );

    $status = strtoupper(
        trim(
            $request['status']
            ?? $plan['status']
        )
    );

    $responsiblePerson =
        $request['responsible_person']
        ?? $plan['responsible_person'];

    $targetDate =
        $request['target_date']
        ?? $plan['target_date'];

    if ($achievability == "NON_ACHIEVABLE") {

        $responsiblePerson = null;
        $targetDate = null;
        $priority = "LOW";
    }

    /*
     * Update
     */

    $sql = "
        UPDATE assessment_action_plan
        SET
            user_action_plan=?,
            achievability=?,
            responsible_person=?,
            priority=?,
            target_date=?,
            status=?,
            updated_by=?,
            updated_on=CURRENT_TIMESTAMP
        WHERE id=?
    ";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        Response::serverError($con->error);
    }

    $stmt->bind_param(
        "ssssssii",
        $userActionPlan,
        $achievability,
        $responsiblePerson,
        $priority,
        $targetDate,
        $status,
        $userId,
        $id
    );

    if (!$stmt->execute()) {
        Response::serverError($stmt->error);
    }

    Response::success(
        "Action plan updated successfully",
        [
            "id"=>$id,
            "user_action_plan"=>$userActionPlan,
            "achievability"=>$achievability,
            "responsible_person"=>$responsiblePerson,
            "priority"=>$priority,
            "target_date"=>$targetDate,
            "status"=>$status
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());

}