CREATE TABLE `play_class_part` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_session_id` VARCHAR(38) NOT NULL COMMENT '课堂id',
  `record_id` INT NOT NULL DEFAULT 0 COMMENT '测评id',
  `duration` INT NOT NULL DEFAULT 0 COMMENT '测评时长',
  `create_time` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`))
  COMMENT = '上课模式分段测评数据';
