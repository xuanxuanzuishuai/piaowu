ALTER TABLE `student`
  CHANGE COLUMN `collection_id` `collection_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '集合ID' ,
  CHANGE COLUMN `assistant_id` `assistant_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '助教ID' ,
  CHANGE COLUMN `course_manage_id` `course_manage_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '课管ID' ;
