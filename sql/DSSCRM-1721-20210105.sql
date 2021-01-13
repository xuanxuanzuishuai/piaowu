
USE operation_pre;
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

INSERT INTO operation_pre.message_push_rules SELECT * FROM dss_pre.message_push_rules;

INSERT INTO `message_push_rules` (`id`, `name`, `type`, `target`, `is_active`, `time`, `content`, `remark`, `create_time`, `update_time`)
VALUES
  (11, '体验营开班前2天', 1, 1, 0, "{\"desc\": \"体验营开班前2天\", \"delay_time\": 0}", "[{\"key\": \"content_1\", \"type\": 1, \"value\": \"@{{name}}你好\\n做有方法的家长，让孩子和全球107个国家和地区优秀的孩子一起，从此简单高效练琴！！！\\n\\n⏰小叶子智能陪练5天体验营，X月X日正式开班，开班前一定要鼓励宝贝多多练习，同时邀请小伙伴一起加入训练营还可得到神秘大礼哦\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\n<a href=\\\"https://referral.xiaoyezi.com/operation/activity/christmas?activity_id=6\\\">\\\\ud83d\\\\udd25邀好友得红包，万元豪礼0元领</a>\\n<a href=\\\"https://referral.xiaoyezi.com/operation/activity/christmas?activity_id=6\\\">\\\\ud83d\\\\udd25邀好友得红包，万元豪礼0元领</a>\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"\"}]", '', unix_timestamp(), unix_timestamp()),
  (12, '体验营开班前1天', 1, 1, 0, "{\"desc\": \"体验营开班前1天\", \"delay_time\": 0}", "[{\"key\": \"content_1\", \"type\": 1, \"value\": \"@{{name}}你好\\n❤️小叶子温馨提示：智能陪练5天体验营，明天就要开班啦~\\n\\n小叶子提示您还有一个红包待领取，2小时有效，速戳领取\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\n<a href=\\\"https://referral.xiaoyezi.com/operation/activity/christmas?activity_id=6\\\" >\\\\ud83d\\\\udd25点我马上领取</a>\\n<a href=\\\"https://referral.xiaoyezi.com/operation/activity/christmas?activity_id=6\\\" >\\\\ud83d\\\\udd25点我马上领取</a>\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"\"}]", '', unix_timestamp(), unix_timestamp()),
  (13, '体验营第一天未练琴', 1, 1, 0, "{\"desc\": \"体验营第一天未练琴\", \"delay_time\": 0}", "[{\"key\": \"content_1\", \"type\": 1, \"value\": \"@{{name}}你好\\n有人说学习音乐，练习弹钢琴能使孩子更聪明。是的，音乐是一扇通向创造思维和形象思维的“窗口”，孩子从小接触音乐教育，会使孩子较快的找到打开这扇“窗子”的钥匙~\\n\\n⏰今天是体验营第2天，小叶子来叫宝贝练琴啦~只有每天练琴才能解锁打卡海报哦\\n<a href=\\\"http://referral.xiaoyezi.com/operation/student/clock5Day/home\\\">爱练琴爱分享，训练营5天赚双倍学费，戳我查看活动进度</a>\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"\"}]", '', unix_timestamp(), unix_timestamp()),
  (14, '体验营第二天未练琴', 1, 1, 0, "{\"desc\": \"体验营第二天未练琴\", \"delay_time\": 0}", "[{\"key\": \"content_1\", \"type\": 1, \"value\": \"@{{name}}你好\\n音乐是人类表达情感的一种方式，也是人类的另一种“语言”，它能使孩子学会从另一个窗口来理解世界的美好！\\n\\n⏰今天是体验营练琴第3天，小叶子发现宝贝昨天没有练琴，期待今天能和宝贝一起进步！今天练琴才能解锁明天的打卡海报哦~\\n<a href=\\\"http://referral.xiaoyezi.com/operation/student/clock5Day/home\\\">爱练琴爱分享，训练营5天赚双倍学费，戳我查看活动进度</a>\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"\"}]", '', unix_timestamp(), unix_timestamp()),
  (15, '体验营第三天未练琴', 1, 1, 0, "{\"desc\": \"体验营第三天未练琴\", \"delay_time\": 0}", "[{\"key\": \"content_1\", \"type\": 1, \"value\": \"@{{name}}你好\\n如果你问我，怎样成为好的钢琴家，那么你先告诉我，练了多少音阶——车尔尼\\n希望宝贝可以坚持自己的热爱！\\n\\n⏰今天是体验营练琴第4天，小叶子发现宝贝昨天没有练琴，小叶子期待今天能和宝贝一起进步！今天练琴才能解锁明天的打卡海报哦~\\n<a href=\\\"http://referral.xiaoyezi.com/operation/student/clock5Day/home\\\">爱练琴爱分享，训练营5天赚双倍学费，戳我查看活动进度</a>\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"\"}]", '', unix_timestamp(), unix_timestamp()),
  (16, '体验营第四天未练琴', 1, 1, 0, "{\"desc\": \"体验营第四天未练琴\", \"delay_time\": 0}", "[{\"key\": \"content_1\", \"type\": 1, \"value\": \"@{{name}}你好\\n贝多芬说：“音乐是比一切智慧更高的启示。”希望有小叶子的陪伴，宝贝的练琴路上不孤单！！！\\n\\n⏰今天是体验营练琴第5天，小叶子发现宝贝昨天没有练琴，小叶子期待今天能和宝贝一起进步！今天练琴才能解锁明天的打卡海报哦~\\n<a href=\\\"http://referral.xiaoyezi.com/operation/student/clock5Day/home\\\">爱练琴爱分享，训练营5天赚双倍学费，戳我查看活动进度</a>\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"\"}]", '', unix_timestamp(), unix_timestamp()),
  (17, '体验营结束后一天', 1, 1, 0, "{\"desc\": \"体验营结束后一天\", \"delay_time\": 0}", "[{\"key\": \"content_1\", \"type\": 1, \"value\": \"@{{name}}你好\\n郎朗说：“强逼的孩子是成不了钢琴家的，一定要自己去发现音乐中的喜悦。”希望宝贝坚持下去，也许练琴路上会很辛苦，但是一定会有收获！！！\\n\\n⏰小叶子提示您还有一个红包待领取，2小时有效，速戳领取\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\n\\\\ud83d\\\\udd25\\n<a href=\\\"https://referral.xiaoyezi.com/operation/activity/christmas?activity_id=6\\\">\\\\ud83d\\\\udd25邀好友得红包，万元豪礼0元领</a>\\n<a href=\\\"https://referral.xiaoyezi.com/operation/activity/christmas?activity_id=6\\\">\\\\ud83d\\\\udd25邀好友得红包，万元豪礼0元领</a>\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"\"}]", '', unix_timestamp(), unix_timestamp()),
  (18, '每月活动', 1, 1, 0, "{\"desc\": \"每月活动\", \"delay_time\": 0}", "[{\"key\": \"content_1\", \"type\": 1, \"value\": \"亲爱的用户，您好！您已被小叶子本月专属福利砸中，请您查收！\\\\ud83c\\\\udf81\\n\\n专属福利：邀好友体验，领百元红包\\n<a href=\\\"https://referral.xiaoyezi.com/operation/activity/christmas?activity_id=6\\\">\\\\ud83d\\\\udd25邀好友得红包，万元豪礼0元领</a>\\n<a href=\\\"https://referral.xiaoyezi.com/operation/activity/christmas?activity_id=6\\\">\\\\ud83d\\\\udd25邀好友得红包，万元豪礼0元领</a>\"}, {\"key\": \"content_2\", \"type\": 1, \"value\": \"\"}]", '', unix_timestamp(), unix_timestamp());


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('STUDENT_INVITE', '学生转介绍渠道', 'BUY_NORMAL_STUDENT_INVITE_STUDENT', '2381', '正式包购买后转介绍'),
  -- prod:('STUDENT_INVITE', '学生转介绍渠道', 'BUY_NORMAL_STUDENT_INVITE_STUDENT', '2574', '正式包购买后转介绍'),
  ('message_rule_config', '消息推送规则', 'assign_template_id', '7nD1tVhctOSBIynkb6n9gq_xa0UCO8Y0c8_ub3uhWAY', '手动push消息默认模板id'),
  ('message_rule_config', '消息推送规则', 'how_long_not_invite', '604800', '7天没有过转介绍行为'),
  ('message_rule_config', '消息推送规则', 'how_long_not_result', '2592000', '30天没有转介绍结果'),
  ('message_rule_config', '消息推送规则', 'register_user_c_rule_id', '7', '注册c用户相关规则'),
  ('message_rule_config', '消息推送规则', 'start_class_day_rule_id', '3', '开班日相关规则'),
  ('message_rule_config', '消息推送规则', 'start_class_seven_day_rule_id', '4', '开班日第七天相关规则'),
  ('message_rule_config', '消息推送规则', 'subscribe_rule_id', '1', '关注相关规则'),
  ('message_rule_config', '消息推送规则', 'trail_user_c_rule_id', '6', '体验c用户相关规则'),
  ('message_rule_config', '消息推送规则', 'year_pay_rule_id', '10', '年卡支付成功后消息推送'),
  ('message_rule_config', '消息推送规则', 'year_user_c_rule_id', '5', '年卡c用户相关规则'),
  ('message_rule_config', '消息推送规则', 'before_class_one_day_rule_id', '12', '开班前1天'),
  ('message_rule_config', '消息推送规则', 'before_class_two_day_rule_id', '11', '开班前2天'),
  ('message_rule_config', '消息推送规则', 'after_class_one_day_rule_id', '17', '结班后1天'),
  ('message_rule_config', '消息推送规则', 'monthly_event_rule_id', '18', '每月活动消息'),
  ('message_rule_config', '消息推送规则', 'no_play_day_rule_config', '{"1":13, "2":14, "3":15, "4":16}', '未练琴天数对应规则配置');

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('poster_config', '标准海报配置', 'poster_height', '1334', ''),
  ('poster_config', '标准海报配置', 'poster_width', '750', ''),
  ('poster_config', '标准海报配置', 'qr_height', '154', ''),
  ('poster_config', '标准海报配置', 'qr_width', '154', ''),
  ('poster_config', '标准海报配置', 'qr_x', '533', ''),
  ('poster_config', '标准海报配置', 'qr_y', '92', '');

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('message_push_type', '消息推送形式', '1', '客服消息', NULL),
  ('message_push_type', '消息推送形式', '2', '模板消息', NULL);


set @parent_id = (select id from privilege where menu_name = '转介绍管理');

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
('消息推送规则', '/op_web/message/rules_list', unix_timestamp(), 'get', 1, '自动推送设置', @parent_id, 'message_rules_list', 1 ),
('消息推送规则详情', '/op_web/message/rule_detail', unix_timestamp(), 'get', 0, '', 0, 'message_rule_detail', 1 ),
('消息推送规则更新状态', '/op_web/message/rule_update_status', unix_timestamp(), 'post', 0, '', 0, 'message_rule_update_status', 1 ),
('消息推送规则更新内容', '/op_web/message/rule_update', unix_timestamp(), 'post', 0, '', 0, 'message_rule_update', 1 ),
('消息手动上次推送内容', '/op_web/message/manual_last_push', unix_timestamp(), 'get', 0, '', 0, 'message_manual_last_push', 1 ),
('消息手动推送', '/op_web/message/manual_push', unix_timestamp(), 'post', 0, '', 0, 'message_manual_push', 1 );

