set @parentMenuId = (select id from privilege where unique_en_name = 'community_operation');
INSERT INTO `privilege` (
	`name`,
	`uri`,
	`created_time`,
	`method`,
	`is_menu`,
	`menu_name`,
	`parent_id`,
	`unique_en_name`,
	`status`
)
VALUES
	(
		'专属售卖链接',
		'/org_web/personal_link/list',
		1595309548,
		'get',
		1,
		'专属售卖链接',
		@parentMenuId,
		'personal_sale_link',
	1
	);

INSERT INTO `dict` (
	`type`,
	`key_name`,
	`key_code`,
	`key_value`
)
VALUES
	(
		'personal_link_package_id',
		'专属售卖链接相关包id',
		'package_id',
		'10438,10439,10440,10441,10448,10444'
	);

ALTER TABLE `gift_code`
ADD COLUMN `employee_uuid` varchar(32) NULL COMMENT '员工的uuid' AFTER `bill_package_id`,
ADD COLUMN `employee_dept_info` varchar(32) NULL COMMENT '员工的部门信息' AFTER `employee_uuid`;