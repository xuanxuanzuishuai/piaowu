ALTER TABLE `play_class_record_message`
  ADD COLUMN `status` INT NOT NULL DEFAULT 0 COMMENT '状态 0未处理 1成功 2失败' AFTER `body`;
