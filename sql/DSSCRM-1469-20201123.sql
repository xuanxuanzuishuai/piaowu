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
  ('EMPLOYEE_ACTIVITY_ENV', '员工活动设置', 'employee_activity_landing_url', 'https://referral-pre.xiaoyezi.com/market/index', '员工专项转介绍海报生成页面URL');
