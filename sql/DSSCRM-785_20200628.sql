CREATE TABLE `dept` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '部门id',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '部门名字',
  `relation` varchar(32) NOT NULL DEFAULT '' COMMENT '部门树',
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '父id',
  `status` tinyint(4) NOT NULL COMMENT '部门状态 1 正常 0 不可用',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间戳',
  `update_time` int(11) DEFAULT NULL COMMENT '更新时间戳',
  `operator` int(11) DEFAULT NULL COMMENT '操作人id',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) COMMENT='部门表';

CREATE TABLE `dept_privilege` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dept_id` int(11) NOT NULL COMMENT '组id',
  `data_type` tinyint(4) NOT NULL COMMENT '数据类型',
  `privilege_type` tinyint(4) NOT NULL COMMENT '权限类型',
  `privilege_custom` varchar(64) DEFAULT NULL COMMENT '自定义权限',
  `create_time` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL COMMENT '是否启用',
  `update_time` int(11) DEFAULT NULL,
  `operator` int(11) DEFAULT NULL COMMENT '操作人id',
  PRIMARY KEY (`id`),
  KEY `data_type` (`dept_id`,`data_type`)
) COMMENT='组权限表';


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('DEPT_DATA_TYPE_NAME', '组权限数据类型名', '1', '学生');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('DEPT_PRIVILEGE_TYPE_NAME', '组权限类型名', '0', '不可见所有组');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('DEPT_PRIVILEGE_TYPE_NAME', '组权限类型名', '1', '可见本组');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('DEPT_PRIVILEGE_TYPE_NAME', '组权限类型名', '2', '可见下属组');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('DEPT_PRIVILEGE_TYPE_NAME', '组权限类型名', '3', '可见所有组');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('DEPT_PRIVILEGE_TYPE_NAME', '组权限类型名', '4', '可见自定义组');

SET @parentId = (select id from privilege where unique_en_name = 'employee_management');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `parent_id`, `unique_en_name`) VALUES ('部门列表', '/org_web/dept/list', '1594008389', 'get', '1', @parentId, 'dept_list');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `parent_id`, `unique_en_name`) VALUES ('部门树形菜单', '/org_web/dept/tree', '1594008389', 'get', '0', '0', 'dept_tree');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `parent_id`, `unique_en_name`) VALUES ('部门编辑', '/org_web/dept/modify', '1594008389', 'post', '0', '0', 'dept_modify');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `parent_id`, `unique_en_name`) VALUES ('部门权限', '/org_web/dept/dept_privilege', '1594008389', 'get', '0', '0', 'dept_dept_privilege');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `parent_id`, `unique_en_name`) VALUES ('部门权限编辑', '/org_web/dept/privilege_modify', '1594008389', 'post', '0', '0', 'dept_privilege_modify');
