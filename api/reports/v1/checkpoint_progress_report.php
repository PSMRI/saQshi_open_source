<?php

/**
 * checkpoint_progress_report.php
 * -------------------------------------------------------
 * Download checkpoint progress report in checklist Excel format.
 *
 * Method:
 * GET /api/reports/v1/checkpoint_progress_report.php?assessment_id=1
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../core/Crypto.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

/**
 * Handles scorecard normalize processing for this API workflow.
 */
function scorecardNormalize(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim((string)$value);

    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

/**
 * Returns the assessment reference column used by department status table.
 */
function scorecardDepartmentStatusColumn(mysqli $con): string
{
    $result = $con->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'assessment_department_status'
          AND COLUMN_NAME = 'assessment_id'
        LIMIT 1
    ");

    return ($result && $result->fetch_assoc()) ? 'assessment_id' : 'ass_period_id';
}

/**
 * Handles scorecard facility processing for this API workflow.
 */
function scorecardFacility(int $facId): array
{
    $facilityJsonPath = __DIR__ . '/../../config/masters/facilities.json';

    if (!file_exists($facilityJsonPath)) {
        return [
            'fac_type_id' => 0,
            'fac_name' => '',
            'facilities_type' => ''
        ];
    }

    $states = json_decode(file_get_contents($facilityJsonPath), true);

    if (!is_array($states)) {
        return [
            'fac_type_id' => 0,
            'fac_name' => '',
            'facilities_type' => ''
        ];
    }

    foreach ($states as $state) {
        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {
                        if ((int)($facility['fac_id'] ?? 0) === $facId) {
                            return [
                                'fac_type_id' => (int)($facility['fac_type_id'] ?? 0),
                                'fac_name' => (string)($facility['fac_name'] ?? ''),
                                'facilities_type' => (string)($facility['facilities_type'] ?? '')
                            ];
                        }
                    }
                }
            }
        }
    }

    return [
        'fac_type_id' => 0,
        'fac_name' => '',
        'facilities_type' => ''
    ];
}

/**
 * Handles scorecard shared strings processing for this API workflow.
 */
function scorecardSharedStrings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');

    if ($xml === false) {
        return [];
    }

    $sx = simplexml_load_string($xml);

    if (!$sx) {
        return [];
    }

    $strings = [];

    foreach ($sx->si as $si) {
        $text = '';

        if (isset($si->t)) {
            $text = (string)$si->t;
        } else {
            foreach ($si->r as $run) {
                $text .= (string)$run->t;
            }
        }

        $strings[] = $text;
    }

    return $strings;
}

/**
 * Handles scorecard cell text processing for this API workflow.
 */
function scorecardCellText(DOMXPath $xpath, array $sharedStrings, string $cellRef): string
{
    $cell = $xpath->query("//x:c[@r='" . $cellRef . "']")->item(0);

    if (!$cell instanceof DOMElement) {
        return '';
    }

    $type = $cell->getAttribute('t');

    if ($type === 's') {
        $value = $xpath->query('x:v', $cell)->item(0);
        $index = $value ? (int)$value->nodeValue : -1;
        return $sharedStrings[$index] ?? '';
    }

    if ($type === 'inlineStr') {
        $text = $xpath->query('x:is/x:t', $cell)->item(0);
        return $text ? (string)$text->nodeValue : '';
    }

    $value = $xpath->query('x:v', $cell)->item(0);
    return $value ? (string)$value->nodeValue : '';
}

/**
 * Handles scorecard column index processing for this API workflow.
 */
function scorecardColumnIndex(string $cellRef): int
{
    preg_match('/^[A-Z]+/', $cellRef, $matches);
    $letters = $matches[0] ?? '';
    $index = 0;

    foreach (str_split($letters) as $letter) {
        $index = ($index * 26) + (ord($letter) - 64);
    }

    return $index;
}

/**
 * Handles scorecard set cell processing for this API workflow.
 */
