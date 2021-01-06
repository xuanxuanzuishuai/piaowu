USE operation_dev;
CREATE TABLE `message_push_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `name` char(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '规则名称',
  `type` tinyint(3) unsigned NOT NULL COMMENT '推送形式:1客服消息;2模板消息;',
  `target` tinyint(3) unsigned NOT NULL COMMENT '推送人群:1:全部用户;2:当日开班用户;3:开班第7天用户;4:年卡C级用户;5:体验C级用户;6:注册C级用户;',
  `is_active` tinyint(3) unsigned NOT NULL COMMENT '是否启用',
  `time` json NOT NULL COMMENT '推送时间',
  `content` json DEFAULT NULL COMMENT '文案内容',
  `remark` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '备注',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='自动推送消息规则';


CREATE TABLE `message_manual_push_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `type` tinyint(1) NOT NULL COMMENT '推送形式',
  `file` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户EXCEL地址',
  `data` json NOT NULL COMMENT '发送数据JSON',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='消息手动推送记录';

INSERT INTO `message_push_rules` (`id`, `name`, `type`, `target`, `is_active`, `time`, `content`, `remark`, `create_time`, `update_time`)
VALUES
	(1, '首关欢迎语推送', 1, 1, 0, '{\"desc\": \"当用户关注时\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"嗨！陪孩子练琴，您辛苦了[玫瑰]\\n\\n小叶子智能陪练，是清华爸爸团队联合国内外钢琴教育专家打造的全球首创，AI驱动的陪练App。\\n\\nAI实时纠正错音，孩子练琴不盲目\\n练完打分有排行，孩子练琴兴趣高\\n每天发练琴报告，妈妈再也不用陪\\n\\n点击观看\\\\ud83d\\\\udc47\\n<a href=\\\"http://mp.weixin.qq.com/s?__biz=MzU1NTkxNzY3MQ==&mid=100000231&idx=1&sn=d11c0cfa5a19418dab0c99c1d8b9b77a&chksm=7bcc49ef4cbbc0f9f923570f89f7389933ee84750cd1751f8be23084d4311f740aff1d9c8775#rd\\\">钢琴大师郎朗演奏《肖邦练习曲》，亲自测试小叶子智能陪练，赞不绝口！</a>\\n\\n点击体验\\\\ud83d\\\\udc47\\n<a href=\\\"https://referral.xiaoyezi.com/market/landing20200929_d5?ad=0&channel_id=1244\\\">限时9.9元5天随便用！</a>\\n\\n如已购买，绑定您付费使用的手机号完成开课准备\\\\ud83d\\\\udc47\\n<a href=\\\"https://dss-weixin.xiongmaopeilian.com/Bind/student/add\\\">立即绑定！</a>\\n\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"\\\\ud83d\\\\ude04\"}, {\"key\": \"image\", \"qr_x\": 533, \"qr_y\": 92, \"type\": 2, \"value\": \"dev/img//auto_push/735c3432fc86eabeae4813fd00f41234.png\", \"content1\": \"\", \"content2\": \"\", \"qr_width\": 154, \"qr_height\": 154, \"poster_width\": 750, \"poster_height\": 1334}]', '首关', 1600848505, 1602740632),
	(2, '红包领取成功后推送', 1, 1, 1, '{\"desc\": \"用户领取红包成功时\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"跟我做，只需简单两步，领取现金奖励！\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"还在为孩子练琴发愁吗？\"}, {\"key\": \"image\", \"qr_x\": 533, \"qr_y\": 92, \"type\": 2, \"value\": \"prod/referral//referral_poster/8c7fde0e744a908f899de5898f656c33.jpg\", \"content1\": \"\", \"content2\": \"\", \"qr_width\": 154, \"qr_height\": 154, \"poster_width\": 750, \"poster_height\": 1334}]', '', 1600848505, 1602331288),
	(3, '开班日当天消息推送', 1, 2, 1, '{\"desc\": \"开班日当天18点\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"跟我做，只需简单两步，领取现金奖励！\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"还在为孩子练琴发愁吗？\"}, {\"key\": \"image\", \"qr_x\": 63, \"qr_y\": 92, \"type\": 2, \"value\": \"prod/referral//referral_poster/8c7fde0e744a908f899de5898f656c33.jpg\", \"qr_width\": 154, \"qr_height\": 154, \"poster_width\": 750, \"poster_height\": 1050}]', '', 1600848505, 1602312204),
	(4, '开班第7天消息推送', 1, 3, 1, '{\"desc\": \"开班第7天18点\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"跟我做，只需简单两步，领取现金奖励！\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"还在为孩子练琴发愁吗？\"}, {\"key\": \"image\", \"qr_x\": 63, \"qr_y\": 92, \"type\": 2, \"value\": \"prod/referral//referral_poster/8c7fde0e744a908f899de5898f656c33.jpg\", \"qr_width\": 154, \"qr_height\": 154, \"poster_width\": 750, \"poster_height\": 1050}]', '', 1600848505, 1602227265),
	(5, '年卡C交互后消息推送', 1, 4, 1, '{\"desc\": \"与公众号交互10分钟后\", \"delay_time\": 600}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"跟我做，只需简单两步，领取现金奖励！\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"还在为孩子练琴发愁吗？\"}, {\"key\": \"image\", \"qr_x\": 63, \"qr_y\": 92, \"type\": 2, \"value\": \"prod/referral//referral_poster/8c7fde0e744a908f899de5898f656c33.jpg\", \"qr_width\": 154, \"qr_height\": 154, \"poster_width\": 750, \"poster_height\": 1050}]', '', 1600848505, 1602322334),
	(6, '体验C交互后消息推送', 1, 5, 1, '{\"desc\": \"与公众号交互10分钟后\", \"delay_time\": 600}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"跟我做，只需简单两步，领取现金奖励！\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"还在为孩子练琴发愁吗？\"}, {\"key\": \"image\", \"qr_x\": 63, \"qr_y\": 92, \"type\": 2, \"value\": \"prod/referral//referral_poster/8c7fde0e744a908f899de5898f656c33.jpg\", \"qr_width\": 154, \"qr_height\": 154, \"poster_width\": 750, \"poster_height\": 1050}]', '', 1600848505, 1600848505),
	(7, '注册C交互后消息推送', 1, 6, 1, '{\"desc\": \"与公众号交互10分钟后\", \"delay_time\": 600}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"跟我做，只需简单两步，领取现金奖励！\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"还在为孩子练琴发愁吗？\"}, {\"key\": \"image\", \"qr_x\": 63, \"qr_y\": 92, \"type\": 2, \"value\": \"prod/referral//referral_poster/8c7fde0e744a908f899de5898f656c33.jpg\", \"qr_width\": 154, \"qr_height\": 154, \"poster_width\": 750, \"poster_height\": 1050}]', '', 1600848505, 1602311747),
	(8, '体验用户绑定手机号消息推', 1, 7, 1, '{\"desc\": \"绑定成功10分钟后\", \"delay_time\": 600}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"有奖转介绍活动开始啦！想要红包和App使用时长就马上和好朋友分享小叶子智能陪练吧！\\n【1】复制以下文案+海报发送至朋友圈\\n【2】好友扫码购课就送您最高188元现金红包呦~\\n多邀多赠，上不封顶！\\n\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"注册用户交互后推送\"}]', '', 1600848505, 1603332736),
	(9, '年卡用户绑定手机号消息推', 1, 8, 1, '{\"desc\": \"绑定成功立即\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"有奖转介绍活动开始啦！想要红包和App使用时长就马上和好朋友分享小叶子智能陪练吧！\\n【1】复制以下文案+海报发送至朋友圈\\n【2】好友扫码购课就送您最高188元现金红包呦~\\n多邀多赠，上不封顶！\\n\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"注册用户交互后推送\"}]', '', 1600848505, 1603332736),
	(10, '年卡支付成功后消息推送', 1, 8, 1, '{\"desc\": \"支付成功立即推送\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"年卡支付成功后消息推送\"}]', '', 1600848505, 1603332736);

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('STUDENT_INVITE', '学生转介绍渠道', 'NORMAL_STUDENT_INVITE_STUDENT', '1220', '学生点击邀请好友渠道'),
  ('STUDENT_INVITE', '学生转介绍渠道', 'BUY_NORMAL_STUDENT_INVITE_STUDENT', '2381', '正式包购买后转介绍'),
  ('STUDENT_INVITE', '学生转介绍渠道', 'BUY_TRAIL_REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT', '2781', '智能体验营小程序已购买转介绍渠道id'),
  ('message_rule_config', '消息推送规则', 'assign_template_id', '7nD1tVhctOSBIynkb6n9gq_xa0UCO8Y0c8_ub3uhWAY', '手动push消息默认模板id'),
  ('message_rule_config', '消息推送规则', 'how_long_not_invite', '604800', '7天没有过转介绍行为'),
  ('message_rule_config', '消息推送规则', 'how_long_not_result', '2592000', '30天没有转介绍结果'),
  ('message_rule_config', '消息推送规则', 'register_user_c_rule_id', '7', '注册c用户相关规则'),
  ('message_rule_config', '消息推送规则', 'start_class_day_rule_id', '3', '开班日相关规则'),
  ('message_rule_config', '消息推送规则', 'start_class_seven_day_rule_id', '4', '开班日第七天相关规则'),
  ('message_rule_config', '消息推送规则', 'subscribe_rule_id', '1', '关注相关规则'),
  ('message_rule_config', '消息推送规则', 'trail_user_c_rule_id', '6', '体验c用户相关规则'),
  ('message_rule_config', '消息推送规则', 'year_pay_rule_id', '10', '年卡支付成功后消息推送'),
  ('message_rule_config', '消息推送规则', 'year_user_c_rule_id', '5', '年卡c用户相关规则');

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('poster_config', '标准海报配置', 'poster_height', '1334', ''),
  ('poster_config', '标准海报配置', 'poster_width', '750', ''),
  ('poster_config', '标准海报配置', 'qr_height', '154', ''),
  ('poster_config', '标准海报配置', 'qr_width', '154', ''),
  ('poster_config', '标准海报配置', 'qr_x', '533', ''),
  ('poster_config', '标准海报配置', 'qr_y', '92', '');

set @parent_id = (select id from privilege where menu_name = '转介绍管理');

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
('消息推送规则', '/op_web/message/rules_list', unix_timestamp(), 'get', 1, '自动推送设置', @parent_id, 'message_rules_list', 1 ),
('消息推送规则详情', '/op_web/message/rule_detail', unix_timestamp(), 'get', 0, '', 0, 'message_rule_detail', 1 ),
('消息推送规则更新状态', '/op_web/message/rule_update_status', unix_timestamp(), 'post', 0, '', 0, 'message_rule_update_status', 1 ),
('消息推送规则更新内容', '/op_web/message/rule_update', unix_timestamp(), 'post', 0, '', 0, 'message_rule_update', 1 ),
('消息手动上次推送内容', '/op_web/message/manual_last_push', unix_timestamp(), 'get', 0, '', 0, 'message_manual_last_push', 1 ),
('消息手动推送', '/op_web/message/manual_push', unix_timestamp(), 'post', 0, '', 0, 'message_manual_push', 1 );

