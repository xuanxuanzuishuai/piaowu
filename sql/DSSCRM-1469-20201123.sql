CREATE TABLE `employee_activity` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `name` varchar(255) NOT NULL COMMENT '活动名称',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '启用状态：0未启用；1已启用；2禁用；',
  `start_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '活动开始时间戳',
  `end_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '活动结束时间戳',
  `rules` text NOT NULL DEFAULT '' COMMENT '规则',
  `banner` varchar(255) NOT NULL DEFAULT '' COMMENT '活动banner',
  `figure` varchar(255) NOT NULL DEFAULT '' COMMENT '活动配图',
  `invite_text` text NOT NULL DEFAULT '' COMMENT '邀请语',
  `poster` varchar(500) NOT NULL DEFAULT '' COMMENT '海报模板',
  `remark` varchar(500) NOT NULL DEFAULT '' COMMENT '备注',
  `employee_share` text NOT NULL DEFAULT '' COMMENT '员工分享语',
  `employee_poster` text NOT NULL DEFAULT '' COMMENT '员工海报模板',
  `app_id` tinyint(4) NOT NULL DEFAULT '0' COMMENT '业务线APP ID',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='员工专项转介绍活动';

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('EMPLOYEE_ACTIVITY_ENV', '员工活动设置', 'invite_channel', '2612', '员工专项转介绍渠道'),
('EMPLOYEE_ACTIVITY_ENV', '员工活动设置', 'employee_activity_landing_url', 'http://referral-pre.xiaoyezi.com/operation/student/staffPoster', '员工专项转介绍海报生成页面URL'),
('HAS_REVIEW_COURSE', '学生当前进度', '0', '注册', ''),
('HAS_REVIEW_COURSE', '学生当前进度', '1', '付费体验卡', ''),
('HAS_REVIEW_COURSE', '学生当前进度', '2', '付费年卡', '');

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES 
('活动详情', '/op_web/employee_activity/detail', unix_timestamp(), 'get', 0, '活动详情', 0, 'employee_activity_detail', 1),
('活动修改', '/op_web/employee_activity/modify', unix_timestamp(), 'post', 0, '活动修改', 0, 'employee_activity_modify', 1),
('添加活动', '/op_web/employee_activity/add', unix_timestamp(), 'post', 0, '添加活动', 0, 'employee_activity_add', 1),
('修改状态', '/op_web/employee_activity/update_status', unix_timestamp(), 'post', 0, '修改状态', 0, 'employee_activity_update_status', 1)
;