function scorecardSetCell(
    DOMDocument $dom,
    DOMXPath $xpath,
    DOMElement $row,
    string $cellRef,
    string|float|int|null $value,
    bool $numeric = false
): void {
    $cell = $xpath->query("x:c[@r='" . $cellRef . "']", $row)->item(0);

    if (!$cell instanceof DOMElement) {
        $cell = $dom->createElementNS(
            'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'c'
        );
        $cell->setAttribute('r', $cellRef);

        $newIndex = scorecardColumnIndex($cellRef);
        $inserted = false;

        foreach ($xpath->query('x:c', $row) as $existing) {
            if (scorecardColumnIndex($existing->getAttribute('r')) > $newIndex) {
                $row->insertBefore($cell, $existing);
                $inserted = true;
                break;
            }
        }

        if (!$inserted) {
            $row->appendChild($cell);
        }
    }

    while ($cell->firstChild) {
        $cell->removeChild($cell->firstChild);
    }

    if ($value === null || $value === '') {
        $cell->removeAttribute('t');
        return;
    }

    if ($numeric) {
        $cell->removeAttribute('t');
        $cell->appendChild($dom->createElementNS(
            'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'v',
            (string)$value
        ));
        return;
    }

    $cell->setAttribute('t', 'inlineStr');
    $is = $dom->createElementNS(
        'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
        'is'
    );
    $text = $dom->createElementNS(
        'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
        't'
    );
    $text->appendChild($dom->createTextNode((string)$value));
    $is->appendChild($text);
    $cell->appendChild($is);
}

/**
 * Handles scorecard create row processing for this API workflow.
 */
function scorecardCreateRow(DOMDocument $dom, int $rowNo): DOMElement
{
    $row = $dom->createElementNS(
        'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
        'row'
    );
    $row->setAttribute('r', (string)$rowNo);
    return $row;
}

/**
 * Handles scorecard apply style processing for this API workflow.
 */
function scorecardApplyStyle(DOMXPath $xpath, DOMElement $row, string $cellRef, ?string $styleId): void
{
    if ($styleId === null || $styleId === '') {
        return;
    }

    $cell = $xpath->query("x:c[@r='" . $cellRef . "']", $row)->item(0);

    if ($cell instanceof DOMElement) {
        $cell->setAttribute('s', $styleId);
    }
}

/**
 * Handles scorecard template styles processing for this API workflow.
 */
function scorecardTemplateStyles(DOMXPath $xpath, array $rowMap, int $rowNo): array
{
    $styles = [];
    $row = $rowMap[$rowNo] ?? null;

    if (!$row instanceof DOMElement) {
        return $styles;
    }

    foreach ($xpath->query('x:c', $row) as $cell) {
        $ref = $cell->getAttribute('r');
        preg_match('/^[A-Z]+/', $ref, $matches);
        $column = $matches[0] ?? '';

        if ($column !== '') {
            $styles[$column] = $cell->getAttribute('s');
        }
    }

    return $styles;
}

/**
 * Handles scorecard set styled cell processing for this API workflow.
 */
function scorecardSetStyledCell(
    DOMDocument $dom,
    DOMXPath $xpath,
    DOMElement $row,
    int $rowNo,
    string $column,
    string|float|int|null $value,
    bool $numeric,
    array $styles
): void {
    $cellRef = $column . $rowNo;
    scorecardSetCell($dom, $xpath, $row, $cellRef, $value, $numeric);
    scorecardApplyStyle($xpath, $row, $cellRef, $styles[$column] ?? null);
}

/**
 * Handles scorecard set column width processing for this API workflow.
 */
function scorecardSetColumnWidth(DOMDocument $dom, DOMXPath $xpath, DOMElement $worksheet, string $column, float $width): void
{
    $index = scorecardColumnIndex($column);
    $cols = $xpath->query('x:cols', $worksheet)->item(0);

    if (!$cols instanceof DOMElement) {
        $cols = $dom->createElementNS(
            'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'cols'
        );
        $sheetData = $xpath->query('x:sheetData', $worksheet)->item(0);

        if ($sheetData instanceof DOMElement) {
            $worksheet->insertBefore($cols, $sheetData);
        } else {
            $worksheet->appendChild($cols);
        }
    }

    foreach ($xpath->query('x:col', $cols) as $col) {
        if ((int)$col->getAttribute('min') === $index && (int)$col->getAttribute('max') === $index) {
            $col->setAttribute('width', (string)$width);
            $col->setAttribute('customWidth', '1');
            return;
        }
    }

    $col = $dom->createElementNS(
        'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
        'col'
    );
    $col->setAttribute('min', (string)$index);
    $col->setAttribute('max', (string)$index);
    $col->setAttribute('width', (string)$width);
    $col->setAttribute('customWidth', '1');

    $inserted = false;

    foreach ($xpath->query('x:col', $cols) as $existing) {
        if ((int)$existing->getAttribute('min') > $index) {
            $cols->insertBefore($col, $existing);
            $inserted = true;
            break;
        }
    }

    if (!$inserted) {
        $cols->appendChild($col);
    }
}

