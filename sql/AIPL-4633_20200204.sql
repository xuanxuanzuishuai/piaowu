ALTER TABLE `feedback`
  ADD COLUMN `lesson_id` INT NULL DEFAULT 0 COMMENT '曲目id',
  ADD COLUMN `client_info` VARCHAR(255) NULL COMMENT '设备信息',
  ADD COLUMN `tags` INT NULL DEFAULT 0 COMMENT '标签bitmap';
