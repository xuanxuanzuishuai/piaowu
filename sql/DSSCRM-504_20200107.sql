CREATE TABLE `review_course_calendar` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `create_time` INT NOT NULL,
  `review_date` INT NOT NULL COMMENT '点评日期 yyyymmdd',
  `play_date` INT NOT NULL COMMENT '点评对应的演奏数据开始日期 yyyymmdd',
  `status` TINYINT NOT NULL COMMENT '状态 0作废 1生效',
  `operator_id` INT NOT NULL COMMENT '操作人id',
  `update_time` INT NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `review_date` (`review_date` ASC))
COMMENT = '点评课工作日历';