/**
 * Handles scorecard remove merges processing for this API workflow.
 */
function scorecardRemoveMerges(DOMXPath $xpath, DOMElement $mergeCells, int $fromRow, int $toRow): void
{
    foreach (iterator_to_array($xpath->query('x:mergeCell', $mergeCells)) as $mergeCell) {
        $ref = $mergeCell->getAttribute('ref');
        preg_match_all('/\d+/', $ref, $matches);
        $rows = array_map('intval', $matches[0] ?? []);

        if (empty($rows)) {
            continue;
        }

        if (max($rows) >= $fromRow && min($rows) <= $toRow) {
            $mergeCells->removeChild($mergeCell);
        }
    }
}

/**
 * Handles scorecard add merge processing for this API workflow.
 */
function scorecardAddMerge(DOMDocument $dom, DOMElement $mergeCells, string $ref): void
{
    $mergeCell = $dom->createElementNS(
        'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
        'mergeCell'
    );
    $mergeCell->setAttribute('ref', $ref);
    $mergeCells->appendChild($mergeCell);
}

/**
 * Handles scorecard remove columns after processing for this API workflow.
 */
function scorecardRemoveColumnsAfter(DOMXPath $xpath, DOMElement $sheetData, int $lastColumnIndex): void
{
    foreach ($xpath->query('x:row', $sheetData) as $row) {
        foreach (iterator_to_array($xpath->query('x:c', $row)) as $cell) {
            if (scorecardColumnIndex($cell->getAttribute('r')) > $lastColumnIndex) {
                $row->removeChild($cell);
            }
        }
    }
}

