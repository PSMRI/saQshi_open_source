<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * State Report Service
 * StateReportService.php
 * Version 1.1.0 | Updated 2026-07-13
 * ==========================================================
 */

require_once __DIR__ . '/StateDashboardService.php';
require_once __DIR__ . '/../core/Crypto.php';

/**
 * Provides state report service behavior for SaQshi API workflows.
 */
class StateReportService extends StateDashboardService
{
    /**
     * Handles stream csv processing for this API workflow.
     */
    public static function streamCsv(mysqli $con, string $report, array $filters = []): void
    {
        $report = strtolower(trim($report));
        $filename = 'saqshi-state-' . str_replace('_', '-', $report ?: 'summary') . '-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        try {
            match ($report) {
                'facilities' => self::writeFacilities($con, $out, $filters),
                'assessments' => self::writeAssessments($con, $out, $filters),
                'class_indicator_report' => self::writeClassIndicatorReport($con, $out, $filters),
                'class_indicator_answers' => self::writeClassIndicatorAnswers($con, $out, $filters),
                'assessor_activity' => self::writeAssessorActivity($con, $out, $filters),
                'cqi' => self::writeCqi($con, $out, $filters),
                'performance' => self::writePerformance($con, $out, $filters),
                'certification' => self::writeCertification($con, $out, $filters),
                default => self::writeSummary($con, $out, $filters),
            };
        } catch (Throwable $e) {
            self::csvRow($out, ['Export Error', $e->getMessage()]);
        }

