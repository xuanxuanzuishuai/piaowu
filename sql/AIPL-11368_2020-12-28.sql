CREATE TABLE `operation_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '活动名称',
  `app_id` tinyint(4) NOT NULL COMMENT '该活动所属业务线',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) COMMENT='运营系统所有活动表';

insert into operation_activity (id,`name`, app_id, create_time, update_time)

select id,`name`,app_id, create_time, update_time from employee_activity;

ALTER TABLE `employee_activity`
ADD COLUMN `op_activity_id` int(11) NOT NULL COMMENT '运营系统总活动表id' AFTER `app_id`;

update employee_activity set op_activity_id = id;

INSERT INTO `operation_activity` (`name`, `app_id`, `create_time`, `update_time`)
VALUES
	('2020双旦专题活动', 8, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());