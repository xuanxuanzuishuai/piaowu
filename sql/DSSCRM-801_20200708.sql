ALTER TABLE `student`
ADD COLUMN `password` VARCHAR(50) NULL COMMENT '登陆密码' AFTER `course_manage_id`;