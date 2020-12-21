ALTER TABLE `dict` CHANGE `key_value` `key_value` TEXT NULL  COMMENT '显示值';

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'day_0', '{\"content1\":\"\\\\ud83d\\\\udd14琴童宝贝们注意啦~\\n\\n⏰小叶子智能陪练5天强化训练营，今天就要开始啦~\\n从现在起，小叶子智能陪练将陪伴宝贝度过快乐练琴的每一天~\\n\\n\\\\ud83d\\\\udd25恭喜你获得“分享赚双倍学费”活动资格\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\n\\n训练营5天内，每天练琴，每天打卡，学费双倍返还，最高可得19.8元红包！[红包]\\n[爱心]活动详情【<a href=\\\"http://www.xiaoyezi.om\\\">活动入口链接</a>】\",\"content2\":\"\",\"poster_path\":\"pre\\/referral\\/6346734caf0adae0932c98f6480f043a.jpg\"}', '第0天推送配置'),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'day_1', '{\"content1\":\"\\\\ud83c\\\\udf89恭喜宝贝已完成训练营第一天练琴，好棒哦～小叶子希望每天都能陪你[愉快]\\n\\n\\\\ud83d\\\\udd25“朋友圈打卡双倍返学费”活动已开始\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【文案➕海报】分享到朋友圈，即可领取打卡红包[红包]\\n[爱心]活动详情【<a href=\\\"http://www.xiaoyezi.om\\\">活动入口链接</a>】\",\"content2\": \"为了鼓励宝贝坚持练琴，刚刚报名了“小叶子智能陪练”只要9.9元！\\\\ud83c\\\\udf89\\\\ud83c\\\\udf89\\n5天不限次练琴，有小叶子陪着练琴，宝贝第一天就爱上了~\",\"poster_path\":\"pre\\/referral\\/c47cc2ccfe2032df54a536dc636790c5.jpg\"}', '第1天推送配置'),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'day_2', '{\"content1\":\"\\\\ud83d\\\\udcaa已经坚持练琴2天啦，音准和节奏一定进步了不少，好棒哦！\\n\\n\\\\ud83d\\\\udd25爱练琴爱分享，打卡返学费活动第2⃣️天！\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【文案➕海报】分享至朋友圈，即可领取打卡红包[红包]\",\"content2\":  \"宝贝能爱上钢琴，真是一件非常幸运的事情[哇]～ 宝贝今天继续用“小叶子智能陪练”练琴，智能纠错，及时反馈，宝贝的错音越来越少！[加油！][加油！]\",\"poster_path\":\"pre\\/referral\\/1afa2511031de720d49ae64d5aa5b850.jpg\"}', '第2天推送配置'),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'day_3', '{\"content1\":\"\\\\ud83d\\\\udcaa宝贝真棒！你已经坚持练琴第3天啦[哇] \\n 练琴成绩开始提升了，小叶子希望宝贝坚持下去，成为“小小贝多芬”指日可期！\\\\ud83c\\\\udf89\\\\ud83c\\\\udf89 \\n\\n\\\\ud83d\\\\udd25爱练琴爱分享，打卡返学费活动第3⃣️天！\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【文案➕海报】分享至朋友圈，即可领取打卡红包[红包]\\n[爱心]活动详情【<a href=\\\"http://www.xiaoyezi.om\\\">活动入口链接</a>】\",\"content2\": \"老师说孩子的音准和节奏都比以前有了明显进步[哇]每天都收到宝贝的练琴日报！    \\n孩子哪里弹错，练琴成绩如何都能在里面看到，真是太好了！\\\\ud83c\\\\udf89[加油！]\",\"poster_path\":\"pre\\/referral\\/98fad97a6f7ec345a7d7d8b17d7a4168.jpg\"}', '第3天推送配置'),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'day_4', '{\"content1\":\"\\\\ud83d\\\\udcaa坚持就是胜利，宝贝好棒！第4天就已经可以完整的弹奏一首曲目啦！\\n\\n\\\\ud83d\\\\udd25爱练琴爱分享，打卡返学费活动第4⃣️天！\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【文案➕海报】分享至朋友圈，即可领取打卡红包[红包]\\n[爱心]活动详情【活动入口链接】\",\"content2\":\"孩子通过“小叶子智能陪练”，练了几个小时就能完整的弹奏一首曲子了\\\\ud83c\\\\udfb9  \\n效果这么棒真的让我意想不到，希望宝贝可以坚持下去！✊\\\\ud83c\\\\udf89\",\"poster_path\":\"pre\\/referral\\/ac6e448408cd68093f30604f00878431.jpg\"}', '第4天推送配置'),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'day_5', '{\"content1\":\"[爱心]这是宝贝和小叶子在一起的第5天，宝贝已经超过了全网93％的琴童哦，成为了“小叶子明日之星”\\n\\\\ud83c\\\\udf38未来的练琴路上小叶子希望跟孩子一起每天练琴，快乐成长！[加油]\\n\\n\\\\ud83d\\\\udd25爱练琴爱分享，打卡返学费活动第5⃣️天！\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【文案➕海报】分享至朋友圈，即可领取打卡红包[红包]\\n[爱心]活动详情【活动入口链接】\",\"content2\": \"今天宝贝被授予了“小叶子明日之星”，练琴成绩已超全网93%的小琴童啦[哇][加油！]\\n音准和节奏也已经变得越来越好了，真为宝贝感到骄傲！\\\\ud83c\\\\udf89\\\\ud83c\\\\udf89\",\"poster_path\":\"pre\\/referral\\/afb75253767f2ee2df33e533a9f61f09.jpg\"}', '第5天推送配置'),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'text_position', '{"1":{"duration":{"s":48,"x":143,"y":844},"lesson":{"s":48,"x":357,"y":844},"percent":{"s":48,"x":547,"y":844},"minute":{"x":170,"y":865,"s":16},"qu":{"x":384,"y":865,"s":16}},"2":{"duration":{"s":48,"x":132,"y":844},"lesson":{"s":48,"x":346,"y":844},"percent":{"s":48,"x":539,"y":844},"minute":{"x":184,"y":865,"s":16},"qu":{"x":396,"y":865,"s":16}},"3":{"duration":{"s":48,"x":120,"y":844},"lesson":{"s":48,"x":332,"y":844},"percent":{"s":48,"x":530,"y":844},"minute":{"x":198,"y":865,"s":16},"qu":{"x":409,"y":865,"s":16}}}', '海报中各数据元素位置'),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'poster_config', '{"name_x":186,"name_y":726,"thumb_x":76,"thumb_y":692,"width":750,"height":1334,"qr_w":156,"qr_h":156,"qr_x":534,"qr_y":1110}', ''),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'url', 'https://referral-pre.xiaoyezi.com/market/index', ''),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'collection_event_id', '33', '参加打卡活动班级关联的EVENT ID'),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'max_name_length', '18', '名字最大长度'),
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'day_channel', '{"1":2576,"2":2577,"3":2578,"4":2579,"5":2580}', '每一天对应的小程序码渠道'),
('student_info', '学生默认信息配置', 'default_thumb', 'prod/thumb/uid_95670/thumb_1603699254.jpg', '学生默认头像'),
('share_poster_check_status', '分享截图审核状态', '1', '待审核', NULL),
('share_poster_check_status', '分享截图审核状态', '2', '已通过', NULL),
('share_poster_check_status', '分享截图审核状态', '3', '未通过', NULL),
('share_poster_check_reason', '分享截图审核原因', '1', '分享分组可见', NULL),
('share_poster_check_reason', '分享截图审核原因', '2', '未使用最新海报', NULL),
('share_poster_check_reason', '分享截图审核原因', '3', '上传截图出错', NULL),
('share_poster_check_reason', '分享截图审核原因', '4', '分享无分享语', NULL),
('share_poster_check_reason', '分享截图审核原因', '6', '朋友圈保留时长不足', NULL)
;


