ALTER TABLE `message_push_rules`
ADD COLUMN `app_id` tinyint(1) UNSIGNED NOT NULL DEFAULT 8 COMMENT '应用id' AFTER `id`;



-- 权限设置
update privilege set  `menu_name` = '智能转介绍管理' where `unique_en_name` = 'operations_management';

set @parentMenuId = (select id from privilege where unique_en_name = 'life_operations_management');

INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('真人转介绍管理', '', unix_timestamp(), 'get', 1, '真人转介绍管理', 0, 'life_operations_management', 1);

INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('真人消息推送规则', '/op_web/message_life/rules_list', unix_timestamp(), 'get', 0, '自动推送设置', @parentMenuId, 'message_life_rules_list', 1),
 ('消息推送规则详情', '/op_web/message_life/rule_detail', unix_timestamp(), 'get', 0, '', 0, 'message_life_rule_detail', 1),
 ('消息推送规则更新状态', '/op_web/message_life/rule_update_status', unix_timestamp(), 'post', 0, '', 0, 'message_life_rule_update_status', 1),
 ('消息推送规则更新内容', '/op_web/message_life/rule_update', unix_timestamp(), 'post', 1, '商家代理', 0, 'message_life_rule_update', 1);



INSERT INTO `message_push_rules` (`id`,`name`, `type`, `target`, `is_active`, `time`, `content`, `remark`, `create_time`, `update_time`)
VALUES
 (30,'首次关注', 1, 1, 1, '{\"desc\": \"首次关注\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"\\\\ud83d\\\\ude18小叶子家长您好呀~ \\n \\n❤️温馨提示您本期上传截图领红包活动已经开始啦！ \\n\\\\ud83d\\\\udd34您还有一个红包未领取哦-  \\n \\n<a href=\\\"https://dss-weixin.xiongmaopeilian.com/student/referral?tag=1\\\">>>戳我上传截图，领红包\\\\ud83e\\\\udde7</a>\\n\\n\\\\ud83d\\\\udd25首次参与返现30元 \\n\\\\ud83d\\\\udd25连续不间断参与8期，可得小叶子书包1个 \\n\\\\ud83d\\\\udd25好友扫码您的海报体验，你还可以获得金叶子兑换现金礼品\\n\\n\\\\ud83d\\\\udcac还没有参与活动或未领取红包，速咨询课管老师活动详情\"}]', '', unix_timestamp(), unix_timestamp()),
 (31,'推荐好友', 1, 1, 1, '{\"desc\": \"点击推荐好友时\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"\\\\ud83d\\\\ude18小叶子家长您好呀~ \\n \\n❤️温馨提示您本期上传截图领红包活动已经开始啦！ \\n\\\\ud83d\\\\udd34您还有一个红包未领取哦-  \\n \\n<a href=\\\"https://dss-weixin.xiongmaopeilian.com/student/referral?tag=1\\\">>>戳我上传截图，领红包\\\\ud83e\\\\udde7</a>\\n\\n\\\\ud83d\\\\udd25首次参与返现30元 \\n\\\\ud83d\\\\udd25连续不间断参与8期，可得小叶子书包1个 \\n\\\\ud83d\\\\udd25好友扫码您的海报体验，你还可以获得金叶子兑换现金礼品\\n\\n\\\\ud83d\\\\udcac还没有参与活动或未领取红包，速咨询课管老师活动详情\"}]', '', unix_timestamp(), unix_timestamp()),
 (32,'推荐好友-默认菜单', 1, 8, 1, '{\"desc\": \"点击推荐好友时\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"\\\\ud83d\\\\ude18小叶子家长您好呀~ \\n \\n❤️温馨提示您本期上传截图领红包活动已经开始啦！ \\n\\\\ud83d\\\\udd34您还有一个红包未领取哦-  \\n \\n<a href=\\\"https://dss-weixin.xiongmaopeilian.com/student/referral?tag=1\\\">>>戳我上传截图，领红包\\\\ud83e\\\\udde7</a>\\n\\n\\\\ud83d\\\\udd25首次参与返现30元 \\n\\\\ud83d\\\\udd25连续不间断参与8期，可得小叶子书包1个 \\n\\\\ud83d\\\\udd25好友扫码您的海报体验，你还可以获得金叶子兑换现金礼品\\n\\n\\\\ud83d\\\\udcac还没有参与活动或未领取红包，速咨询课管老师活动详情\"}]', '', unix_timestamp(), unix_timestamp()),
 (33,'推荐好友-个性化菜单', 1, 8, 1, '{\"desc\": \"点击推荐好友时\", \"delay_time\": 0}', '[{\"key\": \"content_1\", \"type\": 1, \"value\": \"\\\\ud83d\\\\ude18小叶子家长您好呀~ \\n \\n❤️温馨提示您本期上传截图领红包活动已经开始啦！ \\n\\\\ud83d\\\\udd34您还有一个红包未领取哦-  \\n \\n<a href=\\\"https://dss-weixin.xiongmaopeilian.com/student/referral?tag=1\\\">>>戳我上传截图，领红包\\\\ud83e\\\\udde7</a>\\n\\n\\\\ud83d\\\\udd25首次参与返现30元 \\n\\\\ud83d\\\\udd25连续不间断参与8期，可得小叶子书包1个 \\n\\\\ud83d\\\\udd25好友扫码您的海报体验，你还可以获得金叶子兑换现金礼品\\n\\n\\\\ud83d\\\\udcac还没有参与活动或未领取红包，速咨询课管老师活动详情\"}]', '', unix_timestamp(), unix_timestamp());

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('message_rule_config', '消息推送规则', 'life_subscribe_rule_id', '30', '真人首次关注'),
  ('message_rule_config', '消息推送规则', 'invite_friend_rule_id', '31', '真人推荐好友'),
  ('message_rule_config', '消息推送规则', 'invite_friend_pay_rule_id', '32', '智能推荐好友-付费'),
  ('message_rule_config', '消息推送规则', 'invite_friend_not_pay_rule_id', '33', '智能推荐好友-非付费');
