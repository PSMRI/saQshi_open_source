-- Completed assessments are immutable. These triggers also protect against
-- direct SQL writes which bypass the assessment API's ACTIVE-status check.

DROP TRIGGER IF EXISTS trg_assessment_response_block_completed_insert;
DROP TRIGGER IF EXISTS trg_assessment_response_block_completed_update;

DELIMITER $$

CREATE TRIGGER trg_assessment_response_block_completed_insert
BEFORE INSERT ON assessment_response
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1
        FROM assessment_master
        WHERE assessment_id = NEW.assessment_id
          AND UPPER(COALESCE(status, '')) = 'COMPLETED'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Completed assessment responses are locked';
    END IF;
END$$

CREATE TRIGGER trg_assessment_response_block_completed_update
BEFORE UPDATE ON assessment_response
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1
        FROM assessment_master
        WHERE assessment_id = OLD.assessment_id
          AND UPPER(COALESCE(status, '')) = 'COMPLETED'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Completed assessment responses are locked';
    END IF;
END$$

DELIMITER ;
