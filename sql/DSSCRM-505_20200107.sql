CREATE TABLE `review_course_task` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT NOT NULL,
  `review_date` INT NOT NULL,
  `play_date` INT NOT NULL COMMENT '演奏时间',
  `reviewer_id` INT NOT NULL COMMENT '点评人',
  `create_time` INT NOT NULL COMMENT '创建时间',
  `status` TINYINT NOT NULL COMMENT '点评状态 0未点评 1已点评',
  `update_time` INT NOT NULL COMMENT '更新时间',
  `review_audio` VARCHAR(150) NULL COMMENT '点评音频',
  PRIMARY KEY (`id`))
  COMMENT = '点评课任务';

ALTER TABLE `review_course_task`
  ADD COLUMN `sum_duration` INT NOT NULL COMMENT '上课时间' AFTER `play_date`;

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
  VALUES ('REVIEW_COURSE_CONFIG', '点评老师id', 'reviewer_ids', '1', '点评老师id列表');