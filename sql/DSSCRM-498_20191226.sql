CREATE TABLE `play_class_record` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL COMMENT '学生id',
  `lesson_id` INT UNSIGNED NOT NULL COMMENT '曲目id',
  `start_time` INT UNSIGNED NOT NULL COMMENT '开始时间',
  `duration` INT UNSIGNED NOT NULL COMMENT '时长',
  `create_time` INT UNSIGNED NOT NULL COMMENT '创建时间',
  `update_time` INT UNSIGNED NOT NULL COMMENT '更新时间 ai服务处理完成后异步更新数据',
  `class_session_id` VARCHAR(38) NOT NULL COMMENT '上课模式记录id 用于异步更新',
  `best_record_id` INT UNSIGNED NOT NULL COMMENT '最佳演奏id 异步更新',
  PRIMARY KEY (`id`))
  COMMENT = '上课模式演奏记录';

ALTER TABLE `play_class_record`
  ADD INDEX `session_id` (`class_session_id` ASC),
  ADD INDEX `student_id_create_time` (`student_id` ASC, `lesson_id` ASC, `create_time` ASC);