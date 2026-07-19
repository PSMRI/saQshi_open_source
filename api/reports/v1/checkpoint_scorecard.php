<?php

/**
 * checkpoint_scorecard.php
 * -------------------------------------------------------
 * Download checkpoint score card in standard Excel format.
 *
 * Method:
 * GET /api/reports/v1/checkpoint_scorecard.php?assessment_id=1
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

    $sqlRevisedScores = "
        SELECT checkpoint_id, revised_score, closure_remarks
        FROM assessment_action_plan
        WHERE assessment_id = ?
          AND revised_score IS NOT NULL
    ";

    $stmt = $con->prepare($sqlRevisedScores);

    if ($stmt) {
        $stmt->bind_param('i', $assessmentId);
        $stmt->execute();
        $revisedResult = $stmt->get_result();

        while ($row = $revisedResult->fetch_assoc()) {
            $checkpointId = (int)$row['checkpoint_id'];

            if (!isset($responseMap[$checkpointId])) {
                $responseMap[$checkpointId] = [
                    'response_value' => $row['revised_score'],
                    'score' => (float)$row['revised_score'],
                    'remarks' => '',
                    'revised_score' => (float)$row['revised_score']
                ];
                continue;
            }

            $responseMap[$checkpointId]['revised_score'] = (float)$row['revised_score'];

            if (!empty($row['closure_remarks'])) {
                $responseMap[$checkpointId]['remarks'] = (string)$row['closure_remarks'];
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

    $tmpFile = $tmpDir . '/scorecard_' . $assessmentId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.xlsx';

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

    foreach ([2, 3, 4, 5] as $rowNo) {
        if (!isset($rowMap[$rowNo])) {
            continue;
        }

        if ($rowNo === 2) {
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'A2', 'Facility Type: ' . ($facility['facilities_type'] ?: '-'), false);
        } elseif ($rowNo === 3) {
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'G3', $facility['fac_name'] ?: '-', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'I3', $assessor['assessment_date'] ?: $assessment['start_date'], false);
        } elseif ($rowNo === 4) {
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'D4', $assessor['assessor_name'] ?: '-', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'F4', $assessor['assessee_name'] ?: '-', false);
        } elseif ($rowNo === 5) {
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'D5', $assessor['assessment_type'] ?: '-', false);
            scorecardSetCell($dom, $xpath, $rowMap[$rowNo], 'F5', date('Y-m-d'), false);
        }
    }

    $areaStyles = scorecardTemplateStyles($xpath, $rowMap, 7);
    $standardStyles = scorecardTemplateStyles($xpath, $rowMap, 9);
    $dataStyles = scorecardTemplateStyles($xpath, $rowMap, 10);
    $fallbackStyles = scorecardTemplateStyles($xpath, $rowMap, 8);

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $column) {
        if (empty($dataStyles[$column])) {
            $dataStyles[$column] = $fallbackStyles[$column] ?? null;
        }
    }

    $areaFillStyle = $areaStyles['A'] ?? ($areaStyles['C'] ?? null);
    $standardFillStyle = $standardStyles['B'] ?? '7';

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $column) {
        $areaStyles[$column] = $areaFillStyle;
    }

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $column) {
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

    $rowNo = 9;

    foreach ($checklistRows as $item) {
        $row = scorecardCreateRow($dom, $rowNo);
        $sheetData->appendChild($row);

        if ($item['type'] === 'area') {
            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $column) {
                scorecardSetStyledCell(
                    $dom,
                    $xpath,
                    $row,
                    $rowNo,
                    $column,
                    $column === 'A' ? $item['measurable'] : '',
                    false,
                    $areaStyles
                );
            }

            if ($mergeCells instanceof DOMElement) {
                $mergeCell = $dom->createElementNS(
                    'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
                    'mergeCell'
                );
                $mergeCell->setAttribute('ref', 'A' . $rowNo . ':H' . $rowNo);
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

            if ($mergeCells instanceof DOMElement) {
                $mergeCell = $dom->createElementNS(
                    'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
                    'mergeCell'
                );
                $mergeCell->setAttribute('ref', 'B' . $rowNo . ':H' . $rowNo);
                $mergeCells->appendChild($mergeCell);
            }

            $rowNo++;
            continue;
        }

        $response = $responseMap[(int)$item['checkpoint_id']] ?? null;
        $userScore = null;
        $revisedScore = null;

        if ($response) {
            $rawScore = $response['response_value'];
            $userScore = is_numeric($rawScore)
                ? (int)$rawScore
                : (int)$response['score'];

            if ($response['revised_score'] !== null && is_numeric($response['revised_score'])) {
                $revisedScore = (int)$response['revised_score'];
            }
        }

        $remarks = $response ? (string)$response['remarks'] : '';

        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'A', $item['reference'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'B', $item['measurable'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'C', $item['checkpoint'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'D', $item['verification'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'E', $item['method'], false, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'F', $userScore, true, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'G', $revisedScore, true, $dataStyles);
        scorecardSetStyledCell($dom, $xpath, $row, $rowNo, 'H', $remarks, false, $dataStyles);

        $rowNo++;
    }

    if ($mergeCells instanceof DOMElement) {
        $mergeCells->setAttribute('count', (string)$xpath->query('x:mergeCell', $mergeCells)->length);
    }

    $dimension = $xpath->query('//x:dimension')->item(0);

    if ($dimension instanceof DOMElement) {
        $dimension->setAttribute('ref', 'A1:K' . max(10, $rowNo - 1));
    }

    $zip->addFromString('xl/worksheets/sheet1.xml', $dom->saveXML());
    $zip->close();

    $filename = 'checkpoint_scorecard_assessment_' . $assessmentId . '.xlsx';

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