-- 审核消息：
INSERT INTO `wechat_config`(`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`) VALUES (1, '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"\\u60a8\\u4e0a\\u4f20\\u7684\\u622a\\u56fe\\u5ba1\\u6838\\u5df2\\u7ed3\\u675f\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u4f53\\u9a8c\\u8425\\u5206\\u4eab\\u8fd4\\u5b66\\u8d39\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"DAY {{day}}\\u622a\\u56fe\\u5ba1\\u6838\\u7ed3\\u679c\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"{{status}}\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"{{remark}}\",\"color\":\"#FF8A00\"}}}', 'custom', 3, 'custom', '', unix_timestamp(), 0, 0, 0);
select @last_id := last_insert_id();

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES 
('CHECKIN_PUSH_CONFIG', '打卡签到设置', 'verify_message_config_id', @last_id, '审核消息配置ID');


SET @task_id = (SELECT id FROM dss_dev.erp_event_task WHERE `type`=13 AND `name`='打卡1天' LIMIT 1); 
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('operation_common_node', '通用红包搜索节点', '20', '分享返学费打卡1天 - 3元', '');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('node_relate_task', '节点对应的task', '20', @task_id, '');
UPDATE `dict` SET `key_value` = concat(key_value, ',', 20) WHERE `key_code` = 'not_verify_refund' AND `type` = 'node_setting';
UPDATE `dict` SET `key_value` = concat(key_value, ',', 20) WHERE `key_code` = 'not_display_wait' AND `type` = 'node_setting';
-- 三个红包消息：
-- day 1:
INSERT INTO `wechat_config`(`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`, `event_task_id`, `to`) VALUES (1, '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"\\u5956\\u52b1\\u7ea2\\u5305\\u5df2\\u7ecf\\u53d1\\u653e\\uff0c\\u8bf7\\u53ca\\u65f6\\u9886\\u53d6\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u4f53\\u9a8c\\u8425\\u5206\\u4eab\\u8fd4\\u5b66\\u8d39\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u7d2f\\u8ba1\\u5b8c\\u6210{{day}}\\u65e5\\u6253\\u5361\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u5df2\\u5b8c\\u6210\\uff0c\\u5956\\u52b1{{awardValue}}\\u5143\\u5df2\\u53d1\\u653e\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"{{remark}}\",\"color\":\"#FF8A00\"}}}', 'custom', 3, 'custom', '', unix_timestamp(), 0, 0, 0, @task_id, 2);

