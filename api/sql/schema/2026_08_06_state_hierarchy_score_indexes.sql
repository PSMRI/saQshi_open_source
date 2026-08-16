-- Supports latest-round score aggregation in State School Drill-down.
CREATE INDEX idx_assessment_completed_round_facility
    ON assessment_master (status, round_id, fac_id_fk);

CREATE INDEX idx_response_assessment_checkpoint
    ON assessment_response (assessment_id, checkpoint_id);
