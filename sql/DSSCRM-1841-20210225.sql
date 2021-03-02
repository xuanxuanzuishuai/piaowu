-- EPR：!!!
-- EPR：!!!
-- EPR：!!!
set @c = 5516; -- pre
-- set @c = 12324; -- prod

set @eventId = (select id from erp_event where `type` = 5);

INSERT INTO `erp_event_task` (`type`, `condition`, `event_id`, `name`, `desc`, `create_time`, `creator_id`, `update_time`, `updator_id`, `award`, `start_time`, `end_time`, `status`)
VALUES
	(1, 'c', 1, '付费体验卡', 'DSSCRM-1841新规则:2.1.1', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":100,\"type\":1,\"delay\":0,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1),
	(1, 'c', 1, '付费年卡', 'DSSCRM-1841新规则:2.1.2', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":16800,\"type\":1,\"delay\": 1296000,\"need_check\":1},{\"to\":2,\"amount\":5000,\"type\":1,\"delay\":1296000,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1),
	(1, '{\"count\":1}', 1, '付费年卡', 'DSSCRM-1841新规则2.2.2.1', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":20000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":1,\"amount\":10000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":1,\"amount\":15,\"type\":2,\"course_id\":@c,\"need_check\":0},{\"to\":2,\"amount\":10000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":2,\"amount\":15,\"type\":2,\"course_id\":@c,\"need_check\":0}]}', unix_timestamp(), 1672416000, 1),
	(1, '{\"count\":2}', 1, '付费年卡', 'DSSCRM-1841新规则2.2.2.2', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":20000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":1,\"amount\":10000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":2,\"amount\":10000,\"type\":1,\"delay\":1296000,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1),
	(1, '{\"count\":5}', 1, '付费年卡', 'DSSCRM-1841新规则2.2.2.3', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":20000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":1,\"amount\":20000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":1,\"amount\":20000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":2,\"amount\":10000,\"type\":1,\"delay\":1296000,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1),
	(1, '{\"count\":6}', 1, '付费年卡', 'DSSCRM-1841新规则2.2.2.4', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":20000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":1,\"amount\":20000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":1,\"amount\":20000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":1,\"amount\":20000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":2,\"amount\":10000,\"type\":1,\"delay\":1296000,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1),
	(6, '{\"per_day_min_play_time\":600,\"total_qualified_day\":4,\"valid_time_range_day\":5}', @eventId, '5天练琴单日练琴10分钟4天返现19.8元', '单日练琴时长不少于10分钟的天数不少于4天，返现奖励19.8元', unix_timestamp(), 10001, 0, 0, '{\"awards\":[{\"to\":2,\"amount\":1980,\"type\":1,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1);



-- OP: !!!
-- OP: !!!
-- OP: !!!
-- const EXPECT_TRAIL_PAY    = 2; //付费体验卡
-- const EXPECT_YEAR_PAY     = 3; //付费年卡
-- const EXPECT_FIRST_NORMAL = 4; //首购智能正式课
-- 2.1.1：
set @trail_task_id = 325;
-- 2.1.2：
set @year_task_id = '326,327,328,329,330';

UPDATE `dict` SET `key_value` = concat(@trail_task_id, ',', key_value) WHERE `key_code` = '2' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '3' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '4' AND `type` = 'node_relate_task';

-- 1：2.2.2.1;
-- 2：2.2.2.2;
-- 3：2.2.2.3;
-- 4：2.2.2.3;
-- 5：2.2.2.3;
-- 6：2.2.2.4;
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('REFERRAL_CONFIG', '转介绍配置', 'task_stop_change_number', 6, '超过多少人后奖励不再变化'),
('REFERRAL_CONFIG', '转介绍配置', 'normal_task_config', '{\"1\":327, \"2\":328, \"3\":329, \"4\":329, \"5\":329, \"6\":330}', '年卡推荐人数对应任务');
-- 年卡推荐人数计算起始点：
UPDATE `dict` set key_value = '1614528000' where type = 'REFERRAL_CONFIG' and key_code = 'new_rule_start_time';


-- 修改对应task的wechat_config消息task_id
INSERT INTO `wechat_config` (`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`, `event_task_id`, `to`)
VALUES
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：邀请好友报名智能陪练体验营\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, @trail_task_id, 1),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：好友付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 326, 1),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：首次购买智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取', '3', 1, 'award', '', 1609214128, 0, 0, 0, 326, 2),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：好友付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 327, 1),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：首次购买智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取', '3', 1, 'award', '', 1609214128, 0, 0, 0, 327, 2),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：好友付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 328, 1),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：首次购买智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取', '3', 1, 'award', '', 1609214128, 0, 0, 0, 328, 2),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：好友付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 329, 1),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：首次购买智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取', '3', 1, 'award', '', 1609214128, 0, 0, 0, 329, 2),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：好友付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 330, 1),
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：首次购买智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取', '3', 1, 'award', '', 1609214128, 0, 0, 0, 330, 2);

INSERT INTO `poster` (`name`, `path`, `status`, `create_time`)
  VALUES
  ('智能体验营小程序已购买转介绍海报底图', 'pre/referral/bfe715c9311d0b0fd342142914ac8c56.jpg', 1, unix_timestamp());


-- DSS:!!!
-- DSS:!!!
-- DSS:!!!

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('REFERRAL_CONFIG', '转介绍配置', 'return_cash_channel', '1396', '返现活动渠道');

set @trail_task_id = 325;
set @year_task_id = '326,327,328,329,330';

UPDATE `dict` SET `key_value` = concat(@trail_task_id, ',', key_value) WHERE `key_code` = '2' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '3' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '4' AND `type` = 'node_relate_task';


-- select @last_id := 'op插入新poster ID'
UPDATE `dict` SET `key_value` = 35 WHERE `key_code` = 'buy_trail_re_mini_sis_p_id' AND `type` = 'REFERRAL_CONFIG';
