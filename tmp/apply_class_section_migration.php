<?php
require __DIR__ . '/../api/assets/conn/db.php';

$exists = $con->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_assessor_info' AND COLUMN_NAME = 'class_section' LIMIT 1");
if (!$exists || !$exists->fetch_assoc()) {
    if (!$con->query("ALTER TABLE assessment_assessor_info ADD COLUMN class_section VARCHAR(100) NULL AFTER subject_name")) {
        throw new RuntimeException($con->error);
    }
}

$verify = $con->query("SHOW COLUMNS FROM assessment_assessor_info LIKE 'class_section'");
echo json_encode($verify->fetch_assoc(), JSON_UNESCAPED_SLASHES), PHP_EOL;
