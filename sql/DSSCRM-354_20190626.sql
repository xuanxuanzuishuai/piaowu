ALTER TABLE `student`
  ADD COLUMN `trial_start_date` INT(11) NULL DEFAULT NULL COMMENT '试用开始时间' AFTER `sub_end_date`,
  ADD COLUMN `trial_end_date` INT(11) NULL DEFAULT NULL COMMENT '试用结束时间' AFTER `trial_start_date`;
