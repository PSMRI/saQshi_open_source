<?php
require_once __DIR__ . '/../assets/conn/db.php';
$con->query("CREATE TABLE IF NOT EXISTS facility_assessment_round (round_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, fac_id INT NOT NULL, round_no INT NOT NULL DEFAULT 1, status VARCHAR(20) NOT NULL DEFAULT 'OPEN', started_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, completed_on TIMESTAMP NULL, UNIQUE KEY uq_round_facility_number (fac_id, round_no))");
$column = $con->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_master' AND COLUMN_NAME = 'round_id'")->fetch_assoc();
if (!$column) $con->query("ALTER TABLE assessment_master ADD COLUMN round_id BIGINT NULL");
$rows = $con->query("SELECT DISTINCT asa.fac_id_fk, asa.dept_id, asa.assessment_id FROM assessment_section_assignee asa ORDER BY asa.fac_id_fk, asa.dept_id, asa.assessment_id")->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $row) {
    $n = 1;
    $prior = $con->prepare("SELECT COUNT(*) total FROM assessment_section_assignee WHERE fac_id_fk = ? AND dept_id = ? AND assessment_id < ?");
    $prior->bind_param('iii', $row['fac_id_fk'], $row['dept_id'], $row['assessment_id']); $prior->execute();
    $n += (int)($prior->get_result()->fetch_assoc()['total'] ?? 0);
    $round = $con->prepare("SELECT round_id FROM facility_assessment_round WHERE fac_id = ? AND round_no = ? LIMIT 1");
    $round->bind_param('ii', $row['fac_id_fk'], $n); $round->execute(); $found = $round->get_result()->fetch_assoc();
    if (!$found) { $create = $con->prepare("INSERT INTO facility_assessment_round (fac_id, round_no, status) VALUES (?, ?, 'OPEN')"); $create->bind_param('ii', $row['fac_id_fk'], $n); $create->execute(); $roundId = (int)$con->insert_id; } else $roundId = (int)$found['round_id'];
    $update = $con->prepare("UPDATE assessment_master SET round_id = ? WHERE assessment_id = ?"); $update->bind_param('ii', $roundId, $row['assessment_id']); $update->execute();
}
