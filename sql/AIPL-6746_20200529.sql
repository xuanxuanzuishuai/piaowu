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


INSERT INTO `wechat_config` ( `id`, `type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid` )
VALUES
	( 8, 1, '{\"template_id\":\"G1hleUfvk7_lEi-Y5-2m5YBROuuySpwmQz9JcgmTHtM\",\"vars\":{\"first\":{\"value\":\"\\u60a8\\u7684\\u7ec3\\u7434\\u4efb\\u52a1\\u5df2\\u5b8c\\u6210\\uff0c\\u53ef\\u53c2\\u52a0\\u8fd4\\u73b0\\u6d3b\\u52a8\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\"},\"keyword1\":{\"value\":\"\\u4e0a\\u4f20\\u622a\\u56fe\\u9886\\u8fd4\\u73b0\"},\"keyword2\":{\"value\":\"\\u4e0a\\u4f20\\u670b\\u53cb\\u5708\\u5206\\u4eab\\u622a\\u56fe\"},\"keyword3\":{\"value\":\"\\u5f85\\u4e0a\\u4f20\"},\"remark\":{\"value\":\"\\u70b9\\u6b64\\u6d88\\u606f\\uff0c\\u7acb\\u5373\\u4e0a\\u4f20\\u670b\\u53cb\\u5708\\u5206\\u4eab\\u622a\\u56fe\"}}}', 'event', 3, 'custom', '', 1582515283, 0, 10001, 0 );

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
	('事件活动列表', '/org_web/collection/event_task_list', 1591161813, 'get', 0, '', 0, 'event_task_list', 1 );