try {
    $facId = SessionManager::facilityId();
    $userId = SessionManager::userId();

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if ($userId <= 0) {
        Response::error('User session not found');
    }

    $assessmentId = isset($_GET['assessment_id'])
        ? (int)$_GET['assessment_id']
        : 0;

    if ($assessmentId <= 0) {
        Response::validation([
            'assessment_id' => 'assessment_id is required'
        ]);
    }

    $sqlAssessment = "
        SELECT assessment_id, assessment_name, framework_code, fac_id_fk, start_date, end_date, status
        FROM assessment_master
        WHERE assessment_id = ?
          AND fac_id_fk = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlAssessment);

    if (!$stmt) {
        Response::serverError('Assessment prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();

    if (!$assessment) {
        Response::error('Assessment not found for this facility');
    }

    $facility = scorecardFacility($facId);
    $frameworkCode = $assessment['framework_code'] ?: 'saqshi-nqas';
    $engine = FrameworkEngine::load($frameworkCode);

    $checklistRows = [];

    $activeDeptIds = [];
    $departmentStatusColumn = scorecardDepartmentStatusColumn($con);

    $sqlActiveDept = "
        SELECT dept_id
        FROM assessment_department_status
        WHERE {$departmentStatusColumn} = ?
          AND fac_id_fk = ?
          AND is_active = 1
    ";

    $stmt = $con->prepare($sqlActiveDept);

    if ($stmt) {
        $stmt->bind_param('ii', $assessmentId, $facId);
        $stmt->execute();
        $activeDeptResult = $stmt->get_result();

        while ($row = $activeDeptResult->fetch_assoc()) {
            $activeDeptIds[(int)$row['dept_id']] = true;
        }
    }

    foreach ($engine->getDepartments((int)$facility['fac_type_id']) as $department) {
        $deptId = (int)($department['fac_dept_id'] ?? 0);
        $deptName = (string)($department['dept_name'] ?? '');

        foreach (($department['concerns'] ?? []) as $concern) {
            $concernName = trim((string)(
                ($concern['concern_name'] ?? '') .
                (
                    !empty($concern['concern_des'])
                        ? ': ' . $concern['concern_des']
                        : ''
                )
            ));

            $checklistRows[] = [
                'type' => 'area',
                'reference' => '',
                'standard' => '',
                'measurable' => $concernName ?: $deptName,
                'checkpoint' => '',
                'verification' => '',
                'method' => '',
                'checkpoint_id' => 0
            ];

            foreach (($concern['subtypes'] ?? []) as $subtype) {
                $standardRef = (string)($subtype['Reference_No'] ?? '');
                $standardText = (string)($subtype['area_of_con_subtypedeatils'] ?? '');

                if ($standardRef !== '' || $standardText !== '') {
                    $checklistRows[] = [
                        'type' => 'standard',
                        'reference' => $standardRef,
                        'standard' => $standardText,
                        'measurable' => '',
                        'checkpoint' => '',
                        'verification' => '',
                        'method' => '',
                        'checkpoint_id' => 0
                    ];
                }

                foreach (($subtype['checkpoints'] ?? []) as $checkpoint) {
                    $checkpointId = (int)($checkpoint['csqa_id'] ?? 0);
                    $method = $checkpoint['Assessment_Method'] ?? '';

                    if (is_array($method)) {
                        $method = implode('/', array_filter(array_map('strval', $method)));
                    }

                    if ($checkpointId <= 0) {
                        continue;
                    }

                    $checklistRows[] = [
                        'type' => 'checkpoint',
                        'reference' => (string)($checkpoint['csqa_reference_id'] ?? ''),
                        'standard' => '',
                        'measurable' => (string)($checkpoint['Measurable_Element'] ?? ''),
                        'checkpoint' => (string)($checkpoint['Checkpoint'] ?? ''),
                        'verification' => (string)($checkpoint['Means_of_Verification'] ?? ''),
                        'method' => (string)$method,
                        'checkpoint_id' => $checkpointId
                    ];
                }
            }
        }
    }

    $responseMap = [];

    $sqlResponses = "
        SELECT checkpoint_id, response_value, score, remarks
        FROM assessment_response
        WHERE assessment_id = ?
    ";

    $stmt = $con->prepare($sqlResponses);

    if (!$stmt) {
        Response::serverError('Response prepare failed: ' . $con->error);
    }

    $stmt->bind_param('i', $assessmentId);
    $stmt->execute();
    $responses = $stmt->get_result();

    while ($row = $responses->fetch_assoc()) {
        $responseMap[(int)$row['checkpoint_id']] = [
            'response_value' => $row['response_value'],
            'score' => (float)$row['score'],
            'remarks' => (string)($row['remarks'] ?? ''),
            'revised_score' => null
        ];
    }

    $legacyTable = $con->query("SHOW TABLES LIKE 'assessment_cycle_response'");

    if ($legacyTable && $legacyTable->num_rows > 0) {
        $sqlLegacyResponses = "
            SELECT checkpoint_id, response_value, score, remarks
            FROM assessment_cycle_response
            WHERE cycle_id = ?
        ";

        $stmt = $con->prepare($sqlLegacyResponses);

        if ($stmt) {
            $stmt->bind_param('i', $assessmentId);
            $stmt->execute();
            $legacyResponses = $stmt->get_result();

            while ($row = $legacyResponses->fetch_assoc()) {
                $checkpointId = (int)$row['checkpoint_id'];

                if (isset($responseMap[$checkpointId])) {
                    continue;
                }

                $responseMap[$checkpointId] = [
                    'response_value' => $row['response_value'],
                    'score' => (float)$row['score'],
                    'remarks' => (string)($row['remarks'] ?? ''),
                    'revised_score' => null
                ];
            }
        }
    }

    $actionPlanMap = [];

    $sqlActionPlans = "
        SELECT
            checkpoint_id,
            system_action_plan,
            user_action_plan,
            responsible_person,
            target_date,
            status,
            revised_score,
            closure_remarks
        FROM assessment_action_plan
        WHERE assessment_id = ?
    ";

    $stmt = $con->prepare($sqlActionPlans);

    if ($stmt) {
        $stmt->bind_param('i', $assessmentId);
        $stmt->execute();
        $actionPlanResult = $stmt->get_result();

        while ($row = $actionPlanResult->fetch_assoc()) {
            $checkpointId = (int)$row['checkpoint_id'];
            $actionPlanText = trim((string)($row['user_action_plan'] ?? ''));

            if ($actionPlanText === '') {
                $actionPlanText = trim((string)($row['system_action_plan'] ?? ''));
            }

            $actionPlanMap[$checkpointId] = [
                'action_plan' => $actionPlanText,
                'responsible_person' => (string)($row['responsible_person'] ?? ''),
                'target_date' => (string)($row['target_date'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'revised_score' => $row['revised_score'] !== null ? (float)$row['revised_score'] : null,
                'closure_remarks' => (string)($row['closure_remarks'] ?? '')
            ];

            if ($row['revised_score'] !== null) {
                if (!isset($responseMap[$checkpointId])) {
                    $responseMap[$checkpointId] = [
                        'response_value' => '',
                        'score' => 0.0,
                        'remarks' => '',
                        'revised_score' => (float)$row['revised_score']
                    ];
                    continue;
                }

                $responseMap[$checkpointId]['revised_score'] = (float)$row['revised_score'];
            }
        }
    }

    $assessor = [
        'assessor_name' => '',
        'assessee_name' => '',
        'assessment_date' => '',
        'assessment_type' => ''
    ];

    $sqlAssessor = "
        SELECT assessor_name, assessee_name, assessment_date, assessment_type
        FROM assessment_assessor_info
        WHERE assessment_id = ?
          AND fac_id_fk = ?
        ORDER BY info_id ASC
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlAssessor);

    if ($stmt) {
        $stmt->bind_param('ii', $assessmentId, $facId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            $assessor = Crypto::decryptFields($row, ['assessor_name', 'assessee_name']);
        }
    }

    $template = __DIR__ . '/../../templates/report_revise_formate.xlsx';

    if (!file_exists($template)) {
        Response::serverError('Score card template not found');
    }

    $tmpDir = dirname(__DIR__, 3) . '/uploads/reports';

    if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true)) {
        Response::serverError('Unable to create report folder');
    }

    $tmpFile = $tmpDir . '/progress_checklist_' . $assessmentId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.xlsx';

    if (!copy($template, $tmpFile)) {
        Response::serverError('Unable to prepare score card template');
    }

    $zip = new ZipArchive();

    if ($zip->open($tmpFile) !== true) {
        Response::serverError('Unable to open score card workbook');
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

    if ($sheetXml === false) {
        $zip->close();
        Response::serverError('Score card worksheet not found');
    }

    $sharedStrings = scorecardSharedStrings($zip);
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    $dom->loadXML($sheetXml);

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $sheetData = $xpath->query('//x:sheetData')->item(0);

    if (!$sheetData instanceof DOMElement) {
        $zip->close();
        Response::serverError('Score card worksheet data not found');
    }

    $rowMap = [];

    foreach ($xpath->query('x:row', $sheetData) as $row) {
        $rowMap[(int)$row->getAttribute('r')] = $row;
    }

    $mergeCells = $xpath->query('//x:mergeCells')->item(0);

    if ($mergeCells instanceof DOMElement) {
        scorecardRemoveMerges($xpath, $mergeCells, 1, 8);
        scorecardAddMerge($dom, $mergeCells, 'A1:J1');
        scorecardAddMerge($dom, $mergeCells, 'A2:F2');
        scorecardAddMerge($dom, $mergeCells, 'G2:J2');
        scorecardAddMerge($dom, $mergeCells, 'A3:B3');
        scorecardAddMerge($dom, $mergeCells, 'C3:D3');
        scorecardAddMerge($dom, $mergeCells, 'E3:F3');
        scorecardAddMerge($dom, $mergeCells, 'H3:I3');
        scorecardAddMerge($dom, $mergeCells, 'A4:B4');
        scorecardAddMerge($dom, $mergeCells, 'C4:D4');
        scorecardAddMerge($dom, $mergeCells, 'E4:F4');
        scorecardAddMerge($dom, $mergeCells, 'H4:I4');
        scorecardAddMerge($dom, $mergeCells, 'A5:B5');
        scorecardAddMerge($dom, $mergeCells, 'C5:D5');
        scorecardAddMerge($dom, $mergeCells, 'E5:F5');
        scorecardAddMerge($dom, $mergeCells, 'H5:I5');
        scorecardAddMerge($dom, $mergeCells, 'A6:J6');
        scorecardAddMerge($dom, $mergeCells, 'A7:J7');
    }

    foreach ([1, 2, 3, 4, 5] as $rowNo) {
        if (!isset($rowMap[$rowNo])) {
            continue;
        }

        if ($rowNo === 1) {
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'A1', 'National Quality Assurance Standards', false);
        } elseif ($rowNo === 2) {
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'A2', 'Facility Type: ' . ($facility['facilities_type'] ?: '-'), false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'G2', 'Reports: Actionplan and Gap Closer', false);
        } elseif ($rowNo === 3) {
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'A3', 'Name of Facility', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'C3', $facility['fac_name'] ?: '-', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'E3', 'Date of Assessment', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'G3', $assessor['assessment_date'] ?: $assessment['start_date'], false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'H3', 'Total Marks before Gap closer', false);
        } elseif ($rowNo === 4) {
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'A4', 'Name of Assessors', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'C4', $assessor['assessor_name'] ?: '-', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'E4', 'Name of Assessee', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'G4', $assessor['assessee_name'] ?: '-', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'H4', 'Total Marks after Gap closer', false);
        } elseif ($rowNo === 5) {
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'A5', 'Type of Assessment (Internal/ State/External)', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'C5', $assessor['assessment_type'] ?: '-', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'E5', 'Action Plan submitted date', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'G5', date('Y-m-d'), false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'H5', 'Progress %', false);
        }
    }

    $worksheet = $dom->documentElement;
    $areaStyles = scorecardTemplateStyles($xpath, $rowMap, 7);
    $separatorStyles = scorecardTemplateStyles($xpath, $rowMap, 6);
    $standardStyles = scorecardTemplateStyles($xpath, $rowMap, 9);
    $dataStyles = scorecardTemplateStyles($xpath, $rowMap, 10);
    $fallbackStyles = scorecardTemplateStyles($xpath, $rowMap, 8);
    $headerStyles = $fallbackStyles;

    foreach (['I', 'J'] as $column) {
        $headerStyles[$column] = $headerStyles['H'] ?? ($headerStyles['G'] ?? null);
    }

    $titleStyles = scorecardTemplateStyles($xpath, $rowMap, 1);
    $subtitleStyles = scorecardTemplateStyles($xpath, $rowMap, 2);

    foreach (['I', 'J'] as $column) {
        $titleStyles[$column] = $titleStyles['H'] ?? ($titleStyles['A'] ?? null);
        $subtitleStyles[$column] = $subtitleStyles['H'] ?? ($subtitleStyles['A'] ?? null);
    }

    $metaLabelStyle = $fallbackStyles['B'] ?? ($fallbackStyles['A'] ?? null);
    $metaValueStyle = $fallbackStyles['C'] ?? ($fallbackStyles['B'] ?? null);
    $summaryStyle = $standardStyles['B'] ?? ($fallbackStyles['B'] ?? null);
    $areaFillStyle = $areaStyles['A'] ?? ($areaStyles['C'] ?? ($fallbackStyles['B'] ?? null));
    $separatorFillStyle = $separatorStyles['B'] ?? ($separatorStyles['A'] ?? null);

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $column) {
        $areaStyles[$column] = $areaFillStyle;
        $separatorStyles[$column] = $separatorFillStyle;
    }

    if (isset($rowMap[6])) {
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $column) {
            scorecardSetStyledCell($dom, $xpath, $rowMap[6], 6, $column, '', false, $separatorStyles);
        }
    }

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $column) {
        if (isset($rowMap[1])) {
            scorecardSetStyledCell($dom, $xpath, $rowMap[1], 1, $column, $column === 'A' ? 'National Quality Assurance Standards' : '', false, $titleStyles);
        }

        if (isset($rowMap[2])) {
            $value = '';

            if ($column === 'A') {
                $value = 'Facility Type: ' . ($facility['facilities_type'] ?: '-');
            } elseif ($column === 'G') {
                $value = 'Reports: Actionplan and Gap Closer';
            }

            scorecardSetStyledCell($dom, $xpath, $rowMap[2], 2, $column, $value, false, $subtitleStyles);
        }
    }

    foreach ([3, 4, 5] as $metaRowNo) {
        if (!isset($rowMap[$metaRowNo])) {
            continue;
        }

        foreach (['A', 'B', 'E', 'F', 'H', 'I'] as $column) {
            scorecardSetStyledCell($dom, $xpath, $rowMap[$metaRowNo], $metaRowNo, $column, scorecardCellText($xpath, $sharedStrings, $column . $metaRowNo), false, [$column => $column === 'H' || $column === 'I' ? $summaryStyle : $metaLabelStyle]);
        }

        foreach (['C', 'D', 'G', 'J'] as $column) {
            scorecardSetStyledCell($dom, $xpath, $rowMap[$metaRowNo], $metaRowNo, $column, scorecardCellText($xpath, $sharedStrings, $column . $metaRowNo), false, [$column => $column === 'J' ? $summaryStyle : $metaValueStyle]);
        }
    }

    if (isset($rowMap[8])) {
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'A', 'Reference No', false, $headerStyles);
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'B', 'Measurable Elements', false, $headerStyles);
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'C', 'Checkpoints', false, $headerStyles);
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'D', 'Means of Verification', false, $headerStyles);
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'E', 'Assessment Method', false, $headerStyles);
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'F', 'User Compliance', false, $headerStyles);
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'G', 'Revised Score', false, $headerStyles);
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'H', 'Action Plan', false, $headerStyles);
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'I', 'Responsible Person', false, $headerStyles);
        scorecardSetStyledCell($dom, $xpath, $rowMap[8], 8, 'J', 'Gap Status / Remarks', false, $headerStyles);
    }

    if ($worksheet instanceof DOMElement) {
        scorecardSetColumnWidth($dom, $xpath, $worksheet, 'B', 24);
        scorecardSetColumnWidth($dom, $xpath, $worksheet, 'C', 54);
        scorecardSetColumnWidth($dom, $xpath, $worksheet, 'D', 22);
        scorecardSetColumnWidth($dom, $xpath, $worksheet, 'E', 20);
        scorecardSetColumnWidth($dom, $xpath, $worksheet, 'F', 14);
        scorecardSetColumnWidth($dom, $xpath, $worksheet, 'G', 13);
        scorecardSetColumnWidth($dom, $xpath, $worksheet, 'H', 30);
        scorecardSetColumnWidth($dom, $xpath, $worksheet, 'I', 18);
        scorecardSetColumnWidth($dom, $xpath, $worksheet, 'J', 32);
    }

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $column) {
        if (empty($dataStyles[$column])) {
            $dataStyles[$column] = $fallbackStyles[$column] ?? ($fallbackStyles['H'] ?? null);
        }
    }

    $standardFillStyle = $standardStyles['B'] ?? '7';

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $column) {
        $standardStyles[$column] = $standardFillStyle;
    }

    foreach (iterator_to_array($xpath->query('x:row', $sheetData)) as $row) {
        if ((int)$row->getAttribute('r') >= 9) {
            $sheetData->removeChild($row);
        }
    }

    $mergeCells = $xpath->query('//x:mergeCells')->item(0);

    if ($mergeCells instanceof DOMElement) {
        foreach (iterator_to_array($xpath->query('x:mergeCell', $mergeCells)) as $mergeCell) {
            $ref = $mergeCell->getAttribute('ref');
            preg_match_all('/\d+/', $ref, $matches);
            $rows = array_map('intval', $matches[0] ?? []);

            if (!empty($rows) && max($rows) >= 9) {
                $mergeCells->removeChild($mergeCell);
            }
        }
    }

    $totalBeforeGapCloser = 0.0;
    $totalAfterGapCloser = 0.0;
    $totalPossibleScore = 0.0;
    $rowNo = 9;

    foreach ($checklistRows as $item) {
        $row = scorecardCreateRow($dom, $rowNo);
        $sheetData->appendChild($row);

        if ($item['type'] === 'area') {
            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $column) {
                scorecardSetStyledCell($dom, $xpath, $row, $rowNo, $column, $column === 'A' ? $item['measurable'] : '', false, $areaStyles);
            }

            if ($mergeCells instanceof DOMElement) {
                $mergeCell = $dom->createElementNS(
                    'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
                    'mergeCell'
                );
                $mergeCell->setAttribute('ref', 'A' . $rowNo . ':J' . $rowNo);
                $mergeCells->appendChild($mergeCell);
            }

            $rowNo++;
            continue;
        }

        if ($item['type'] === 'standard') {
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'A', $item['reference'], false, $standardStyles);
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'B', $item['standard'], false, $standardStyles);
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'C', '', false, $standardStyles);
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'D', '', false, $standardStyles);
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'E', '', false, $standardStyles);
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'F', '', false, $standardStyles);
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'G', '', false, $standardStyles);
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'H', '', false, $standardStyles);
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'I', '', false, $standardStyles);
            scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'J', '', false, $standardStyles);

            if ($mergeCells instanceof DOMElement) {
                $mergeCell = $dom->createElementNS(
                    'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
                    'mergeCell'
                );
                $mergeCell->setAttribute('ref', 'B' . $rowNo . ':J' . $rowNo);
                $mergeCells->appendChild($mergeCell);
            }

            $rowNo++;
            continue;
        }

        $response = $responseMap[(int)$item['checkpoint_id']] ?? null;
        $actionPlan = $actionPlanMap[(int)$item['checkpoint_id']] ?? null;
        $userScore = null;
        $revisedScore = null;

        if ($response) {
            $rawScore = $response['response_value'];
            $userScore = is_numeric($rawScore)
                ? (int)$rawScore
                : (int)$response['score'];
            $revisedScore = $response['revised_score'] !== null
                ? (float)$response['revised_score']
                : null;
        }

        if ($actionPlan && $actionPlan['revised_score'] !== null) {
            $revisedScore = $actionPlan['revised_score'];
        }

        $totalPossibleScore += 2;

        if ($userScore !== null) {
            $totalBeforeGapCloser += (float)$userScore;
        }

        $totalAfterGapCloser += $revisedScore !== null
            ? (float)$revisedScore
            : (float)($userScore ?? 0);

        $remarks = $response ? trim((string)$response['remarks']) : '';
        $statusText = $actionPlan ? trim((string)$actionPlan['status']) : '';
        $closureRemarks = $actionPlan ? trim((string)$actionPlan['closure_remarks']) : '';
        $gapStatusParts = [];

        if ($statusText !== '') {
            $gapStatusParts[] = 'Status: ' . $statusText;
        } elseif ($userScore !== null && (float)$userScore < 2) {
            $gapStatusParts[] = 'Status: OPEN GAP';
        } elseif ($userScore !== null) {
            $gapStatusParts[] = 'Status: NO GAP';
        }

        if ($closureRemarks !== '') {
            $gapStatusParts[] = 'Closure: ' . $closureRemarks;
        }

        if ($actionPlan && trim((string)$actionPlan['target_date']) !== '') {
            $gapStatusParts[] = 'Target: ' . $actionPlan['target_date'];
        }

        if ($remarks !== '') {
            $gapStatusParts[] = 'Remarks: ' . $remarks;
        }

        $gapStatus = implode(' | ', $gapStatusParts);

        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'A', $item['reference'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'B', $item['measurable'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'C', $item['checkpoint'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'D', $item['verification'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'E', $item['method'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'F', $userScore, true, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'G', $revisedScore, true, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'H', $actionPlan['action_plan'] ?? '', false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'I', $actionPlan['responsible_person'] ?? '', false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'J', $gapStatus, false, $dataStyles);

        $rowNo++;
    }

    $progressPercent = $totalPossibleScore > 0
        ? round((($totalAfterGapCloser - $totalBeforeGapCloser) / $totalPossibleScore) * 100, 2)
        : 0;

    if (isset($rowMap[3])) {
        scorecardSetStyledCell($dom, $xpath, $rowMap[3], 3, 'J', $totalBeforeGapCloser, true, ['J' => $summaryStyle]);
    }

    if (isset($rowMap[4])) {
        scorecardSetStyledCell($dom, $xpath, $rowMap[4], 4, 'J', $totalAfterGapCloser, true, ['J' => $summaryStyle]);
    }

    if (isset($rowMap[5])) {
        scorecardSetStyledCell($dom, $xpath, $rowMap[5], 5, 'J', $progressPercent, true, ['J' => $summaryStyle]);
    }

    if ($mergeCells instanceof DOMElement) {
        $mergeCells->setAttribute('count', (string)$xpath->query('x:mergeCell', $mergeCells)->length);
    }

    scorecardRemoveColumnsAfter($xpath, $sheetData, 10);

    $dimension = $xpath->query('//x:dimension')->item(0);

    if ($dimension instanceof DOMElement) {
        $dimension->setAttribute('ref', 'A1:J' . max(10, $rowNo - 1));
    }

    $zip->addFromString('xl/worksheets/sheet1.xml', $dom->saveXML());
    $zip->close();

    $filename = 'checkpoint_progress_assessment_' . $assessmentId . '.xlsx';

    if (!headers_sent()) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    readfile($tmpFile);
    @unlink($tmpFile);
    exit;

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
