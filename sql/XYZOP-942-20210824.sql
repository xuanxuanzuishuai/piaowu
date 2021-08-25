ALTER TABLE `message_push_rules`
ADD COLUMN `app_id` tinyint(1) UNSIGNED NOT NULL DEFAULT 8 COMMENT '应用id' AFTER `id`;



-- 权限设置
update privilege set  `menu_name` = '智能转介绍管理' where `unique_en_name` = 'operations_management';

set @parentMenuId = (select id from privilege where unique_en_name = 'life_operations_management');

INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('真人转介绍管理', '', unix_timestamp(), 'get', 1, '真人转介绍管理', 0, 'life_operations_management', 1);

INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('真人消息推送规则', '/op_web/message_life/rules_list', unix_timestamp(), 'get', 1, '自动推送设置', @parentMenuId, 'message_life_rules_list', 1),
 ('消息推送规则详情', '/op_web/message_life/rule_detail', unix_timestamp(), 'get', 0, '', 0, 'message_life_rule_detail', 1),
 ('消息推送规则更新状态', '/op_web/message_life/rule_update_status', unix_timestamp(), 'post', 0, '', 0, 'message_life_rule_update_status', 1),
 ('消息推送规则更新内容', '/op_web/message_life/rule_update', unix_timestamp(), 'post', 0, '', 0, 'message_life_rule_update', 1);



INSERT INTO `message_push_rules` (`id`,`app_id`,`name`, `type`, `target`, `is_active`, `time`, `content`, `remark`, `create_time`, `update_time`)
VALUES
 (30, 1,'首次关注', 1, 1, 1, '{\"desc\": \"首次关注\", \"delay_time\": 0}', '[{"key": "content_1", "type": 1, "value": "亲爱的家长，终于等到你！ 小叶子陪练，由国际钢琴大师郎朗推荐。好老师，敢示范，练琴效果看得见。\\ud83c\\udfb9初次见面，送你一节「限时0元」1对1陪练课，让好老师带孩子高效练琴！ <a href=\"http://www.xiongmaopeilian.com/?from=107\">>>> 戳此免费领取体验课</a> 小叶子音乐教育成立于2013年，获得创新工场，真格基金，红杉资本等机构C轮投资。深耕行业6年，获得国际大奖无数。 【师资】八轮高标准，严选好老师 【上课】老师能示范，真正高效练 【课后】课后有点评，练琴有重点 \\ud83d\\udc47点击下方菜单栏「0元领课」，让孩子高效练琴！ \n"}]', '', unix_timestamp(), unix_timestamp()),
 (31, 1,'推荐好友', 1, 1, 1, '{\"desc\": \"点击推荐好友时\", \"delay_time\": 0}', '[{"key": "content_1", "type": 1, "value": "孩子最近总给我秀琴技\\ud83d\\ude04有客人也要展现一番。小家伙是真努力，进步也快，肯定是等开学给老师和其他小朋友展示\\ud83d\\ude2c\n课程推荐给你，扫码就能0元领取一节50分钟的真人陪练课，你也试试吧～"}, {"1": "已下线", "2": "已上线", "key": "image", "QR_X": "535", "QR_Y": "72", "type": 2, "value": "pre/img//auto_push/90bad00886c5c30d64c2b4a3f306e5c8.png", "QR_WIDTH": "154", "QR_HEIGHT": "154", "poster_id": "577", "POSTER_WIDTH": "750", "POSTER_HEIGHT": "1334"}]', '', unix_timestamp(), unix_timestamp()),
 (32, 8,'推荐好友-年卡已付费', 1, 1, 1, '{\"desc\": \"点击推荐好友时\", \"delay_time\": 0}', '[{"key": "content_1", "type": 1, "value": "\\ud83c\\udf08转发以下【文案+海报】分享至朋友圈，邀请好友一起体验小叶子智能陪练！ \\ud83c\\udf81*8月【年卡会员】参与活动有多重豪礼相送*： \\ud83c\\udf1f<a href=\"https://referral.xiaoyezi.com/operation/activity/awards/index?awtype=month\">转发下方邀请语加海报，点此上传，即可领奖</a> \\ud83c\\udf1f邀请好友扫码体验，好友练琴，就送您1000金叶子； \\ud83c\\udf1f每位好友扫码付费年卡，还会额外送您20000金叶子 *金叶子可兑换\\ud83d\\udc49APP使用时长 / 实物好礼 *奖励仅限8月有效*\t"}, {"key": "content_2", "type": 1, "value": "孩子最近总给我秀琴技\\ud83d\\ude04有客人也要展现一番。小家伙是真努力，进步也快，肯定是等开学给老师和其他小朋友展示\\ud83d\\ude2c 把我们的练琴神器送大家体验下，扫码0元领\t"},{"1": "已下线", "2": "已上线", "key": "image", "QR_X": "535", "QR_Y": "72", "type": 2, "value": "prod/referral//referral_poster/2bf78032f37f471faac1375b456c6537.png", "QR_WIDTH": "154", "QR_HEIGHT": "154", "poster_id": "576", "POSTER_WIDTH": "750", "POSTER_HEIGHT": "1334"}]', '', 1630307633, 1630307986)
 (33, 8,'推荐好友-非付费', 1, 1, 1, '{\"desc\": \"点击推荐好友时\", \"delay_time\": 0}', '[{"key": "content_1", "type": 1, "value": "\\ud83c\\udf39 小叶子感谢您的信任！ \\ud83c\\udf81 小叶子精心为您准备了各种福利，动动您的手指即可轻松领取~ ①复制以下分享语+海报发送至朋友圈 ②好友扫码购课您还能兑换更多超值好礼！ ③好友也有机会领取超值礼品哦~ 6月限时活动，多邀多得，上不封顶！ "}, {"key": "content_2", "type": 1, "value": "终于不用为孩子练琴费心了！试了下小叶子智能陪练，孩子喜欢，练琴变主动了\\ud83d\\ude04。一段时间下来，进步真是明显！我怎么没早发现这种练琴神器，你们也快扫码体验下！ "}, {"1": "已下线", "2": "已上线", "key": "image", "QR_X": "535", "QR_Y": "72", "type": 2, "value": "prod/img//activity/2f111166daee22e853d981cc79f665e5.png", "QR_WIDTH": "154", "QR_HEIGHT": "154", "poster_id": "576", "POSTER_WIDTH": "750", "POSTER_HEIGHT": "1334"}]', '', unix_timestamp(), unix_timestamp());

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('message_rule_config', '消息推送规则', 'life_subscribe_rule_id', '30', '真人首次关注'),
  ('message_rule_config', '消息推送规则', 'invite_friend_rule_id', '31', '真人推荐好友'),
  ('message_rule_config', '消息推送规则', 'invite_friend_pay_rule_id', '32', '智能推荐好友-付费'),
  ('message_rule_config', '消息推送规则', 'invite_friend_not_pay_rule_id', '33', '智能推荐好友-非付费');
