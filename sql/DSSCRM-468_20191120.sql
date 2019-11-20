ALTER TABLE `student`
  ADD COLUMN `has_review_course` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否有点评课 0无 1有';