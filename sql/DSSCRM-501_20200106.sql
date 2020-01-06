CREATE TABLE `play_class_record_message` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `create_time` INT UNSIGNED NOT NULL COMMENT '创建时间',
  `body` TEXT NULL COMMENT '消息内容',
  PRIMARY KEY (`id`),
  INDEX `create_time` (`create_time` ASC))
COMMENT = '用户演奏数据队列消息';