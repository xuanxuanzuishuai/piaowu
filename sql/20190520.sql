
ALTER TABLE `student_account_log`
ADD COLUMN `schedule_id` INT(11) NULL DEFAULT '0' COMMENT '课程id' AFTER `new_balance`;

ALTER TABLE `schedule_user`
CHANGE COLUMN `user_status` `user_status` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '学生子状态1 已预约 3 已请假 4 已出席 老师子状态1 已分配 2 已请假 3 已出席' ,
ADD COLUMN `is_deduct` INT(1) NOT NULL DEFAULT '0' COMMENT '是否扣费 1 是' AFTER `price`;


DELETE FROM `dict` WHERE `id`='186';
DELETE FROM `dict` WHERE `id`='183';
DELETE FROM `dict` WHERE `id`='190';