SET @task_id = (SELECT id FROM dss_dev.erp_event_task WHERE `type`=13 AND `name`='打卡3天' LIMIT 1); 
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('operation_common_node', '通用红包搜索节点', '21', '分享返学费打卡3天 - 6.9元', '');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('node_relate_task', '节点对应的task', '21', @task_id, '');
UPDATE `dict` SET `key_value` = concat(key_value, ',', 21) WHERE `key_code` = 'not_verify_refund' AND `type` = 'node_setting';
UPDATE `dict` SET `key_value` = concat(key_value, ',', 21) WHERE `key_code` = 'not_display_wait' AND `type` = 'node_setting';
-- 三个红包消息：
-- day 3:
INSERT INTO `wechat_config`(`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`, `event_task_id`, `to`) VALUES (1, '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"\\u5956\\u52b1\\u7ea2\\u5305\\u5df2\\u7ecf\\u53d1\\u653e\\uff0c\\u8bf7\\u53ca\\u65f6\\u9886\\u53d6\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u4f53\\u9a8c\\u8425\\u5206\\u4eab\\u8fd4\\u5b66\\u8d39\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u7d2f\\u8ba1\\u5b8c\\u6210{{day}}\\u65e5\\u6253\\u5361\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u5df2\\u5b8c\\u6210\\uff0c\\u5956\\u52b1{{awardValue}}\\u5143\\u5df2\\u53d1\\u653e\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"{{remark}}\",\"color\":\"#FF8A00\"}}}', 'custom', 3, 'custom', '', unix_timestamp(), 0, 0, 0, @task_id, 2);

SET @task_id = (SELECT id FROM dss_dev.erp_event_task WHERE `type`=13 AND `name`='打卡5天' LIMIT 1); 
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('operation_common_node', '通用红包搜索节点', '22', '分享返学费打卡5天 - 19.8元', '');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('node_relate_task', '节点对应的task', '22', @task_id, '');
UPDATE `dict` SET `key_value` = concat(key_value, ',', 22) WHERE `key_code` = 'not_verify_refund' AND `type` = 'node_setting';
UPDATE `dict` SET `key_value` = concat(key_value, ',', 22) WHERE `key_code` = 'not_display_wait' AND `type` = 'node_setting';

-- 三个红包消息：
-- day 5:
INSERT INTO `wechat_config`(`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`, `event_task_id`, `to`) VALUES (1, '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"\\u5956\\u52b1\\u7ea2\\u5305\\u5df2\\u7ecf\\u53d1\\u653e\\uff0c\\u8bf7\\u53ca\\u65f6\\u9886\\u53d6\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u4f53\\u9a8c\\u8425\\u5206\\u4eab\\u8fd4\\u5b66\\u8d39\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u7d2f\\u8ba1\\u5b8c\\u6210{{day}}\\u65e5\\u6253\\u5361\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u5df2\\u5b8c\\u6210\\uff0c\\u5956\\u52b1{{awardValue}}\\u5143\\u5df2\\u53d1\\u653e\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"{{remark}}\",\"color\":\"#FF8A00\"}}}', 'custom', 3, 'custom', '', unix_timestamp(), 0, 0, 0, $task_id, 2);


SET @pid = ( SELECT id FROM privilege WHERE NAME = "转介绍管理" LIMIT 1);
INSERT INTO `privilege` ( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ('打卡截图审核', '/op_web/checkin_poster/list', unix_timestamp(), 'get', 1, '打卡截图审核', @pid, 'checkin_poster_list', 1),
  ('打卡截图审核通过', '/op_web/checkin_poster/approved', unix_timestamp(), 'post', 0, '打卡截图审核通过', 0, 'checkin_poster_approved', 1),
  ('打卡截图审核拒绝', '/op_web/checkin_poster/refused', unix_timestamp(), 'post', 0, '打卡截图审核拒绝', 0, 'checkin_poster_refused', 1),
  ('红包审核', '/op_web/referee/award_list', unix_timestamp(), 'get', 1, '红包截图', @pid, 'referee_award_list', 1),
  ('红包发放', '/op_web/referee/award_verify', unix_timestamp(), 'get', 0, '红包发放', 0, 'referee_award_verify', 1),
  ('红包列表选项', '/op_web/referee/config', unix_timestamp(), 'get', 0, '红包列表选项', 0, 'referee_award_config', 1);

