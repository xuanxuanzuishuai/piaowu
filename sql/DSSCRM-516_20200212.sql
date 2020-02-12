ALTER TABLE `channel`
  ADD COLUMN `app_id` INT(10) NOT NULL DEFAULT 0 COMMENT '业务线id' AFTER `update_time`;
