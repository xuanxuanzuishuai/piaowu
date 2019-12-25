CREATE TABLE `review_course_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT NOT NULL DEFAULT 0 COMMENT '学生id',
  `reviewer_id` INT NOT NULL DEFAULT 0 COMMENT '点评人 员工账号id',
  `date` INT NOT NULL DEFAULT 0 COMMENT '点评日期',
  `create_time` INT NOT NULL DEFAULT 0 COMMENT '创建时间',
  `audio` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '点评语音',
  `send_time` INT NOT NULL DEFAULT 0 COMMENT '发送时间 0表示未发送',
  PRIMARY KEY (`id`),
  INDEX `student_id_date` (`student_id` ASC, `date` ASC)
) COMMENT = '点评课点评记录';