        fclose($out);
        exit;
    }

    /** Streams the Education class answer export as one workbook with four class tabs. */
    public static function streamClassIndicatorWorkbook(mysqli $con, array $filters = []): void
    {
        @set_time_limit(300);

        $csv = fopen('php://temp/maxmemory:16777216', 'w+');
        if ($csv === false) {
            throw new RuntimeException('Unable to prepare the class answer export.');
        }
        self::writeClassIndicatorAnswers($con, $csv, $filters);
        rewind($csv);

        $header = fgetcsv($csv, 0, ',', '"', '\\') ?: [];
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
        }
        $sheets = ['Class 9' => [], 'Class 10' => [], 'Class 11' => [], 'Class 12' => []];
        while (($row = fgetcsv($csv, 0, ',', '"', '\\')) !== false) {
            $class = trim((string)($row[6] ?? ''));
            if (isset($sheets[$class])) {
                $sheets[$class][] = $row;
            }
        }
        fclose($csv);

        if ($header === []) {
            $header = ['Report Status'];
            $sheets = ['Class 9' => [['No class assessment data is available.']]];
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'saqshi_class_answers_');
        if ($tmpFile === false) {
            throw new RuntimeException('Unable to create the workbook file.');
        }
        $workbook = $tmpFile . '.xlsx';
        @unlink($tmpFile);

        try {
            self::createClassAnswerWorkbook($workbook, $header, $sheets);
            $filename = 'saqshi-class-indicator-answers-' . date('Ymd-His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string)filesize($workbook));
            header('Pragma: no-cache');
            header('Expires: 0');
            readfile($workbook);
        } finally {
            if (is_file($workbook)) {
                @unlink($workbook);
            }
        }
        exit;
    }

    /** Creates a lightweight, dependency-free XLSX workbook for the four Education classes. */
    private static function createClassAnswerWorkbook(string $file, array $header, array $sheets): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Excel export requires the PHP ZipArchive extension.');
        }
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the Excel workbook.');
        }

        try {
            $sheetNames = array_keys($sheets);
            $zip->addFromString('[Content_Types].xml', self::xlsxContentTypes(count($sheetNames)));
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', self::xlsxWorkbookXml($sheetNames));
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::xlsxWorkbookRelationships(count($sheetNames)));
            $zip->addFromString('xl/styles.xml', self::xlsxStylesXml());

            foreach ($sheetNames as $index => $name) {
                $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', self::xlsxSheetXml($header, $sheets[$name]));
            }
        } finally {
            $zip->close();
        }
    }

    private static function xlsxContentTypes(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $overrides . '</Types>';
    }

    private static function xlsxWorkbookXml(array $sheetNames): string
    {
        $sheets = '';
        foreach ($sheetNames as $index => $name) {
            $sheets .= '<sheet name="' . self::xlsxEscape($name) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheets . '</sheets></workbook>';
    }

    private static function xlsxWorkbookRelationships(int $sheetCount): string
    {
        $relationships = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $relationships .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $relationships .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>';
    }

    private static function xlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs></styleSheet>';
    }

    private static function xlsxSheetXml(array $header, array $rows): string
    {
        $allRows = array_merge([$header], $rows);
        $columnCount = max(1, count($header));
        $xmlRows = '';
        foreach ($allRows as $rowIndex => $row) {
            $xmlRows .= '<row r="' . ($rowIndex + 1) . '"' . ($rowIndex === 0 ? ' ht="36" customHeight="1"' : '') . '>';
            for ($columnIndex = 0; $columnIndex < $columnCount; $columnIndex++) {
                $xmlRows .= self::xlsxInlineCell(self::xlsxColumnName($columnIndex + 1) . ($rowIndex + 1), $row[$columnIndex] ?? '', $rowIndex === 0 ? 1 : 0);
            }
            $xmlRows .= '</row>';
        }
        $lastColumn = self::xlsxColumnName($columnCount);
        $lastRow = max(1, count($allRows));
        $cols = '';
        for ($column = 1; $column <= $columnCount; $column++) {
            $width = $column <= 16 ? 18 : 30;
            $cols .= '<col min="' . $column . '" max="' . $column . '" width="' . $width . '" customWidth="1"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/><cols>' . $cols . '</cols><sheetData>' . $xmlRows . '</sheetData><autoFilter ref="A1:' . $lastColumn . $lastRow . '"/></worksheet>';
    }

    private static function xlsxInlineCell(string $reference, $value, int $style = 0): string
    {
        $text = self::xlsxEscape((string)$value);
        $space = preg_match('/^\s|\s$/u', (string)$value) ? ' xml:space="preserve"' : '';
        return '<c r="' . $reference . '" t="inlineStr"' . ($style ? ' s="' . $style . '"' : '') . '><is><t' . $space . '>' . $text . '</t></is></c>';
    }

    private static function xlsxColumnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)) . $name;
            $column = intdiv($column, 26);
        }
        return $name;
    }

    private static function xlsxEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Handles export catalog processing for this API workflow.
     */
    public static function exportCatalog(): array
    {
        $labels = self::domainLabels();
        $summaryAreas = [$labels['facilities'], $labels['assessments']];
        if (self::moduleEnabled('cqi')) {
            $summaryAreas[] = 'CQI';
        }
        if (self::moduleEnabled('performance')) {
            $summaryAreas[] = 'performance';
        }
        if (self::moduleEnabled('certification')) {
            $summaryAreas[] = 'certification';
        }

        $assessmentDescription = 'All ' . $labels['assessment'] . ' records with status, '
            . $labels['departments'] . ', checkpoints';
        if (self::moduleEnabled('cqi')) {
            $assessmentDescription .= ', action plans';
        }
        $assessmentDescription .= ' and score fields.';

        $reports = [
            ['key' => 'summary', 'title' => 'State Summary', 'description' => 'Summary counts for ' . self::humanList($summaryAreas) . '.'],
            ['key' => 'facilities', 'title' => 'All ' . $labels['facility'] . ' List', 'description' => $labels['facility'] . ' master list with state, division, district, block, ' . $labels['facility'] . ' type, ' . $labels['facility_code'] . ' and coordinates.'],
            ['key' => 'assessments', 'title' => 'Assessment Details', 'description' => $assessmentDescription],
            ['key' => 'class_indicator_report', 'title' => 'School Class Assessment Report', 'description' => 'One row per school class with UDISE, assessor and assessee details, class teacher, subject, dates, indicator completion, marks and status.'],
            ['key' => 'class_indicator_answers', 'title' => 'Class-wise Indicator Answers', 'description' => 'Excel workbook with Class 9, 10, 11 and 12 tabs; each tab includes school, assessor, teacher, completion and 38 indicator answers.'],
            ['key' => 'assessor_activity', 'title' => $labels['assessor'] . ' Activity', 'description' => 'Completed assessment count and assessment-level details for each ' . $labels['assessor'] . '.'],
        ];

        if (self::moduleEnabled('cqi')) {
            $reports[] = ['key' => 'cqi', 'title' => 'CQI Details', 'description' => 'Action plan and gap closure extract with responsible person, target date and revised score.'];
        }
        if (self::moduleEnabled('performance')) {
            $reports[] = ['key' => 'performance', 'title' => 'Performance Details', 'description' => 'Performance entries with month, numerator, denominator, result and remarks.'];
        }
        if (self::moduleEnabled('certification')) {
            $reports[] = ['key' => 'certification', 'title' => 'Certification History', 'description' => $labels['facility'] . ' certification history with decoded status, dates and score.'];
        }
        return $reports;
    }

    /**
     * Handles write summary processing for this API workflow.
     */
    private static function writeSummary(mysqli $con, $out, array $filters): void
    {
        $labels = self::domainLabels();
        $facility = self::facilityCategory($con, $filters);
        $assessment = self::assessmentProgress($con, $filters, true);
        $cqi = self::moduleEnabled('cqi') ? self::cqiSummary($con, $filters) : [];
        $performance = self::moduleEnabled('performance') ? self::performanceSummary($con, $filters) : [];
        $certification = self::moduleEnabled('certification') ? self::certificationSummary($con, $filters) : [];

        self::csvRow($out, ['Report', 'Metric', 'Value']);
        self::csvRow($out, [$labels['facilities'], 'Total ' . $labels['facilities'], $facility['total_facilities'] ?? 0]);
        foreach (($facility['facility_types'] ?? []) as $row) {
            self::csvRow($out, [$labels['facilities'], $labels['facility'] . ' Type - ' . ($row['facility_type'] ?? ''), $row['count'] ?? 0]);
        }
        self::csvRow($out, [$labels['assessments'], 'Total', $assessment['total'] ?? 0]);
        self::csvRow($out, [$labels['assessments'], 'Active', $assessment['active'] ?? 0]);
        self::csvRow($out, [$labels['assessments'], 'Completed', $assessment['completed'] ?? 0]);
        self::csvRow($out, [$labels['assessments'], 'Cancelled', $assessment['cancelled'] ?? 0]);
        if (self::moduleEnabled('cqi')) {
            self::csvRow($out, ['CQI', $labels['facilities'] . ' With Action Plan', $cqi['facilities_with_action_plan'] ?? 0]);
            self::csvRow($out, ['CQI', 'Completed', $cqi['completed'] ?? 0]);
            self::csvRow($out, ['CQI', 'Pending', $cqi['pending'] ?? 0]);
            self::csvRow($out, ['CQI', 'Overdue', $cqi['overdue'] ?? 0]);
        }
        if (self::moduleEnabled('performance')) {
            self::csvRow($out, ['Performance', 'Facilities', $performance['summary']['facilities'] ?? 0]);
            self::csvRow($out, ['Performance', 'Performance Entries', $performance['summary']['performance_entries'] ?? 0]);
            self::csvRow($out, ['Performance', 'Submitted Months', $performance['summary']['submitted_months'] ?? 0]);
        }
        if (self::moduleEnabled('certification')) {
            self::csvRow($out, ['Certification', 'Total', $certification['total'] ?? 0]);
            foreach (($certification['status'] ?? []) as $row) {
                self::csvRow($out, ['Certification', 'Status - ' . ($row['status'] ?? ''), $row['count'] ?? 0]);
            }
        }
    }

    /**
     * Handles write facilities processing for this API workflow.
     */
    private static function writeFacilities(mysqli $con, $out, array $filters): void
    {
        $labels = self::domainLabels();
        self::csvRow($out, [
            'State', 'Division', 'District', 'Block', $labels['facility'] . ' Name',
            $labels['facility'] . ' Type', $labels['facility_code'], 'Latitude', 'Longitude', 'Active',
            'Class-wise Completion', 'Round-wise Class Completion'
        ]);

        $classCompletion = self::schoolClassCompletion($con);
        $masterRows = self::facilityMasterRows();
        if ($masterRows !== []) {
            foreach ($masterRows as $row) {
                if (!self::matchesMasterFilters($row, $filters)) {
                    continue;
                }
                self::csvRow($out, [
                    $row['state_name'], $row['division'], $row['district'],
                    $row['block'], $row['fac_name'], self::facilityTypeName($row['facility_type'] ?? ''), $row['nin_no'],
                    $row['latitude'], $row['longitude'], '1',
                    $classCompletion[(int)($row['fac_id'] ?? 0)]['by_class'] ?? '',
                    $classCompletion[(int)($row['fac_id'] ?? 0)]['by_round'] ?? ''
                ]);
            }
            return;
        }

        if (!self::tableExistsLocal($con, 'facilities')) {
            self::csvRow($out, ['Facilities table is not available.']);
            return;
        }

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT f.fac_id, f.state_name, f.division, f.Dist_Name, f.Block_Name, f.fac_name,
                   f.Health_facilty_type, f.NIN_no, f.lat, f.longit, f.is_active
            FROM facilities f
            {$where['sql']}
            ORDER BY f.state_name, f.division, f.Dist_Name, f.Block_Name, f.fac_name
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row) use ($classCompletion): array {
            $completion = $classCompletion[(int)($row['fac_id'] ?? 0)] ?? [];
            return [
                $row['state_name'] ?? '', $row['division'] ?? '',
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['fac_name'] ?? '',
                self::facilityTypeName($row['Health_facilty_type'] ?? ''), $row['NIN_no'] ?? '', $row['lat'] ?? '',
                $row['longit'] ?? '', $row['is_active'] ?? '',
                $completion['by_class'] ?? '', $completion['by_round'] ?? ''
            ];
        });
    }

    /** Creates compact class and round completion summaries for the school-list CSV. */
    private static function schoolClassCompletion(mysqli $con): array
    {
        if (!self::tableExistsLocal($con, 'assessment_master') || !self::tableExistsLocal($con, 'assessment_department')) {
            return [];
        }
        $hasRounds = self::tableExistsLocal($con, 'facility_assessment_round');
        $roundJoin = $hasRounds ? 'LEFT JOIN facility_assessment_round fr ON fr.round_id = a.round_id' : '';
        $roundNo = $hasRounds ? 'fr.round_no' : 'NULL';
        $rows = self::reportRows($con, "
            SELECT a.fac_id_fk, a.framework_code, f.Health_facilty_type AS facility_type_id,
                   d.dept_id, d.status AS class_status, a.status AS assessment_status,
                   {$roundNo} AS round_no
            FROM assessment_master a
            JOIN assessment_department d ON d.assessment_id = a.assessment_id AND d.is_active = 1
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$roundJoin}
            WHERE UPPER(COALESCE(a.status, '')) <> 'PENDING'
            ORDER BY a.fac_id_fk, COALESCE({$roundNo}, 0), d.dept_id
        ");

        $byFacility = [];
        foreach ($rows as $row) {
            $facilityId = (int)($row['fac_id_fk'] ?? 0);
            $deptId = (int)($row['dept_id'] ?? 0);
            if ($facilityId <= 0 || $deptId <= 0) continue;
            $round = (int)($row['round_no'] ?? 0);
            $roundLabel = $round > 0 ? 'Round ' . $round : 'No round';
            $className = self::reportClassName(
                (string)($row['framework_code'] ?? ''),
                (int)($row['facility_type_id'] ?? 0),
                $deptId
            );
            $status = strtoupper(trim((string)($row['class_status'] ?? '')));
            if ($status === '') $status = strtoupper(trim((string)($row['assessment_status'] ?? 'NOT STARTED')));
            $byFacility[$facilityId]['classes'][$className][] = $roundLabel . ': ' . $status;
            $byFacility[$facilityId]['rounds'][$roundLabel][] = $className . ': ' . $status;
        }

        $result = [];
        foreach ($byFacility as $facilityId => $summary) {
            $classParts = [];
            foreach (($summary['classes'] ?? []) as $className => $entries) {
                $classParts[] = $className . ' (' . implode('; ', array_unique($entries)) . ')';
            }
            $roundParts = [];
            foreach (($summary['rounds'] ?? []) as $roundLabel => $entries) {
                $roundParts[] = $roundLabel . ' (' . implode('; ', array_unique($entries)) . ')';
            }
            $result[$facilityId] = ['by_class' => implode(' | ', $classParts), 'by_round' => implode(' | ', $roundParts)];
        }
        return $result;
    }

    private static function reportClassName(string $frameworkCode, int $facilityTypeId, int $deptId): string
    {
        static $names = [];
        $frameworkCode = $frameworkCode ?: 'saqshi-education';
        $key = $frameworkCode . ':' . $facilityTypeId . ':' . $deptId;
        if (isset($names[$key])) {
            return $names[$key];
        }
        try {
            $engine = FrameworkEngine::load($frameworkCode);
            foreach ($engine->getDepartments($facilityTypeId) as $department) {
                if ((int)($department['fac_dept_id'] ?? $department['dept_id'] ?? 0) === $deptId) {
                    return $names[$key] = (string)($department['dept_name'] ?? ('Class ' . $deptId));
                }
            }
        } catch (Throwable $e) { }
        return $names[$key] = 'Class/Department ' . $deptId;
    }

    /**
     * Returns the known Education questionnaire count without parsing the
     * multi-megabyte framework JSON once per exported row.
     */
    private static function reportClassIndicatorTotal(string $frameworkCode, int $facilityTypeId, int $deptId): int
    {
        return strtolower(trim($frameworkCode)) === 'saqshi-education' ? 38 : 0;
    }

    /** Fetches unfiltered internal report rows for a small grouped summary query. */
    private static function reportRows(mysqli $con, string $sql): array
    {
        $result = $con->query($sql);
        if (!$result) {
            throw new RuntimeException('School completion report query failed: ' . $con->error);
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        return $rows;
    }

    /**
     * Handles write assessments processing for this API workflow.
     */
    private static function writeAssessments(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'assessment_master')) {
            self::csvRow($out, ['Assessment table is not available.']);
            return;
        }

        $labels = self::domainLabels();
        self::csvRow($out, [
            $labels['facility_code'], $labels['facility'], 'District', 'Block', 'Assessment ID',
            'Assessment Name', 'Framework', 'Start Date', 'Planned End Date', 'Actual Completion Date', 'Cancellation Date', 'Status',
            $labels['assessor'] . ' Name', 'Class / Subject Teacher Name', 'Teacher ID', 'Subject', 'Class Section',
            'Checkpoint Done', 'Original Score', 'Final Score', 'Action Plans',
            'Completed Action Plans', 'Last Updated'
        ]);

        $responseTable = self::responseTable($con);
        $responseJoin = '';
        if ($responseTable) {
            $assessmentColumn = self::columnExistsLocal($con, $responseTable, 'assessment_id') ? 'assessment_id' : 'cycle_id';
            $finalScore = self::tableExistsLocal($con, 'assessment_action_plan')
                ? 'COALESCE(ap.revised_score, r.score)'
                : 'r.score';
            $actionJoinForScore = self::tableExistsLocal($con, 'assessment_action_plan')
                ? "LEFT JOIN assessment_action_plan ap ON ap.assessment_id = r.{$assessmentColumn} AND ap.dept_id = r.dept_id AND ap.checkpoint_id = r.checkpoint_id"
                : '';
            $responseJoin = "
                LEFT JOIN (
                    SELECT r.{$assessmentColumn} AS assessment_id,
                           COUNT(DISTINCT r.checkpoint_id) AS checkpoint_done,
                           ROUND(COALESCE(SUM(r.score), 0), 2) AS original_score,
                           ROUND(COALESCE(SUM({$finalScore}), 0), 2) AS final_score
                    FROM {$responseTable} r
                    {$actionJoinForScore}
                    GROUP BY r.{$assessmentColumn}
                ) rs ON rs.assessment_id = a.assessment_id
            ";
        }

        $actionJoin = self::tableExistsLocal($con, 'assessment_action_plan')
            ? "
                LEFT JOIN (
                    SELECT assessment_id,
                           COUNT(*) AS action_plans,
                           SUM(CASE WHEN UPPER(COALESCE(status, '')) IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS completed_action_plans,
                           MAX(updated_on) AS last_action_update
                    FROM assessment_action_plan
                    GROUP BY assessment_id
                ) aps ON aps.assessment_id = a.assessment_id
            "
            : '';

        // One assessment can contain class-level details. Keep each saved
        // class record visible rather than dropping any teacher/section data.
        $hasAssessorInfo = self::tableExistsLocal($con, 'assessment_assessor_info');
        $hasClassSection = $hasAssessorInfo && self::columnExistsLocal($con, 'assessment_assessor_info', 'class_section');
        $assessorInfoJoin = $hasAssessorInfo
            ? 'LEFT JOIN assessment_assessor_info ai ON ai.assessment_id = a.assessment_id'
            : '';
        $assessorInfoSelect = $hasAssessorInfo
            ? "ai.dept_id AS class_id, ai.assessor_name, ai.assessee_name, ai.teacher_code, ai.subject_name, " . ($hasClassSection ? 'ai.class_section' : "''") . ' AS class_section,'
            : "NULL AS class_id, '' AS assessor_name, '' AS assessee_name, '' AS teacher_code, '' AS subject_name, '' AS class_section,";

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT f.fac_id, f.NIN_no, f.fac_name, f.Dist_Name, f.Block_Name,
                   a.assessment_id, a.assessment_name, a.framework_code, a.start_date,
                   a.end_date, a.completed_on, a.cancelled_on, a.status, COALESCE(rs.checkpoint_done, 0) AS checkpoint_done,
                   {$assessorInfoSelect}
                   COALESCE(rs.original_score, 0) AS original_score,
                   COALESCE(rs.final_score, 0) AS final_score,
                   COALESCE(aps.action_plans, 0) AS action_plans,
                   COALESCE(aps.completed_action_plans, 0) AS completed_action_plans,
                   COALESCE(aps.last_action_update, '') AS last_action_update
            FROM assessment_master a
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$assessorInfoJoin}
            {$responseJoin}
            {$actionJoin}
            {$where['sql']}
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name, a.assessment_id DESC, class_id
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            return [
                $row['NIN_no'] ?? '', $row['fac_name'] ?? '',
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['assessment_id'] ?? '',
                $row['assessment_name'] ?? '', $row['framework_code'] ?? '', $row['start_date'] ?? '',
                $row['end_date'] ?? '', $row['completed_on'] ?? '', $row['cancelled_on'] ?? '', $row['status'] ?? '',
                Crypto::decrypt((string)($row['assessor_name'] ?? '')),
                Crypto::decrypt((string)($row['assessee_name'] ?? '')),
                $row['teacher_code'] ?? '', $row['subject_name'] ?? '', $row['class_section'] ?? '',
                $row['checkpoint_done'] ?? 0,
                $row['original_score'] ?? 0, $row['final_score'] ?? 0, $row['action_plans'] ?? 0,
                $row['completed_action_plans'] ?? 0, $row['last_action_update'] ?? ''
            ];
        });
    }

    /** Exports one Education assessment row for every school class. */
    private static function writeClassIndicatorReport(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'assessment_master') || !self::tableExistsLocal($con, 'assessment_department')) {
            self::csvRow($out, ['Assessment class data is not available.']);
            return;
        }

        self::csvRow($out, [
            'District', 'Block', 'School Name', 'UDISE Code', 'Assessment ID', 'Assessment Name',
            'Assessor Name', 'Assessee Name', 'Class', 'Class Teacher Name', 'Subject Name', 'Section',
            'Assessment Start Date', 'Assessment End Date', 'Class Start Date', 'Class End Date',
            'Total Indicators', 'Completed Indicators', 'Total Marks', 'Obtained Marks', 'Assessment Status', 'Class Status'
        ]);

        $responseTable = self::responseTable($con);
        $responseJoin = '';
        $responseMetrics = '0 AS indicators_answered, 0 AS total_marks, 0 AS obtained_marks';
        if ($responseTable !== '') {
            $assessmentColumn = self::columnExistsLocal($con, $responseTable, 'assessment_id') ? 'assessment_id' : 'cycle_id';
            $hasMaxScore = self::columnExistsLocal($con, $responseTable, 'max_score');
            $totalMarks = $hasMaxScore ? 'SUM(COALESCE(max_score, 0))' : 'COUNT(DISTINCT checkpoint_id) * 2';
            $responseJoin = "LEFT JOIN (
                SELECT {$assessmentColumn} AS assessment_id, dept_id,
                       COUNT(DISTINCT checkpoint_id) AS indicators_answered,
                       ROUND(COALESCE({$totalMarks}, 0), 2) AS total_marks,
                       ROUND(COALESCE(SUM(score), 0), 2) AS obtained_marks
                FROM {$responseTable}
                GROUP BY {$assessmentColumn}, dept_id
            ) r ON r.assessment_id = a.assessment_id AND r.dept_id = d.dept_id";
            $responseMetrics = 'COALESCE(r.indicators_answered, 0) AS indicators_answered, COALESCE(r.total_marks, 0) AS total_marks, COALESCE(r.obtained_marks, 0) AS obtained_marks';
        }

        $assessorInfoJoin = self::tableExistsLocal($con, 'assessment_assessor_info')
            ? 'LEFT JOIN assessment_assessor_info ai ON ai.assessment_id = a.assessment_id AND ai.dept_id = d.dept_id'
            : '';
        $assessorInfoFields = $assessorInfoJoin !== ''
            ? "ai.assessor_name, ai.assessor_designation, ai.assessee_name, ai.teacher_code, ai.class_section, ai.subject_name,"
            : "'' AS assessor_name, '' AS assessor_designation, '' AS assessee_name, '' AS teacher_code, '' AS class_section, '' AS subject_name,";

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT f.NIN_no, f.fac_name, f.state_name, f.division, f.Dist_Name, f.Block_Name, f.Health_facilty_type,
                   a.assessment_id, a.assessment_name, a.start_date, a.end_date, a.status AS assessment_status, a.framework_code,
                   d.dept_id, d.status AS class_status, d.started_on, d.completed_on, d.total_checkpoints, d.completed_checkpoints,
                   {$assessorInfoFields}
                   {$responseMetrics}
            FROM assessment_master a
            JOIN assessment_department d ON d.assessment_id = a.assessment_id AND d.is_active = 1
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$assessorInfoJoin}
            {$responseJoin}
            {$where['sql']}
            AND a.framework_code = 'saqshi-education'
            ORDER BY f.state_name, f.division, f.Dist_Name, f.Block_Name, f.fac_name, a.assessment_id DESC, d.dept_id
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            $total = max(0, (int)($row['total_checkpoints'] ?? 0));
            if ($total === 0) {
                $total = self::reportClassIndicatorTotal(
                    (string)($row['framework_code'] ?? ''),
                    (int)($row['Health_facilty_type'] ?? 0),
                    (int)($row['dept_id'] ?? 0)
                );
            }
            $answered = max(0, (int)($row['indicators_answered'] ?? 0));
            $completed = min($total, max((int)($row['completed_checkpoints'] ?? 0), $answered));
            return [
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['fac_name'] ?? '', $row['NIN_no'] ?? '',
                $row['assessment_id'] ?? '', $row['assessment_name'] ?? '',
                Crypto::decrypt((string)($row['assessor_name'] ?? '')), Crypto::decrypt((string)($row['assessee_name'] ?? '')),
                self::reportClassName((string)($row['framework_code'] ?? ''), (int)($row['Health_facilty_type'] ?? 0), (int)($row['dept_id'] ?? 0)),
                Crypto::decrypt((string)($row['assessee_name'] ?? '')), $row['subject_name'] ?? '', $row['class_section'] ?? '',
                $row['start_date'] ?? '', $row['end_date'] ?? '', $row['started_on'] ?? '', $row['completed_on'] ?? '',
                $total, $completed, $row['total_marks'] ?? 0, $row['obtained_marks'] ?? 0,
                $row['assessment_status'] ?? '', $row['class_status'] ?? ''
            ];
        });
    }

    /** Exports every shared Education indicator as its own answer column. */
    private static function writeClassIndicatorAnswers(mysqli $con, $out, array $filters): void
    {
        // This export joins every completed school/class to its 38 answers.
        // Allow it to finish on larger local master datasets without changing
        // the global PHP request limit.
        @set_time_limit(300);

        $responseTable = self::responseTable($con);
        if ($responseTable === '' || !self::tableExistsLocal($con, 'assessment_department')) {
            self::csvRow($out, ['Assessment response data is not available.']);
            return;
        }

        $indicatorMap = self::educationIndicatorMap();
        if ($indicatorMap['columns'] === []) {
            self::csvRow($out, ['Education indicators could not be loaded.']);
            return;
        }

        $header = [
            'School Code', 'School Name', 'District', 'Block', 'Assessment ID', 'Assessment Name',
            'Class', 'Class Section', 'Subject', 'Assessor Name', 'Assessor Designation',
            'Class/Subject Teacher Name', 'Teacher ID', 'Class Active', 'Class Status', 'Class Completed On'
        ];
        foreach ($indicatorMap['columns'] as $key => $label) {
            $header[] = $key . ' - ' . $label;
        }
        self::csvRow($out, $header);

        $assessmentColumn = self::columnExistsLocal($con, $responseTable, 'assessment_id') ? 'assessment_id' : 'cycle_id';
        $assessorInfoJoin = self::tableExistsLocal($con, 'assessment_assessor_info')
            ? 'LEFT JOIN assessment_assessor_info ai ON ai.assessment_id = a.assessment_id AND ai.dept_id = d.dept_id'
            : '';
        $assessorInfoFields = $assessorInfoJoin !== ''
            ? 'ai.assessor_name, ai.assessor_designation, ai.assessee_name, ai.teacher_code, ai.class_section, ai.subject_name,'
            : "'' AS assessor_name, '' AS assessor_designation, '' AS assessee_name, '' AS teacher_code, '' AS class_section, '' AS subject_name,";
        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT f.NIN_no, f.fac_name, f.Dist_Name, f.Block_Name, f.Health_facilty_type,
                   a.assessment_id, a.assessment_name, a.framework_code, d.dept_id, d.is_active, d.status AS class_status, d.completed_on,
                   {$assessorInfoFields} r.checkpoint_id, r.response_value, r.response_json
            FROM assessment_master a
            JOIN assessment_department d ON d.assessment_id = a.assessment_id
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$assessorInfoJoin}
            LEFT JOIN {$responseTable} r ON r.{$assessmentColumn} = a.assessment_id AND r.dept_id = d.dept_id
            {$where['sql']} AND a.framework_code = 'saqshi-education'
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name, a.assessment_id DESC, d.dept_id, r.checkpoint_id
        ";

        $statement = $con->prepare($sql);
        if (!$statement) {
            throw new RuntimeException('Unable to prepare class indicator export: ' . $con->error);
        }
        if ($where['types'] !== '') {
            $bind = [];
            $bind[] = $where['types'];
            foreach ($where['params'] as $index => $value) {
                $bind[] = &$where['params'][$index];
            }
            call_user_func_array([$statement, 'bind_param'], $bind);
        }
        $statement->execute();
        $result = $statement->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rowKey = $row['assessment_id'] . ':' . $row['dept_id'];
            if (!isset($rows[$rowKey])) {
                $rows[$rowKey] = [
                    'values' => [
                        $row['NIN_no'] ?? '', $row['fac_name'] ?? '', $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '',
                        $row['assessment_id'] ?? '', $row['assessment_name'] ?? '',
                        self::reportClassName((string)($row['framework_code'] ?? ''), (int)($row['Health_facilty_type'] ?? 0), (int)($row['dept_id'] ?? 0)),
                        $row['class_section'] ?? '', $row['subject_name'] ?? '', Crypto::decrypt((string)($row['assessor_name'] ?? '')),
                        $row['assessor_designation'] ?? '', Crypto::decrypt((string)($row['assessee_name'] ?? '')), $row['teacher_code'] ?? '',
                        $row['is_active'] ?? '', $row['class_status'] ?? '', $row['completed_on'] ?? ''
                    ],
                    'answers' => array_fill_keys(array_keys($indicatorMap['columns']), '')
                ];
            }
            $checkpointId = (string)($row['checkpoint_id'] ?? '');
            $column = $indicatorMap['checkpoints'][$checkpointId] ?? null;
            if ($column !== null) {
                $answer = trim((string)($row['response_value'] ?? ''));
                if ($answer === '' && !empty($row['response_json'])) {
                    $answer = (string)$row['response_json'];
                }
                $rows[$rowKey]['answers'][$column] = $indicatorMap['options'][$checkpointId][$answer] ?? $answer;
            }
        }
        $result->free();
        $statement->close();

        foreach ($rows as $row) {
            self::csvRow($out, array_merge($row['values'], array_values($row['answers'])));
        }
    }

    /** Returns shared Education indicator headers and response-value labels. */
    private static function educationIndicatorMap(): array
    {
        $map = ['columns' => [], 'checkpoints' => [], 'options' => []];
        try {
            $framework = FrameworkEngine::load('saqshi-education');
            // Education uses the same 38 indicators for every class. Use the
            // first class as the canonical column set instead of creating a
            // separate/duplicate column set for each configured class.
            $facilityType = $framework->toArray()[0] ?? [];
            $department = $facilityType['departments'][0] ?? [];
            foreach ($framework->getCheckpoints((int)($facilityType['fac_type_id'] ?? 0), (int)($department['fac_dept_id'] ?? 0)) as $checkpoint) {
                $id = (string)($checkpoint['csqa_id'] ?? '');
                $reference = trim((string)($checkpoint['csqa_reference_id'] ?? $id));
                if ($id === '' || $reference === '') continue;
                $map['columns'][$reference] = (string)($checkpoint['Checkpoint'] ?? $checkpoint['Measurable_Element'] ?? 'Indicator ' . $reference);
                $map['checkpoints'][$id] = $reference;
                foreach (($checkpoint['response']['options'] ?? []) as $option) {
                    $map['options'][$id][(string)($option['value'] ?? '')] = (string)($option['label'] ?? $option['value'] ?? '');
                }
            }

            // Classes can have different internal checkpoint IDs. Map each
            // of them to the canonical 38 reference columns without adding
            // any further headers.
            $canonicalKeys = array_keys($map['columns']);
            foreach ($framework->toArray() as $type) {
                foreach (($type['departments'] ?? []) as $class) {
                    foreach ($framework->getCheckpoints((int)($type['fac_type_id'] ?? 0), (int)($class['fac_dept_id'] ?? 0)) as $index => $checkpoint) {
                        $id = (string)($checkpoint['csqa_id'] ?? '');
                        $reference = $canonicalKeys[$index] ?? null;
                        if ($id === '' || $reference === null) continue;
                        $map['checkpoints'][$id] = $reference;
                        foreach (($checkpoint['response']['options'] ?? []) as $option) {
                            $map['options'][$id][(string)($option['value'] ?? '')] = (string)($option['label'] ?? $option['value'] ?? '');
                        }
                    }
                }
            }
        } catch (Throwable $e) { }
        return $map;
    }

    /**
     * Handles write cqi processing for this API workflow.
     */
    private static function writeCqi(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'assessment_action_plan')) {
            self::csvRow($out, ['CQI action plan table is not available.']);
            return;
        }

        $labels = self::domainLabels();
        self::csvRow($out, [
            'District', 'Block', $labels['facility'] . ' Name', $labels['facility_code'], $labels['facility'] . ' Type',
            'Assessment ID', 'Assessment Name', 'Assessment Status',
            'Open Gap', 'Closed Gap', 'Left Gap', 'Total Action Plan',
            'Overdue Gap', 'Last Updated'
        ]);

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT
                f.Dist_Name,
                f.Block_Name,
                f.fac_name,
                f.NIN_no,
                f.Health_facilty_type,
                a.assessment_id,
                a.assessment_name,
                a.status AS assessment_status,
                SUM(CASE WHEN UPPER(COALESCE(ap.status, '')) IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS closed_gap,
                SUM(CASE WHEN UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS open_gap,
                COUNT(*) AS total_action_plan,
                SUM(CASE WHEN ap.target_date IS NOT NULL AND ap.target_date < CURDATE() AND UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS overdue_gap,
                MAX(ap.updated_on) AS last_updated
            FROM assessment_action_plan ap
            LEFT JOIN assessment_master a ON a.assessment_id = ap.assessment_id
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$where['sql']}
            GROUP BY
                f.Dist_Name,
                f.Block_Name,
                f.fac_name,
                f.NIN_no,
                f.Health_facilty_type,
                a.assessment_id,
                a.assessment_name,
                a.status
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name, a.assessment_id DESC
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            $openGap = (int)($row['open_gap'] ?? 0);
            $closedGap = (int)($row['closed_gap'] ?? 0);
            return [
                $row['Dist_Name'] ?? '',
                $row['Block_Name'] ?? '',
                $row['fac_name'] ?? '',
                $row['NIN_no'] ?? '',
                self::facilityTypeName($row['Health_facilty_type'] ?? ''),
                $row['assessment_id'] ?? '',
                $row['assessment_name'] ?? '',
                $row['assessment_status'] ?? '',
                $openGap,
                $closedGap,
                $openGap,
                $row['total_action_plan'] ?? 0,
                $row['overdue_gap'] ?? 0,
                $row['last_updated'] ?? ''
            ];
        });
    }

    /**
     * Handles write performance processing for this API workflow.
     */
    private static function writePerformance(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'performance_entries')) {
            self::csvRow($out, ['Performance table is not available.']);
            return;
        }

        $labels = self::domainLabels();
        self::csvRow($out, [
            'District', 'Block', $labels['facility'] . ' Name', $labels['facility_code'], $labels['facility'] . ' Type',
            'Total Departments', 'KPI Departments', 'Outcome Departments',
            'KPI Month Count', 'KPI Months', 'Outcome Month Count',
            'Outcome Months', 'KPI Entry Count', 'Outcome Entry Count',
            'Latest Updated'
        ]);

        $where = self::facilityWhereLocal($filters, 'f');
        if (($filters['month'] ?? '') !== '') {
            $where['sql'] .= ' AND pe.entry_month = ?';
            $where['types'] .= 'i';
            $where['params'][] = (int)$filters['month'];
        }
        if (($filters['year'] ?? '') !== '') {
            $where['sql'] .= ' AND pe.entry_year = ?';
            $where['types'] .= 'i';
            $where['params'][] = (int)$filters['year'];
        }

        $sql = "
            SELECT
                f.Dist_Name,
                f.Block_Name,
                f.fac_name,
                f.NIN_no,
                f.Health_facilty_type,
                COUNT(DISTINCT pe.dept_id) AS total_departments,
                COUNT(DISTINCT CASE WHEN pe.indicator_type = 'KPI' THEN pe.dept_id END) AS kpi_departments,
                COUNT(DISTINCT CASE WHEN pe.indicator_type = 'OUTCOME' THEN pe.dept_id END) AS outcome_departments,
                SUM(CASE WHEN pe.indicator_type = 'KPI' THEN 1 ELSE 0 END) AS kpi_entries,
                SUM(CASE WHEN pe.indicator_type = 'OUTCOME' THEN 1 ELSE 0 END) AS outcome_entries,
                COUNT(DISTINCT CASE WHEN pe.indicator_type = 'KPI' THEN CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0')) END) AS kpi_month_count,
                COUNT(DISTINCT CASE WHEN pe.indicator_type = 'OUTCOME' THEN CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0')) END) AS outcome_month_count,
                GROUP_CONCAT(DISTINCT CASE WHEN pe.indicator_type = 'KPI' THEN DATE_FORMAT(STR_TO_DATE(CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0'), '-01'), '%Y-%m-%d'), '%b-%y') END ORDER BY pe.entry_year, pe.entry_month SEPARATOR ', ') AS kpi_months,
                GROUP_CONCAT(DISTINCT CASE WHEN pe.indicator_type = 'OUTCOME' THEN DATE_FORMAT(STR_TO_DATE(CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0'), '-01'), '%Y-%m-%d'), '%b-%y') END ORDER BY pe.entry_year, pe.entry_month SEPARATOR ', ') AS outcome_months,
                MAX(pe.updated_on) AS latest_updated
            FROM performance_entries pe
            LEFT JOIN facilities f ON f.fac_id = pe.fac_id
            {$where['sql']}
            GROUP BY
                f.Dist_Name,
                f.Block_Name,
                f.fac_name,
                f.NIN_no,
                f.Health_facilty_type
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            return [
                $row['Dist_Name'] ?? '',
                $row['Block_Name'] ?? '',
                $row['fac_name'] ?? '',
                $row['NIN_no'] ?? '',
                self::facilityTypeName($row['Health_facilty_type'] ?? ''),
                $row['total_departments'] ?? 0,
                $row['kpi_departments'] ?? 0,
                $row['outcome_departments'] ?? 0,
                $row['kpi_month_count'] ?? 0,
                $row['kpi_months'] ?? '',
                $row['outcome_month_count'] ?? 0,
                $row['outcome_months'] ?? '',
                $row['kpi_entries'] ?? 0,
                $row['outcome_entries'] ?? 0,
                $row['latest_updated'] ?? ''
            ];
        });
    }

    /**
     * Handles write certification processing for this API workflow.
     */
    private static function writeCertification(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'certification_history')) {
            self::csvRow($out, ['Certification history table is not available.']);
            return;
        }

        $labels = self::domainLabels();
        self::csvRow($out, [
            'History ID', $labels['facility_code'], $labels['facility'], 'District', 'Block',
            'Status', 'Certification Type', 'Assessment Mode', 'Certification Date',
            'Valid From', 'Expiry Date', 'Score', 'Renewal Status', 'Remarks',
            'Action Type', 'Action By', 'Action On'
        ]);

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT ch.history_id, ch.fac_id_fk, ch.fac_nin, ch.new_data_json, ch.action_type,
                   ch.action_by, ch.action_on, f.fac_name, f.Dist_Name, f.Block_Name
            FROM certification_history ch
            LEFT JOIN facilities f ON f.fac_id = ch.fac_id_fk OR CAST(f.NIN_no AS CHAR) = CAST(ch.fac_nin AS CHAR)
            {$where['sql']}
            ORDER BY ch.action_on DESC, ch.history_id DESC
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            $payload = json_decode((string)($row['new_data_json'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            return [
                $row['history_id'] ?? '', $row['fac_nin'] ?? '',
                $row['fac_name'] ?? '', $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '',
                $payload['status'] ?? $payload['Cert_status'] ?? '',
                $payload['certification_type'] ?? $payload['certification_level'] ?? $payload['type_of_ass'] ?? '',
                $payload['assessment_mode'] ?? $payload['ass_mod'] ?? '',
                $payload['certification_date'] ?? $payload['date_of_ass'] ?? '',
                $payload['valid_from'] ?? '',
                $payload['expiry_date'] ?? $payload['valid_to'] ?? $payload['validity'] ?? '',
                $payload['score'] ?? '',
                $payload['renewal_status'] ?? '',
                $payload['remarks'] ?? $payload['cert_detailscol'] ?? '',
                $row['action_type'] ?? '', $row['action_by'] ?? '', $row['action_on'] ?? ''
            ];
        });
    }

    /**
     * Handles response table processing for this API workflow.
     */
    private static function responseTable(mysqli $con): string
    {
        if (self::tableExistsLocal($con, 'assessment_response')) {
            return 'assessment_response';
        }
        if (self::tableExistsLocal($con, 'assessment_cycle_response')) {
            return 'assessment_cycle_response';
        }
        return '';
    }

    /**
     * Handles select column processing for this API workflow.
     */
    private static function selectColumn(mysqli $con, string $table, string $column, string $alias): string
    {
        return self::columnExistsLocal($con, $table, $column)
            ? "{$alias}.{$column}"
            : "''";
    }

    /**
     * Handles month name processing for this API workflow.
     */
    private static function monthName(int $month): string
    {
        if ($month < 1 || $month > 12) {
            return '';
        }

        return date('F', mktime(0, 0, 0, $month, 1));
    }

    /**
     * Exports completed-assessment counts and details by assigned Assessor/DPO.
     */
    private static function writeAssessorActivity(mysqli $con, $out, array $filters): void
    {
        $labels = self::domainLabels();
        $assessorLabel = $labels['assessor'];

        if (!self::tableExistsLocal($con, 'assessment_master') || !self::columnExistsLocal($con, 'assessment_master', 'assigned_assessor_id')) {
            self::csvRow($out, [$assessorLabel . ' activity is not available because assessments have no assigned ' . strtolower($assessorLabel) . '.']);
            return;
        }

        self::csvRow($out, [
            $assessorLabel . ' ID', $assessorLabel . ' Code', $assessorLabel . ' Name',
            'Completed Assessments', 'Assessment ID', 'Assessment Name', 'Framework',
            $labels['facility_code'], $labels['facility'] . ' Name',
            'District', 'Block', 'Start Date', 'Planned End Date', 'Actual Completion Date', 'Cancellation Date', 'Assessment Status'
        ]);

        $where = self::facilityWhereLocal($filters, 'f');
        $hasAssessorMaster = self::tableExistsLocal($con, 'assessor_master');
        $assessorJoin = $hasAssessorMaster
            ? 'LEFT JOIN assessor_master am ON am.assessor_id = a.assigned_assessor_id'
            : '';
        $assessorId = $hasAssessorMaster ? 'am.assessor_id' : 'a.assigned_assessor_id';
        $assessorCode = $hasAssessorMaster ? 'am.assessor_code' : "''";
        $assessorName = $hasAssessorMaster ? 'am.assessor_name' : "''";
        $completedJoin = "
            LEFT JOIN (
                SELECT assigned_assessor_id,
                       SUM(CASE WHEN UPPER(COALESCE(status, '')) IN ('COMPLETED', 'CLOSED') THEN 1 ELSE 0 END) AS completed_assessments
                FROM assessment_master
                WHERE assigned_assessor_id IS NOT NULL
                GROUP BY assigned_assessor_id
            ) ac ON ac.assigned_assessor_id = a.assigned_assessor_id
        ";

        $sql = "
            SELECT {$assessorId} AS assessor_id, {$assessorCode} AS assessor_code,
                   {$assessorName} AS assessor_name, COALESCE(ac.completed_assessments, 0) AS completed_assessments,
                   a.assessment_id, a.assessment_name, a.framework_code, a.start_date, a.end_date, a.completed_on, a.cancelled_on, a.status,
                   f.NIN_no, f.fac_name, f.Dist_Name, f.Block_Name
            FROM assessment_master a
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$assessorJoin}
            {$completedJoin}
            {$where['sql']}
            ORDER BY assessor_name, assessor_code, a.assessment_id DESC
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, static function (array $row): array {
            return [
                $row['assessor_id'] ?? '', $row['assessor_code'] ?? '',
                Crypto::decrypt((string) ($row['assessor_name'] ?? '')),
                $row['completed_assessments'] ?? 0, $row['assessment_id'] ?? '',
                $row['assessment_name'] ?? '', $row['framework_code'] ?? '',
                $row['NIN_no'] ?? '', $row['fac_name'] ?? '',
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['start_date'] ?? '',
                $row['end_date'] ?? '', $row['completed_on'] ?? '', $row['cancelled_on'] ?? '', $row['status'] ?? ''
            ];
        });
    }

    /**
     * Returns display labels for the currently active deployment domain.
     */
    private static function domainLabels(): array
    {
        static $labels = null;
        if ($labels !== null) {
            return $labels;
        }

        $path = __DIR__ . '/../config/domain.json';
        $domain = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $configured = is_array($domain['labels'] ?? null) ? $domain['labels'] : [];
        $labels = [
            'facility' => (string) ($configured['facility'] ?? 'Facility'),
            'facilities' => (string) ($configured['facilities'] ?? 'Facilities'),
            'facility_code' => (string) ($configured['facility_code'] ?? 'NIN'),
            'assessor' => (string) ($configured['assessor'] ?? 'Assessor'),
            'assessment' => (string) ($configured['assessment'] ?? 'Assessment'),
            'assessments' => (string) ($configured['assessments'] ?? 'Assessments'),
            'department' => (string) ($configured['department'] ?? 'Department'),
            'departments' => (string) ($configured['departments'] ?? 'Departments'),
        ];
        return $labels;
    }

    /**
     * Formats a short, human-readable list for report descriptions.
     */
    private static function humanList(array $items): string
    {
        $items = array_values(array_filter($items, static fn ($item): bool => trim((string) $item) !== ''));
        $count = count($items);
        if ($count < 2) {
            return (string) ($items[0] ?? 'the enabled modules');
        }
        if ($count === 2) {
            return $items[0] . ' and ' . $items[1];
        }

        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
    }

    /**
     * Checks whether an optional module is enabled for the active domain.
     */
    private static function moduleEnabled(string $key): bool
    {
        static $modules = null;
        if ($modules === null) {
            $path = __DIR__ . '/../config/modules.json';
            $config = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
            $modules = is_array($config['modules'] ?? null) ? $config['modules'] : [];
        }

        return !isset($modules[$key]) || !empty($modules[$key]['enabled']);
    }

    /**
     * Handles facility type name processing for this API workflow.
     */
    private static function facilityTypeName(mixed $typeId): string
    {
        $id = (int)$typeId;
        static $map = null;

        if ($map === null) {
            $map = [];
            $path = __DIR__ . '/../config/masters/facility_types.json';
            $types = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];

            foreach (is_array($types) ? $types : [] as $type) {
                $map[(int)($type['fac_type_id'] ?? 0)] = (string)($type['facilities_type'] ?? '');
            }
        }

        return $map[$id] ?? (string)$typeId;
    }

    /**
     * Handles department map processing for this API workflow.
     */
    private static function departmentMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach ([
            __DIR__ . '/../config/masters/departmet.json',
            __DIR__ . '/../config/masters/department.json'
        ] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $rows = json_decode((string)file_get_contents($path), true);
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $id = (int)($row['fac_dept_id'] ?? $row['dept_id'] ?? $row['department_id'] ?? 0);
                $name = trim((string)($row['dept_name'] ?? $row['department_name'] ?? $row['name'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $map[$id] = $name;
                }
            }
        }

        return $map;
    }

    /**
     * Handles checkpoint map processing for this API workflow.
     */
    private static function checkpointMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $domainPath = __DIR__ . '/../config/domain.json';
        $domain = is_file($domainPath) ? json_decode((string)file_get_contents($domainPath), true) : [];
        $frameworkCode = preg_replace('/[^a-z0-9_-]/i', '', (string)($domain['default_framework'] ?? '')) ?: 'saqshi-nqas';
        $path = __DIR__ . '/../config/frameworks/' . $frameworkCode . '.json';
        if (!is_file($path)) {
            return $map;
        }

        $facilityTypes = json_decode((string)file_get_contents($path), true);
        if (!is_array($facilityTypes)) {
            return $map;
        }

        foreach ($facilityTypes as $facilityType) {
            foreach (($facilityType['departments'] ?? []) as $department) {
                foreach (($department['concerns'] ?? []) as $concern) {
                    foreach (($concern['subtypes'] ?? []) as $subtype) {
                        foreach (($subtype['checkpoints'] ?? []) as $checkpoint) {
                            $id = (string)($checkpoint['csqa_id'] ?? '');
                            if ($id === '') {
                                continue;
                            }
                            $map[$id] = [
                                'department_name' => (string)($department['dept_name'] ?? ''),
                                'concern_name' => trim((string)($concern['concern_des'] ?? '') . ' ' . (string)($concern['concern_name'] ?? '')),
                                'standard' => (string)($subtype['Reference_No'] ?? $checkpoint['c_subtype_Reference_No_fk'] ?? ''),
                                'measurable_element' => (string)($checkpoint['Measurable_Element'] ?? ''),
                                'checkpoint' => (string)($checkpoint['Checkpoint'] ?? ''),
                                'assessment_method' => (string)($checkpoint['Assessment_Method'] ?? '')
                            ];
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Handles performance indicator map processing for this API workflow.
     */
    private static function performanceIndicatorMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach ([
            __DIR__ . '/../config/performance/outcome.json',
            __DIR__ . '/../config/performance/kpi.json'
        ] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $data = json_decode((string)file_get_contents($path), true);
            if (!is_array($data)) {
                continue;
            }
            self::collectPerformanceIndicators($data, $map);
        }

        return $map;
    }

    /**
     * Handles collect performance indicators processing for this API workflow.
     */
    private static function collectPerformanceIndicators(array $node, array &$map): void
    {
        if (isset($node['indicator_id']) || isset($node['kpi_id']) || isset($node['outcome_id'])) {
            $id = (string)($node['indicator_id'] ?? $node['kpi_id'] ?? $node['outcome_id'] ?? '');
            $type = strtoupper((string)($node['indicator_type'] ?? (str_contains((string)($node['indicator_code'] ?? ''), 'KPI') ? 'KPI' : 'OUTCOME')));
            if ($id !== '') {
                $labels = self::indicatorFieldLabels($node);
                $details = [
                    'indicator_code' => (string)($node['indicator_code'] ?? $node['kpi_code'] ?? $node['outcome_code'] ?? ''),
                    'indicator_name' => (string)($node['indicator_name'] ?? $node['kpi_name'] ?? $node['outcome_name'] ?? ''),
                    'numerator_label' => $labels['numerator'],
                    'denominator_label' => $labels['denominator']
                ];
                $map[$id] = $details;
                $map[$type . ':' . $id] = $details;
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                self::collectPerformanceIndicators($value, $map);
            }
        }
    }

    /**
     * Handles indicator field labels processing for this API workflow.
     */
    private static function indicatorFieldLabels(array $indicator): array
    {
        $labels = ['numerator' => '', 'denominator' => ''];
        foreach (($indicator['fields'] ?? []) as $field) {
            $name = strtolower((string)($field['field_name'] ?? $field['field_id'] ?? ''));
            if ($name === 'numerator' || $name === 'n') {
                $labels['numerator'] = (string)($field['label'] ?? '');
            }
            if ($name === 'denominator' || $name === 'd') {
                $labels['denominator'] = (string)($field['label'] ?? '');
            }
        }
        return $labels;
    }

    /**
     * Handles facility where local processing for this API workflow.
     */
    private static function facilityWhereLocal(array $filters, string $alias): array
    {
        $where = ['1=1'];
        $types = '';
        $params = [];
        $map = [
            'state_code' => "{$alias}.state_code",
            'division' => "{$alias}.division",
            'district' => "{$alias}.Dist_Name",
            'block' => "{$alias}.Block_Name",
            'facility_type' => "{$alias}.Health_facilty_type"
        ];

        foreach ($map as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = "{$column} = ?";
                $types .= in_array($key, ['state_code', 'facility_type'], true) ? 'i' : 's';
                $params[] = in_array($key, ['state_code', 'facility_type'], true) ? (int)$filters[$key] : (string)$filters[$key];
            }
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "({$alias}.fac_name LIKE ? OR CAST({$alias}.NIN_no AS CHAR) LIKE ? OR CAST({$alias}.fac_id AS CHAR) LIKE ? OR {$alias}.Dist_Name LIKE ? OR {$alias}.Block_Name LIKE ?)";
            $like = '%' . $search . '%';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return ['sql' => 'WHERE ' . implode(' AND ', $where), 'types' => $types, 'params' => $params];
    }

    /**
     * Flattens the configured facility master for exports in non-database domains.
     */
    private static function facilityMasterRows(): array
    {
        static $rows = null;
        if ($rows !== null) {
            return $rows;
        }

        $rows = [];
        $domainPath = __DIR__ . '/../config/domain.json';
        $domain = is_file($domainPath) ? json_decode((string) file_get_contents($domainPath), true) : [];
        if (($domain['domain'] ?? '') !== 'education') {
            return $rows;
        }

        $path = __DIR__ . '/../config/masters/facilities.json';
        $states = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        if (!is_array($states)) {
            return $rows;
        }

        foreach ($states as $state) {
            foreach (($state['divisions'] ?? []) as $division) {
                foreach (($division['districts'] ?? []) as $district) {
                    foreach (($district['blocks'] ?? []) as $block) {
                        foreach (($block['facilities'] ?? []) as $facility) {
                            $rows[] = [
                                'state_code' => (int) ($state['state_id'] ?? 0),
                                'state_name' => (string) ($state['state_name'] ?? ''),
                                'division' => (string) ($division['division_name'] ?? ''),
                                'district' => (string) ($district['dist_name'] ?? ''),
                                'block' => (string) ($block['block_name'] ?? ''),
                                'fac_id' => $facility['fac_id'] ?? '',
                                'fac_name' => (string) ($facility['fac_name'] ?? ''),
                                'facility_type' => $facility['fac_type_id'] ?? '',
                                'nin_no' => $facility['nin_no'] ?? '',
                                'latitude' => $facility['latitude'] ?? '',
                                'longitude' => $facility['longitude'] ?? '',
                            ];
                        }
                    }
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => [
            $a['state_name'], $a['division'], $a['district'], $a['block'], $a['fac_name']
        ] <=> [
            $b['state_name'], $b['division'], $b['district'], $b['block'], $b['fac_name']
        ]);

        return $rows;
    }

    /**
     * Applies the State Report filters to a facility-master row.
     */
    private static function matchesMasterFilters(array $row, array $filters): bool
    {
        $exact = [
            'state_code' => 'state_code',
            'division' => 'division',
            'district' => 'district',
            'block' => 'block',
            'facility_type' => 'facility_type',
        ];
        foreach ($exact as $filter => $field) {
            if (($filters[$filter] ?? '') !== '' && (string) $filters[$filter] !== (string) $row[$field]) {
                return false;
            }
        }

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        if ($search === '') {
            return true;
        }
        $haystack = mb_strtolower(implode(' ', [
            $row['fac_id'], $row['nin_no'], $row['fac_name'], $row['district'], $row['block']
        ]));
        return str_contains($haystack, $search);
    }

    /**
     * Handles stream query processing for this API workflow.
     */
    private static function streamQuery(mysqli $con, string $sql, string $types, array $params, $out, callable $mapRow): void
    {
        $stmt = self::prepareAndBind($con, $sql, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            self::csvRow($out, $mapRow($row));
        }
        $stmt->close();
    }

    /**
     * Handles csv row processing for this API workflow.
     */
    private static function csvRow($out, array $fields): void
    {
        fputcsv($out, $fields, ',', '"', '', "\r\n");
    }

    /**
     * Handles prepare and bind processing for this API workflow.
     */
    private static function prepareAndBind(mysqli $con, string $sql, string $types = '', array $params = []): mysqli_stmt
    {
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Report query prepare failed: ' . $con->error);
        }

        if ($types !== '') {
            $refs = [];
            foreach ($params as $key => $value) {
                $refs[$key] = &$params[$key];
            }
            $stmt->bind_param($types, ...$refs);
        }

        return $stmt;
    }

    /**
     * Handles table exists local processing for this API workflow.
     */
    private static function tableExistsLocal(mysqli $con, string $table): bool
    {
        $stmt = $con->prepare("
            SELECT COUNT(*) AS table_count
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : [];
        $stmt->close();

        return (int)($row['table_count'] ?? 0) > 0;
    }

    /**
     * Handles column exists local processing for this API workflow.
     */
    private static function columnExistsLocal(mysqli $con, string $table, string $column): bool
    {
        $stmt = $con->prepare("
            SELECT COUNT(*) AS column_count
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : [];
        $stmt->close();

        return (int)($row['column_count'] ?? 0) > 0;
    }
}
