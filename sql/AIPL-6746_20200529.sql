ALTER TABLE share_poster
  ADD COLUMN type TINYINT(1) UNSIGNED DEFAULT 1
COMMENT '海报类型：1上传截图领奖 2上传截图领返现'
  AFTER `award_id`;

ALTER TABLE collection
  ADD COLUMN `event_id` INT(11) UNSIGNED NOT NULL
COMMENT '关联事件ID'
  AFTER `teaching_type`;

ALTER TABLE collection
  ADD COLUMN `task_id` INT(11) UNSIGNED NOT NULL
COMMENT '任务ID'
  AFTER `event_id`;

ALTER TABLE `message_record`
  ADD COLUMN `activity_type` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
COMMENT '活动类型1上传截图领奖2上传截图领返现'
  AFTER `update_time`;


INSERT INTO `wechat_config` ( `type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid` )
VALUES
	( 1,
	'{"title":"上传截图领奖励红包","description":"上传朋友圈分享截图，领取练琴奖励红包","url":"/student/returnMoney","picurl":"/prod/referral_activity//referral_poster/871CEA0C-E428-471F-A281-DEF434458EF9.png"}',
	'custom',
	1,
	'share_news',
	'',
	1583918495,
	0,
	0,
	0 );

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
	('事件活动列表', '/org_web/collection/event_task_list', 1591161813, 'get', 0, '', 0, 'event_task_list', 1 );