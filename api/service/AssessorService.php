<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Assessor Assignment Service
 * AssessorService.php
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */

require_once __DIR__ . '/../core/Crypto.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/FrameworkEngine.php';
require_once __DIR__ . '/DepartmentStatusService.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/SmsService.php';

class AssessorService
{
    private const SCHEMA_MARKER = 'api/storage/schema/assessor_service_v2.ready';

    private mysqli $db;
    private array $columnCache = [];
    private array $tableExistsCache = [];

    public function __construct(mysqli $db)
    {
        $this->db = $db;
        $this->ensureSchemaOnce();
    }

    public function listAssessors(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(10, (int)($params['per_page'] ?? $params['perPage'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        $search = trim((string)($params['search'] ?? ''));
        $where = '';
        $types = '';
        $values = [];

        if ($search !== '') {
            $where = "WHERE am.assessor_code LIKE ? OR CAST(am.user_id AS CHAR) LIKE ?";
            $like = '%' . $search . '%';
            $types = 'ss';
            $values = [$like, $like];
        }

        $total = $this->scalar("SELECT COUNT(*) FROM assessor_master am {$where}", $types, $values);
        $sql = "
            SELECT am.*, u.u_name,
                   COUNT(afm.mapping_id) AS mapped_facilities
            FROM assessor_master am
            LEFT JOIN s_user u ON u.u_id = am.user_id
            LEFT JOIN assessor_facility_mapping afm
                ON afm.assessor_id = am.assessor_id
               AND afm.assignment_status = 'ACTIVE'
            {$where}
            GROUP BY am.assessor_id
            ORDER BY am.assessor_id DESC
            LIMIT ? OFFSET ?
        ";

        $rows = $this->rows($sql, $types . 'ii', array_merge($values, [$perPage, $offset]));

        return [
            'rows' => array_map([$this, 'publicAssessor'], $rows),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => (int)ceil($total / $perPage)
            ]
        ];
    }

    public function saveAssessor(array $payload, int $userId): array
    {
        $assessorId = (int)($payload['assessor_id'] ?? 0);
        $code = strtoupper(trim((string)($payload['assessor_code'] ?? '')));
        $name = trim((string)($payload['assessor_name'] ?? ''));

        if ($code === '') {
            throw new InvalidArgumentException('Assessor code is required.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Assessor name is required.');
        }

        $linkedUserId = $this->nullableInt($payload['user_id'] ?? null);
        $notification = null;

        if ($assessorId <= 0 && (!$linkedUserId || $linkedUserId <= 0)) {
            $createdUser = $this->createAssessorLoginUser($code, $name, $payload);
            $linkedUserId = (int)$createdUser['user_id'];
            $notification = $createdUser['notification'];
        }

        if ($assessorId > 0) {
            $sql = "
                UPDATE assessor_master
                SET user_id = ?, assessor_code = ?, assessor_name = ?, designation = ?,
                    mobile_no = ?, mail_id = ?, state_id = ?, division_id = ?,
                    dist_id = ?, block_id = ?, is_active = ?, updated_by = ?
                WHERE assessor_id = ?
            ";
            $values = [
                $linkedUserId,
                $code,
                Crypto::encrypt($name),
                trim((string)($payload['designation'] ?? '')),
                Crypto::encrypt(trim((string)($payload['mobile_no'] ?? ''))),
                Crypto::encrypt(trim((string)($payload['mail_id'] ?? ''))),
                $this->nullableInt($payload['state_id'] ?? null),
                $this->nullableInt($payload['division_id'] ?? null),
                $this->nullableInt($payload['dist_id'] ?? null),
                $this->nullableInt($payload['block_id'] ?? null),
                (int)($payload['is_active'] ?? 1),
                $userId,
                $assessorId
            ];
            $this->execute($sql, 'isssssiiiiiii', $values);
        } else {
            $sql = "
                INSERT INTO assessor_master
                (user_id, assessor_code, assessor_name, designation, mobile_no, mail_id,
                 state_id, division_id, dist_id, block_id, is_active, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $values = [
                $linkedUserId,
                $code,
                Crypto::encrypt($name),
                trim((string)($payload['designation'] ?? '')),
                Crypto::encrypt(trim((string)($payload['mobile_no'] ?? ''))),
                Crypto::encrypt(trim((string)($payload['mail_id'] ?? ''))),
                $this->nullableInt($payload['state_id'] ?? null),
                $this->nullableInt($payload['division_id'] ?? null),
                $this->nullableInt($payload['dist_id'] ?? null),
                $this->nullableInt($payload['block_id'] ?? null),
                (int)($payload['is_active'] ?? 1),
                $userId,
                $userId
            ];
            $this->execute($sql, 'isssssiiiiiii', $values);
            $assessorId = (int)$this->db->insert_id;
        }

        $assessor = $this->getAssessor($assessorId);
        $assessor['login_user_created'] = $notification !== null;
        $assessor['temporary_password_sent'] = $notification;

        return $assessor;
    }

    public function getAssessor(int $assessorId): array
    {
        $row = $this->row(
            "SELECT am.*, u.u_name FROM assessor_master am LEFT JOIN s_user u ON u.u_id = am.user_id WHERE am.assessor_id = ?",
            'i',
            [$assessorId]
        );

        if (!$row) {
            throw new RuntimeException('Assessor not found.');
        }

        return $this->publicAssessor($row);
    }

    public function searchFacilities(array $params): array
    {
        $search = trim((string)($params['search'] ?? ''));
        $limit = min(50, max(5, (int)($params['limit'] ?? 25)));
        $where = $search !== ''
            ? "WHERE fac_name LIKE ? OR CAST(NIN_no AS CHAR) LIKE ? OR Dist_Name LIKE ? OR Block_Name LIKE ?"
            : '';
        $types = $search !== '' ? 'ssss' : '';
        $like = '%' . $search . '%';
        $values = $search !== '' ? [$like, $like, $like, $like] : [];

        return $this->rows(
            "SELECT fac_id, NIN_no, fac_name, Dist_Name, Block_Name, state_name, division, Health_facilty_type
             FROM facilities {$where} ORDER BY fac_name LIMIT ?",
            $types . 'i',
            array_merge($values, [$limit])
        );
    }

    public function listMappings(array $params): array
    {
        $assessorId = (int)($params['assessor_id'] ?? 0);

        if ($assessorId <= 0) {
            throw new InvalidArgumentException('Assessor ID is required.');
        }

        return $this->rows(
            "SELECT afm.*, f.fac_name, f.Dist_Name, f.Block_Name, f.state_name, f.division, f.Health_facilty_type
             FROM assessor_facility_mapping afm
             LEFT JOIN facilities f ON f.fac_id = afm.fac_id
             WHERE afm.assessor_id = ?
             ORDER BY afm.assignment_status, f.fac_name",
            'i',
            [$assessorId]
        );
    }

    public function saveMapping(array $payload, int $userId): array
    {
        $assessorId = (int)($payload['assessor_id'] ?? 0);
        $facId = (int)($payload['fac_id'] ?? 0);

        if ($assessorId <= 0 || $facId <= 0) {
            throw new InvalidArgumentException('Assessor and facility are required.');
        }

        $facility = $this->facility($facId);

        if (!$facility) {
            throw new InvalidArgumentException('Facility not found.');
        }

        $status = strtoupper(trim((string)($payload['assignment_status'] ?? 'ACTIVE')));
        $status = in_array($status, ['ACTIVE', 'INACTIVE'], true) ? $status : 'ACTIVE';

        $this->execute(
            "INSERT INTO assessor_facility_mapping
             (assessor_id, fac_id, fac_nin, assignment_status, assigned_from, assigned_to, assigned_by, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                fac_nin = VALUES(fac_nin),
                assignment_status = VALUES(assignment_status),
                assigned_from = VALUES(assigned_from),
                assigned_to = VALUES(assigned_to),
                assigned_by = VALUES(assigned_by),
                remarks = VALUES(remarks),
                updated_on = CURRENT_TIMESTAMP",
            'iiisssis',
            [
                $assessorId,
                $facId,
                $facility['NIN_no'] !== null ? (int)$facility['NIN_no'] : null,
                $status,
                $this->nullableDate($payload['assigned_from'] ?? null),
                $this->nullableDate($payload['assigned_to'] ?? null),
                $userId,
                trim((string)($payload['remarks'] ?? ''))
            ]
        );

        return ['assessor_id' => $assessorId, 'fac_id' => $facId, 'assignment_status' => $status];
    }

    public function assessorDashboard(int $userId, string $username): array
    {
        $assessor = $this->currentAssessor($userId, $username);
        $modules = $this->moduleConfig();
        $facilities = array_map(
            fn($row) => $this->withFacilityWorkflow($row, $modules),
            $this->mappedFacilities((int)$assessor['assessor_id'])
        );

        return [
            'assessor' => $this->publicAssessor($assessor),
            'modules' => $modules,
            'total_facilities' => count($facilities),
            'active_mappings' => count(array_filter($facilities, fn($row) => $row['assignment_status'] === 'ACTIVE')),
            'facilities' => $facilities
        ];
    }

    public function mappedFacilitiesForUser(int $userId, string $username): array
    {
        $assessor = $this->currentAssessor($userId, $username);
        $modules = $this->moduleConfig();

        return [
            'assessor' => $this->publicAssessor($assessor),
            'modules' => $modules,
            'facilities' => array_map(
                fn($row) => $this->withFacilityWorkflow($row, $modules),
                $this->mappedFacilities((int)$assessor['assessor_id'])
            )
        ];
    }

    public function facilitySummary(int $userId, string $username, int $facId): array
    {
        $assessor = $this->currentAssessor($userId, $username);

        $mapping = $this->row(
            "SELECT mapping_id FROM assessor_facility_mapping
             WHERE assessor_id = ? AND fac_id = ?
             LIMIT 1",
            'ii',
            [(int)$assessor['assessor_id'], $facId]
        );

        if (!$mapping) {
            throw new RuntimeException('This facility is not assigned to the logged-in assessor.');
        }

        $facility = $this->facility($facId);

        if (!$facility) {
            throw new RuntimeException('Facility details not found.');
        }

        $_SESSION['fac_id'] = $facId;
        $_SESSION['assessor_id'] = (int)$assessor['assessor_id'];
        $_SESSION['assessor_selected_fac_id'] = $facId;

        return [
            'facility' => $facility,
            'modules' => $this->moduleConfig(),
            'assessments' => $this->facilityAssessments($facId, $facility),
            'cqi' => $this->facilityCqiSummary($facId),
            'performance' => $this->facilityPerformanceSummary($facId)
        ];
    }

    public function startAssessment(array $payload, int $userId, string $username): array
    {
        $assessor = $this->currentAssessor($userId, $username);
        $assessorId = (int)$assessor['assessor_id'];
        $facId = (int)($payload['fac_id'] ?? 0);

        if ($facId <= 0) {
            throw new InvalidArgumentException('Facility is required.');
        }

        $mapping = $this->row(
            "SELECT * FROM assessor_facility_mapping
             WHERE assessor_id = ? AND fac_id = ? AND assignment_status = 'ACTIVE'
             LIMIT 1",
            'ii',
            [$assessorId, $facId]
        );

        if (!$mapping) {
            throw new RuntimeException('This facility is not assigned to the logged-in assessor.');
        }

        $facility = $this->facility($facId);

        if (!$facility) {
            throw new RuntimeException('Facility details not found.');
        }

        $_SESSION['fac_id'] = $facId;
        $_SESSION['assessor_id'] = $assessorId;
        $_SESSION['assessor_selected_fac_id'] = $facId;

        $framework = trim((string)($payload['framework_code'] ?? 'saqshi-nqas'));
        $assessment = $this->activeAssessment($facId);
        $created = false;

        if (!$assessment) {
            $name = trim((string)($payload['assessment_name'] ?? 'State Assessment - ' . ($facility['fac_name'] ?? 'Facility')));
            $startDate = trim((string)($payload['start_date'] ?? date('Y-m-d')));
            $endDate = trim((string)($payload['end_date'] ?? date('Y-m-d', strtotime('+30 days'))));

            $this->execute(
                "INSERT INTO assessment_master
                 (assessment_name, framework_code, fac_id_fk, start_date, end_date, status, created_by, assigned_assessor_id, assessment_source)
                 VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?, ?, 'STATE_ASSESSOR')",
                'ssissii',
                [$name, $framework, $facId, $startDate, $endDate, $userId, $assessorId]
            );

            $assessment = $this->activeAssessment($facId);
            $created = true;
        }

        $assessmentId = (int)($assessment['assessment_id'] ?? 0);
        $_SESSION['assessment_id'] = $assessmentId;

        $departments = $this->frameworkDepartments($framework, (int)($facility['Health_facilty_type'] ?? 0));
        $autoActivated = false;

        if (count($departments) === 1 && $assessmentId > 0) {
            $deptId = (int)($departments[0]['dept_id'] ?? $departments[0]['fac_dept_id'] ?? 0);
            if ($deptId > 0) {
                (new DepartmentStatusService($this->db))->saveStatus([
                    'fac_id' => $facId,
                    'ass_period' => $assessmentId,
                    'dept_id' => $deptId,
                    'is_active' => 1,
                    'user_id' => $userId
                ]);
                $autoActivated = true;
            }
        }

        $this->execute(
            "UPDATE assessor_facility_mapping SET last_assessment_id = ? WHERE assessor_id = ? AND fac_id = ?",
            'iii',
            [$assessmentId, $assessorId, $facId]
        );

        return [
            'created' => $created,
            'assessment' => $assessment,
            'facility' => $facility,
            'department_count' => count($departments),
            'auto_activated_single_department' => $autoActivated,
            'next_action' => $this->workflowForAssessment($facId, $assessment, $this->moduleConfig()),
            'next_route' => $autoActivated ? 'assessment/assessor-info' : 'assessment/departments'
        ];
    }

    private function currentAssessor(int $userId, string $username): array
    {
        $row = $this->row(
            "SELECT * FROM assessor_master
             WHERE is_active = 1 AND (user_id = ? OR assessor_code = ?)
             ORDER BY user_id = ? DESC
             LIMIT 1",
            'isi',
            [$userId, strtoupper(trim($username)), $userId]
        );

        if (!$row) {
            throw new RuntimeException('Assessor profile is not mapped to this login.');
        }

        return $row;
    }

    private function createAssessorLoginUser(string $code, string $name, array $payload): array
    {
        $existing = $this->row(
            "SELECT u_id FROM s_user WHERE u_name = ? LIMIT 1",
            's',
            [$code]
        );

        if ($existing) {
            return [
                'user_id' => (int)$existing['u_id'],
                'notification' => [
                    'created' => false,
                    'message' => 'Existing login user linked.'
                ]
            ];
        }

        $temporaryPassword = $this->temporaryPassword();
        $roleId = $this->assessorRoleId();
        $email = trim((string)($payload['mail_id'] ?? ''));
        $mobile = trim((string)($payload['mobile_no'] ?? ''));
        $profile = $this->splitName($name);
        $columns = [
            'u_name' => $code,
            'u_password' => Auth::hashPassword($temporaryPassword),
            'role_id_fk' => $roleId,
            'is_active' => 1,
            'f_name' => Crypto::encrypt($profile['first']),
            'm_name' => Crypto::encrypt($profile['middle']),
            'l_name' => Crypto::encrypt($profile['last']),
            'mail_id' => Crypto::encrypt($email),
            'mob_no' => Crypto::encrypt($mobile),
            'user_type' => 'ASSESSOR',
            'fac_id_fk' => null,
            'dept_id' => null,
            'password_must_change' => 1
        ];

        $columns = array_filter(
            $columns,
            fn($value, $column) => $this->columnExists('s_user', (string)$column),
            ARRAY_FILTER_USE_BOTH
        );

        $names = array_keys($columns);
        $placeholders = implode(', ', array_fill(0, count($names), '?'));
        $types = '';
        $values = [];

        foreach ($columns as $value) {
            $types .= is_int($value) ? 'i' : 's';
            $values[] = $value;
        }

        $this->execute(
            'INSERT INTO s_user (' . implode(', ', $names) . ') VALUES (' . $placeholders . ')',
            $types,
            $values
        );

        $templateVars = [
            'assessor_code' => $code,
            'assessor_name' => $name,
            'username' => $code,
            'temporary_password' => $temporaryPassword
        ];
        $emailResult = (new EmailService())->sendTemplate('assessor_login', $email, $templateVars, ['assessor_code' => $code]);
        $smsResult = (new SmsService())->sendTemplate('assessor_login', $mobile, $templateVars, ['assessor_code' => $code]);

        return [
            'user_id' => (int)$this->db->insert_id,
            'notification' => [
                'created' => true,
                'email' => $emailResult,
                'sms' => $smsResult
            ]
        ];
    }

    private function assessorRoleId(): int
    {
        if ($this->tableExists('u_role')) {
            $row = $this->row(
                "SELECT role_id FROM u_role WHERE LOWER(role_name) LIKE '%assessor%' AND role_status = 1 ORDER BY role_id LIMIT 1"
            );

            if ($row) {
                return (int)$row['role_id'];
            }

            if ($this->columnExists('u_role', 'role_id') && $this->columnExists('u_role', 'role_name')) {
                $roleStatus = $this->columnExists('u_role', 'role_status') ? ', role_status' : '';
                $statusValue = $roleStatus !== '' ? ', 1' : '';
                $this->db->query("INSERT INTO u_role (role_id, role_name{$roleStatus}) VALUES (10, 'Assessor'{$statusValue})");
            }
        }

        return 10;
    }

    private function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
        $password = '';

        for ($i = 0; $i < 12; $i += 1) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return [
            'first' => $parts[0] ?? $name,
            'middle' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : '',
            'last' => count($parts) > 1 ? (string)end($parts) : ''
        ];
    }

    private function mappedFacilities(int $assessorId): array
    {
        $rows = $this->rows(
            "SELECT afm.mapping_id, afm.assessor_id, afm.fac_id, afm.fac_nin,
                    afm.assignment_status, afm.assigned_from, afm.assigned_to,
                    afm.last_assessment_id, f.fac_name, f.Dist_Name, f.Block_Name,
                    f.state_name, f.division, f.Health_facilty_type,
                    a.assessment_id, a.assessment_name, a.status AS assessment_status
             FROM assessor_facility_mapping afm
             LEFT JOIN facilities f ON f.fac_id = afm.fac_id
             LEFT JOIN assessment_master a ON a.assessment_id = afm.last_assessment_id
             WHERE afm.assessor_id = ?
             ORDER BY afm.assignment_status, f.fac_name",
            'i',
            [$assessorId]
        );

        return $rows;
    }

    private function withFacilityWorkflow(array $row, array $modules): array
    {
        $facId = (int)($row['fac_id'] ?? 0);
        $assessment = $facId > 0 ? $this->activeAssessment($facId) : null;

        if ($assessment) {
            $row['assessment_id'] = (int)$assessment['assessment_id'];
            $row['last_assessment_id'] = (int)$assessment['assessment_id'];
            $row['assessment_name'] = $assessment['assessment_name'] ?? '';
            $row['assessment_status'] = $assessment['status'] ?? 'ACTIVE';
            $row['framework_code'] = $assessment['framework_code'] ?? '';
        }

        $row['next_action'] = $this->workflowForAssessment($facId, $assessment, $modules);
        return $row;
    }

    private function workflowForAssessment(int $facId, ?array $assessment, array $modules): array
    {
        if (!$this->moduleEnabled($modules, 'assessment')) {
            return [
                'type' => 'disabled',
                'label' => 'Assessment Disabled',
                'route' => '',
                'params' => [],
                'state' => 'disabled'
            ];
        }

        if (!$assessment) {
            return [
                'type' => 'start',
                'label' => 'Start Assessment',
                'route' => '',
                'params' => ['fac_id' => $facId],
                'state' => 'not_started'
            ];
        }

        $assessmentId = (int)($assessment['assessment_id'] ?? 0);
        $activeDepartments = $this->activeDepartments($facId, $assessmentId);
        $deptId = (int)($activeDepartments[0]['dept_id'] ?? 0);

        if (!$activeDepartments) {
            return [
                'type' => 'route',
                'label' => 'Activate Department',
                'route' => 'assessment/departments',
                'params' => ['assessment_id' => $assessmentId],
                'state' => 'department_pending',
                'assessment_id' => $assessmentId,
                'active_department_count' => 0,
                'assessor_info_count' => 0,
                'response_count' => 0
            ];
        }

        $assessorInfoCount = $this->assessorInfoCount($facId, $assessmentId);
        $responseCount = $this->responseCount($assessmentId, $deptId);

        if ($assessorInfoCount <= 0) {
            return [
                'type' => 'route',
                'label' => 'Assessor Info',
                'route' => 'assessment/assessor-info',
                'params' => ['assessment_id' => $assessmentId, 'dept_id' => $deptId],
                'state' => 'assessor_info_pending',
                'assessment_id' => $assessmentId,
                'dept_id' => $deptId,
                'active_department_count' => count($activeDepartments),
                'assessor_info_count' => $assessorInfoCount,
                'response_count' => $responseCount
            ];
        }

        return [
            'type' => 'route',
            'label' => $responseCount > 0 ? 'Continue Checklist' : 'Start Checklist',
            'route' => 'assessment/checklist',
            'params' => ['assessment_id' => $assessmentId, 'dept_id' => $deptId],
            'state' => 'checklist_ready',
            'assessment_id' => $assessmentId,
            'dept_id' => $deptId,
            'active_department_count' => count($activeDepartments),
            'assessor_info_count' => $assessorInfoCount,
            'response_count' => $responseCount
        ];
    }

    private function activeDepartments(int $facId, int $assessmentId): array
    {
        if (!$this->tableExists('assessment_department_status')) {
            return [];
        }

        $assessmentColumn = $this->departmentStatusAssessmentColumn();

        return $this->rows(
            "SELECT dept_id
             FROM assessment_department_status
             WHERE fac_id_fk = ? AND {$assessmentColumn} = ? AND is_active = 1
             ORDER BY dept_id",
            'ii',
            [$facId, $assessmentId]
        );
    }

    private function assessorInfoCount(int $facId, int $assessmentId): int
    {
        if (!$this->tableExists('assessment_assessor_info')) {
            return 0;
        }

        return $this->scalar(
            "SELECT COUNT(*) FROM assessment_assessor_info
             WHERE fac_id_fk = ? AND assessment_id = ?",
            'ii',
            [$facId, $assessmentId]
        );
    }

    private function responseCount(int $assessmentId, int $deptId = 0): int
    {
        $table = $this->responseTable();

        if ($table === '') {
            return 0;
        }

        $column = $this->columnExists($table, 'assessment_id') ? 'assessment_id' : 'cycle_id';

        if ($deptId > 0 && $this->columnExists($table, 'dept_id')) {
            return $this->scalar(
                "SELECT COUNT(*) FROM {$table} WHERE {$column} = ? AND dept_id = ?",
                'ii',
                [$assessmentId, $deptId]
            );
        }

        return $this->scalar(
            "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?",
            'i',
            [$assessmentId]
        );
    }

    private function responseTable(): string
    {
        if ($this->tableExists('assessment_response')) {
            return 'assessment_response';
        }

        if ($this->tableExists('assessment_cycle_response')) {
            return 'assessment_cycle_response';
        }

        return '';
    }

    private function facilityAssessments(int $facId, array $facility): array
    {
        $responseTable = $this->responseTable();
        $deptSelect = "0 AS active_departments";
        $scoreSelect = "0 AS saved_checkpoints, 0 AS obtained_score, 0 AS max_score, 0 AS score_percent";

        if ($this->tableExists('assessment_department_status')) {
            $departmentStatusColumn = $this->departmentStatusAssessmentColumn();

            $deptSelect = "
                (SELECT COUNT(DISTINCT ds.dept_id)
                 FROM assessment_department_status ds
                 WHERE ds.{$departmentStatusColumn} = a.assessment_id
                   AND ds.fac_id_fk = a.fac_id_fk
                   AND ds.is_active = 1) AS active_departments
            ";
        }

        if ($responseTable !== '') {
            $responseColumn = $this->columnExists($responseTable, 'assessment_id') ? 'assessment_id' : 'cycle_id';
            $scoreExpression = 'r.score';
            $actionJoin = '';

            if ($this->tableExists('assessment_action_plan')) {
                $scoreExpression = 'COALESCE(ap.revised_score, r.score)';
                $actionJoin = "
                    LEFT JOIN assessment_action_plan ap
                        ON ap.assessment_id = a.assessment_id
                       AND ap.dept_id = r.dept_id
                       AND ap.checkpoint_id = r.checkpoint_id
                ";
            }

            $scoreSelect = "
                (SELECT COUNT(*)
                 FROM {$responseTable} r
                 WHERE r.{$responseColumn} = a.assessment_id) AS saved_checkpoints,
                (SELECT COALESCE(SUM({$scoreExpression}), 0)
                 FROM {$responseTable} r
                 {$actionJoin}
                 WHERE r.{$responseColumn} = a.assessment_id) AS obtained_score,
                0 AS max_score,
                0 AS score_percent
            ";
        }

        $rows = $this->rows(
            "SELECT a.assessment_id, a.assessment_name, a.framework_code, a.status,
                    a.start_date, a.end_date, a.created_on,
                    {$deptSelect},
                    {$scoreSelect}
             FROM assessment_master a
             WHERE a.fac_id_fk = ?
             ORDER BY a.assessment_id DESC
             LIMIT 25",
            'i',
            [$facId]
        );

        foreach ($rows as &$row) {
            $totalCheckpoints = $this->assessmentChecklistCount(
                $facId,
                (int)($row['assessment_id'] ?? 0),
                (string)($row['framework_code'] ?? 'saqshi-nqas'),
                (int)($facility['Health_facilty_type'] ?? 0)
            );
            $maxScore = $totalCheckpoints * 2;
            $obtained = (float)($row['obtained_score'] ?? 0);

            $row['total_checkpoints'] = $totalCheckpoints;
            $row['max_score'] = $maxScore;
            $row['score_percent'] = $maxScore > 0 ? round(($obtained / $maxScore) * 100, 2) : 0;
        }

        unset($row);

        return $rows;
    }

    private function assessmentChecklistCount(int $facId, int $assessmentId, string $framework, int $facilityType): int
    {
        if ($assessmentId <= 0 || $facilityType <= 0) {
            return 0;
        }

        $activeDepartments = $this->activeDepartments($facId, $assessmentId);
        $departmentIds = array_map(fn($row) => (int)($row['dept_id'] ?? 0), $activeDepartments);
        $departmentIds = array_values(array_filter(array_unique($departmentIds)));

        if (!$departmentIds) {
            return 0;
        }

        try {
            $engine = FrameworkEngine::load($framework ?: 'saqshi-nqas');
            $total = 0;

            foreach ($departmentIds as $deptId) {
                $total += count($engine->getCheckpoints($facilityType, $deptId));
            }

            return $total;
        } catch (Throwable) {
            return 0;
        }
    }

    private function facilityCqiSummary(int $facId): array
    {
        if (!$this->tableExists('assessment_action_plan')) {
            return ['enabled' => false, 'open_gaps' => 0, 'closed_gaps' => 0, 'action_plans' => 0];
        }

        $row = $this->row(
            "SELECT
                COUNT(*) AS action_plans,
                SUM(CASE WHEN ap.status = 'COMPLETED' OR ap.revised_score >= 2 THEN 1 ELSE 0 END) AS closed_gaps,
                SUM(CASE WHEN (ap.status IS NULL OR ap.status <> 'COMPLETED') AND (ap.revised_score IS NULL OR ap.revised_score < 2) THEN 1 ELSE 0 END) AS open_gaps
             FROM assessment_action_plan ap
             INNER JOIN assessment_master a ON a.assessment_id = ap.assessment_id
             WHERE a.fac_id_fk = ?",
            'i',
            [$facId]
        ) ?? [];

        return [
            'enabled' => true,
            'action_plans' => (int)($row['action_plans'] ?? 0),
            'open_gaps' => (int)($row['open_gaps'] ?? 0),
            'closed_gaps' => (int)($row['closed_gaps'] ?? 0)
        ];
    }

    private function facilityPerformanceSummary(int $facId): array
    {
        if (!$this->tableExists('performance_entries')) {
            return ['enabled' => false, 'total_months' => 0, 'kpi_months' => 0, 'outcome_months' => 0, 'latest_period' => null];
        }

        $row = $this->row(
            "SELECT
                COUNT(DISTINCT CONCAT(entry_year, '-', LPAD(entry_month, 2, '0'))) AS total_months,
                COUNT(DISTINCT CASE WHEN indicator_type = 'KPI' THEN CONCAT(entry_year, '-', LPAD(entry_month, 2, '0')) END) AS kpi_months,
                COUNT(DISTINCT CASE WHEN indicator_type = 'OUTCOME' THEN CONCAT(entry_year, '-', LPAD(entry_month, 2, '0')) END) AS outcome_months,
                MAX(CONCAT(entry_year, '-', LPAD(entry_month, 2, '0'))) AS latest_period
             FROM performance_entries
             WHERE fac_id = ?",
            'i',
            [$facId]
        ) ?? [];

        return [
            'enabled' => true,
            'total_months' => (int)($row['total_months'] ?? 0),
            'kpi_months' => (int)($row['kpi_months'] ?? 0),
            'outcome_months' => (int)($row['outcome_months'] ?? 0),
            'latest_period' => $row['latest_period'] ?? null
        ];
    }

    private function moduleConfig(): array
    {
        $default = [
            'domain' => 'healthcare',
            'modules' => [
                'assessment' => ['enabled' => true, 'label' => 'Assessment'],
                'cqi' => ['enabled' => true, 'label' => 'CQI / Gap Closure'],
                'performance' => ['enabled' => true, 'label' => 'Performance Monitoring'],
                'kpi' => ['enabled' => true, 'label' => 'KPI'],
                'outcome' => ['enabled' => true, 'label' => 'Outcome'],
                'certification' => ['enabled' => true, 'label' => 'Certification'],
                'reports' => ['enabled' => true, 'label' => 'Reports']
            ],
            'role_visibility' => ['assessor' => ['assessment', 'cqi', 'performance', 'kpi', 'outcome', 'reports']]
        ];
        $path = __DIR__ . '/../config/modules.json';

        if (!is_file($path)) {
            return $default;
        }

        $data = json_decode((string)file_get_contents($path), true);

        if (!is_array($data)) {
            return $default;
        }

        $data['modules'] = array_replace_recursive($default['modules'], $data['modules'] ?? []);
        $data['role_visibility'] = array_replace_recursive($default['role_visibility'], $data['role_visibility'] ?? []);
        $data['domain'] = (string)($data['domain'] ?? $default['domain']);

        return $data;
    }

    private function moduleEnabled(array $config, string $module): bool
    {
        return (bool)($config['modules'][$module]['enabled'] ?? false);
    }

    private function activeAssessment(int $facId): ?array
    {
        return $this->row(
            "SELECT assessment_id, assessment_name, framework_code, fac_id_fk, start_date,
                    end_date, status, created_by, created_on, updated_on
             FROM assessment_master
             WHERE fac_id_fk = ? AND status = 'ACTIVE'
             ORDER BY assessment_id DESC
             LIMIT 1",
            'i',
            [$facId]
        );
    }

    private function frameworkDepartments(string $framework, int $facilityType): array
    {
        if ($facilityType <= 0) {
            return [];
        }

        try {
            return FrameworkEngine::load($framework)->getDepartments($facilityType);
        } catch (Throwable) {
            return [];
        }
    }

    private function publicAssessor(array $row): array
    {
        $row = Crypto::decryptFields($row, ['assessor_name', 'mobile_no', 'mail_id']);
        $row['assessor_id'] = (int)($row['assessor_id'] ?? 0);
        $row['user_id'] = isset($row['user_id']) ? (int)$row['user_id'] : null;
        $row['is_active'] = (int)($row['is_active'] ?? 0);
        $row['mapped_facilities'] = (int)($row['mapped_facilities'] ?? 0);
        return $row;
    }

    private function facility(int $facId): ?array
    {
        return $this->row(
            "SELECT fac_id, NIN_no, fac_name, state_name, division, Dist_Name, Block_Name,
                    Health_facilty_type, lat, longit
             FROM facilities WHERE fac_id = ? LIMIT 1",
            'i',
            [$facId]
        );
    }

    private function ensureTables(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS assessor_master (
                assessor_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                assessor_code VARCHAR(80) NOT NULL UNIQUE,
                assessor_name VARCHAR(500) NOT NULL,
                designation VARCHAR(120) NULL,
                mobile_no VARCHAR(500) NULL,
                mail_id VARCHAR(500) NULL,
                state_id INT NULL,
                division_id INT NULL,
                dist_id INT NULL,
                block_id INT NULL,
                is_active TINYINT NOT NULL DEFAULT 1,
                created_by INT NULL,
                updated_by INT NULL,
                created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_assessor_user (user_id),
                KEY idx_assessor_status (is_active)
            )
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS assessor_facility_mapping (
                mapping_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                assessor_id BIGINT NOT NULL,
                fac_id INT NOT NULL,
                fac_nin BIGINT NULL,
                assignment_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                assigned_from DATE NULL,
                assigned_to DATE NULL,
                last_assessment_id BIGINT NULL,
                assigned_by INT NULL,
                remarks TEXT NULL,
                created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_assessor_facility (assessor_id, fac_id),
                KEY idx_mapping_assessor_status (assessor_id, assignment_status),
                KEY idx_mapping_facility (fac_id, fac_nin)
            )
        ");

        $this->ensureAssessmentColumn('assigned_assessor_id', 'BIGINT NULL');
        $this->ensureAssessmentColumn('assessment_source', "VARCHAR(30) NULL");
        $this->ensureUserColumnDefinition('f_name', 'TEXT NULL');
        $this->ensureUserColumnDefinition('m_name', 'TEXT NULL');
        $this->ensureUserColumnDefinition('l_name', 'TEXT NULL');
        $this->ensureUserColumnDefinition('mail_id', 'TEXT NULL');
        $this->ensureUserColumnDefinition('mob_no', 'TEXT NULL');
        $this->ensureUserColumnDefinition('user_type', 'VARCHAR(30) NULL');
        $this->ensureUserColumn('password_must_change', 'TINYINT NOT NULL DEFAULT 0');
        $this->ensureUserColumn('password_changed_on', 'TIMESTAMP NULL');
    }

    private function ensureSchemaOnce(): void
    {
        $marker = dirname(__DIR__) . '/../' . self::SCHEMA_MARKER;

        if (is_file($marker)) {
            return;
        }

        $this->ensureTables();

        $dir = dirname($marker);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($marker, date('c') . PHP_EOL);
    }

    private function ensureAssessmentColumn(string $column, string $definition): void
    {
        $exists = $this->row(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_master' AND COLUMN_NAME = ?",
            's',
            [$column]
        );

        if (!$exists) {
            $this->db->query("ALTER TABLE assessment_master ADD COLUMN {$column} {$definition}");
        }
    }

    private function ensureUserColumn(string $column, string $definition): void
    {
        if (!$this->tableExists('s_user') || $this->columnExists('s_user', $column)) {
            return;
        }

        $this->db->query("ALTER TABLE s_user ADD COLUMN {$column} {$definition}");
        unset($this->columnCache['s_user']);
    }

    private function ensureUserColumnDefinition(string $column, string $definition): void
    {
        if (!$this->tableExists('s_user')) {
            return;
        }

        if ($this->columnExists('s_user', $column)) {
            if ($this->userColumnSupportsDefinition($column, $definition)) {
                return;
            }

            $this->db->query("ALTER TABLE s_user MODIFY {$column} {$definition}");
            unset($this->columnCache['s_user']);
            return;
        }

        $this->db->query("ALTER TABLE s_user ADD COLUMN {$column} {$definition}");
        unset($this->columnCache['s_user']);
    }

    private function userColumnSupportsDefinition(string $column, string $definition): bool
    {
        $row = $this->tableColumns('s_user')[$column] ?? null;

        if (!$row) {
            return false;
        }

        $dataType = strtolower((string)($row['DATA_TYPE'] ?? ''));
        $length = (int)($row['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
        $required = strtoupper($definition);

        if (str_contains($required, 'TEXT')) {
            return in_array($dataType, ['text', 'mediumtext', 'longtext'], true);
        }

        if (preg_match('/VARCHAR\((\d+)\)/i', $definition, $matches)) {
            $requiredLength = (int)$matches[1];
            return in_array($dataType, ['text', 'mediumtext', 'longtext'], true)
                || ($dataType === 'varchar' && $length >= $requiredLength);
        }

        return true;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $row = $this->row(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            's',
            [$table]
        );

        $this->tableExistsCache[$table] = $row !== null;
        return $this->tableExistsCache[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        $columns = $this->tableColumns($table);
        return array_key_exists($column, $columns);
    }

    private function departmentStatusAssessmentColumn(): string
    {
        return $this->columnExists('assessment_department_status', 'assessment_id')
            ? 'assessment_id'
            : 'ass_period_id';
    }

    private function tableColumns(string $table): array
    {
        if (array_key_exists($table, $this->columnCache)) {
            return $this->columnCache[$table];
        }

        $rows = $this->rows(
            "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            's',
            [$table]
        );
        $columns = [];

        foreach ($rows as $row) {
            $columns[(string)$row['COLUMN_NAME']] = $row;
        }

        $this->columnCache[$table] = $columns;
        return $columns;
    }

    private function nullableInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int)$value;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function row(string $sql, string $types = '', array $values = []): ?array
    {
        $rows = $this->rows($sql, $types, $values);
        return $rows[0] ?? null;
    }

    private function rows(string $sql, string $types = '', array $values = []): array
    {
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException('Query prepare failed: ' . $this->db->error);
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function scalar(string $sql, string $types = '', array $values = []): int
    {
        $row = $this->row($sql, $types, $values);
        return (int)array_values($row ?? [0])[0];
    }

    private function execute(string $sql, string $types, array $values): void
    {
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException('Query prepare failed: ' . $this->db->error);
        }

        $stmt->bind_param($types, ...$values);

        if (!$stmt->execute()) {
            throw new RuntimeException('Query failed: ' . $stmt->error);
        }
    }
}
