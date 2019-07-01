ALTER TABLE `student`
  ADD COLUMN `trial_start_date` INT(11) NULL DEFAULT NULL COMMENT '试用开始时间' AFTER `sub_end_date`,
  ADD COLUMN `trial_end_date` INT(11) NULL DEFAULT NULL COMMENT '试用结束时间' AFTER `trial_start_date`;

ALTER TABLE `student`
  CHANGE COLUMN `sub_start_date` `sub_start_date` INT(11) NOT NULL DEFAULT 0 COMMENT '订阅开始日期' ,
  CHANGE COLUMN `sub_end_date` `sub_end_date` INT(11) NOT NULL DEFAULT 0 COMMENT '订阅结束日期' ,
  CHANGE COLUMN `trial_start_date` `trial_start_date` INT(11) NOT NULL DEFAULT 0 COMMENT '试用开始时间' ,
  CHANGE COLUMN `trial_end_date` `trial_end_date` INT(11) NOT NULL DEFAULT 0 COMMENT '试用结束时间' ;