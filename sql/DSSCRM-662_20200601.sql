# 返现添加菜单
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
		'返现截图审核',
		'/org_web/return_money_poster/list',
		1590980182,
		'get',
		1,
		'返现截图审核',
		513,
		'return_money_poster_list',
	1
	),
	(
		'社群返现截图审核通过',
		'/org_web/return_money_poster/approved',
		1590997338,
		'post',
		0,
		'',
		513,
		'return_money_appprove',
	1
	),
	(
		'社群截图拒绝',
		'/org_web/return_money_poster/refused',
		1590997889,
		'post',
		0,
		'',
		513,
		'return_money_refused',
	1
	),
	(
		'返现红包审核',
		'/org_web/community_red_pack/awardList',
		1591004481,
		'get',
		1,
		'返现红包审核',
		513,
		'community_red_pack_check',
	1
	),
	(
		'社群返现红包搜索相关配置',
		'/org_web/community_red_pack/config',
		1591152526,
		'get',
		0,
		'',
		513,
		'community_red_pack_config',
	1
	),
	(
		'社群返现红包发放/不发放',
		'/org_web/community_red_pack/updateAward',
		1591161813,
		'post',
		0,
		'',
		513,
		'community_red_pack_update',
	1
	);

# 返现审核通过模板消息推送配置

INSERT INTO `wechat_config` (
	`id`,
	`type`,
	`content`,
	`msg_type`,
	`content_type`,
	`event_type`,
	`event_key`,
	`create_time`,
	`update_time`,
	`create_uid`,
	`update_uid`
)
VALUES
	(
		5,
		1,
		'{\"template_id\":\"G1hleUfvk7_lEi-Y5-2m5YBROuuySpwmQz9JcgmTHtM\",\"vars\":{\"first\":{\"value\":\"\\u60a8\\u4e0a\\u4f20\\u7684\\u622a\\u56fe\\u5ba1\\u6838\\u7ed3\\u675f\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\"},\"keyword1\":{\"value\":\"\\u4e0a\\u4f20\\u622a\\u56fe\\u9886\\u8fd4\\u73b0\"},\"keyword2\":{\"value\":\"\\u4e0a\\u4f20\\u5206\\u4eab\\u622a\\u56fe\"},\"keyword3\":{\"value\":\"\\u5df2\\u901a\\u8fc7\"},\"remark\":{\"value\":\"\\u70b9\\u6b64\\u6d88\\u606f\\uff0c\\u67e5\\u770b\\u8be6\\u60c5\"}}}',
		'event',
		3,
		'custom',
		'',
		1582515283,
		0,
		10001,
	0
	),
	(
		6,
		1,
		'{\"template_id\":\"G1hleUfvk7_lEi-Y5-2m5YBROuuySpwmQz9JcgmTHtM\",\"vars\":{\"first\":{\"value\":\"\\u60a8\\u4e0a\\u4f20\\u7684\\u622a\\u56fe\\u5ba1\\u6838\\u7ed3\\u675f\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\"},\"keyword1\":{\"value\":\"\\u4e0a\\u4f20\\u622a\\u56fe\\u9886\\u8fd4\\u73b0\"},\"keyword2\":{\"value\":\"\\u4e0a\\u4f20\\u5206\\u4eab\\u622a\\u56fe\"},\"keyword3\":{\"value\":\"\\u672a\\u901a\\u8fc7\",\"color\":\"#FF0000\"}},\"remark\":{\"value\":\"\\u70b9\\u6b64\\u6d88\\u606f\\uff0c\\u67e5\\u770b\\u8be6\\u60c5\"}}',
		'event',
		3,
		'custom',
		'',
		1582515283,
		0,
		10001,
	0
	);

-- 发送微信红包的配置语

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`)
VALUES
('WE_CHAT_RED_PACK', '微信红包显示语', 'NORMAL_PIC_WORD', '{\"act_name\":\"AI\\u966a\\u7ec3\\u8f6c\\u4ecb\\u7ecd\",\"send_name\":\"\\u9080\\u8bf7\\u597d\\u53cb\\u5956\\u52b1\",\"wishing\":\"\\u63a8\\u8350\\u591a\\u591a\\uff0c\\u5956\\u52b1\\u591a\\u591a\"}'),
('WE_CHAT_RED_PACK', '微信红包显示语', 'COMMUNITY_PIC_WORD', '{\"act_name\":\"\\u793e\\u7fa4\\u8fd4\\u73b0\\u7ea2\\u5305\",\"send_name\":\"\\u7ec3\\u7434\\u5956\\u52b1\\u7ea2\\u5305\",\"wishing\":\"\\u591a\\u591a\\u7ec3\\u7434\\uff0c\\u5feb\\u5feb\\u6210\\u957f\"}'),
('COMMUNITY_CONFIG', '社群返现上传截图地址', 'COMMUNITY_UPLOAD_POSTER_URL', 'https://dss-weixin.xiongmaopeilian.com/student/returnMoney')