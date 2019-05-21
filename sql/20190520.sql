
ALTER TABLE `student_account_log`
ADD COLUMN `schedule_id` INT(11) NULL DEFAULT '0' COMMENT '课程id' AFTER `new_balance`;

ALTER TABLE `schedule_user`
CHANGE COLUMN `user_status` `user_status` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '学生子状态1 已预约 3 已请假 4 已出席 老师子状态1 已分配 2 已请假 3 已出席' ,
ADD COLUMN `is_deduct` INT(1) NOT NULL DEFAULT '0' COMMENT '是否扣费 1 是' AFTER `price`;


DELETE FROM `dict` WHERE `id`='186';
DELETE FROM `dict` WHERE `id`='183';
DELETE FROM `dict` WHERE `id`='190';

DELETE FROM `dict` WHERE `id`='477';
DELETE FROM `dict` WHERE `id`='478';
DELETE FROM `dict` WHERE `id`='479';
DELETE FROM `dict` WHERE `id`='480';
DELETE FROM `dict` WHERE `id`='481';
DELETE FROM `dict` WHERE `id`='482';
DELETE FROM `dict` WHERE `id`='483';
DELETE FROM `dict` WHERE `id`='484';

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('班级名搜索', '/schedule/task/searchName', '1557731681', 'get', '0', '', '0', 'class_search');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `parent_id`, `unique_en_name`) VALUES ('学生课次扣费确认框', '/schedule/schedule/deduct', '1557731681', 'get', '0', '0', 'deduct_sure');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `parent_id`, `unique_en_name`) VALUES ('学生课次扣费', '/schedule/schedule/deductAmount', '1557731681', 'post', '0', '0', 'student_deduct');
