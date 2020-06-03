ALTER TABLE `dss_dev`.`collection`
  ADD COLUMN `trial_type` INT NOT NULL DEFAULT 0 COMMENT '体验课类型' AFTER `teaching_type`;
