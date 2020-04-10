ALTER TABLE `ai_play_record`
  ADD COLUMN `old_format` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否是5.0以前版本的数据' AFTER `input_type`;
