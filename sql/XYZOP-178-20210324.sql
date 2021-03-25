-- EPR：!!!
-- EPR：!!!
-- EPR：!!!
set @c = 5516; -- pre
-- set @c = 12324; -- prod

set @eventId = (select id from erp_event where `type` = 5);

INSERT INTO `erp_event_task` (`type`, `condition`, `event_id`, `name`, `desc`, `create_time`, `creator_id`, `update_time`, `updator_id`, `award`, `start_time`, `end_time`, `status`)
VALUES
    (1, 'c', 1, '付费体验卡', 'XYZOP-178-1.1', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":100,\"type\":1,\"delay\":0,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1),
	(1, 'c', 1, '付费年卡', 'XYZOP-178-1.2', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":10000,\"type\":1,\"delay\": 1296000,\"need_check\":1},{\"to\":2,\"amount\":5000,\"type\":1,\"delay\":1296000,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1),

	(1, '{\"count\":[1,2,4,6]}', 1, '付费体验卡', 'XYZOP-178-2.1.1&2.1.2&2.1.4&2.1.6', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":5,\"type\":2,\"course_id\":@c,\"need_check\":1,\"delay\":10}]}', unix_timestamp(), 1672416000, 1),
	(1, '{\"count\":3}', 1, '付费体验卡', 'XYZOP-178-2.1.3', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":2000,\"type\":1,\"delay\":0,\"need_check\":1},{\"to\":1,\"amount\":5,\"type\":2,\"course_id\":@c,\"need_check\":1,\"delay\":10}]}', unix_timestamp(), 1672416000, 1),
	(1, '{\"count\":5}', 1, '付费体验卡', 'XYZOP-178-2.1.5', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":3000,\"type\":1,\"delay\":0,\"need_check\":1},{\"to\":1,\"amount\":5,\"type\":2,\"course_id\":@c,\"need_check\":1,\"delay\":10}]}', unix_timestamp(), 1672416000, 1),
	(1, 'c', 1, '付费年卡', 'XYZOP-178-2.2.1', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":10000,\"type\":1,\"delay\":1296000,\"need_check\":1},{\"to\":1,\"amount\":30,\"type\":2,\"delay\":1296000,\"need_check\":1},{\"to\":2,\"amount\":5000,\"type\":1,\"delay\":1296000,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1),
	(1, 'c', 1, '付费年卡', 'XYZOP-178-2.2.2', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":10000,\"type\":1,\"delay\":1296000,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1)
	;



-- OP: !!!
-- OP: !!!
-- OP: !!!
-- const EXPECT_TRAIL_PAY    = 2; //付费体验卡
-- const EXPECT_YEAR_PAY     = 3; //付费年卡
-- const EXPECT_FIRST_NORMAL = 4; //首购智能正式课
-- XYZOP-178-1.1
set @trail_task_id = 333;

-- 第一个：XYZOP-178-1.2
-- 第二个：XYZOP-178-2.2.1
set @year_task_id = '334,338';

UPDATE `dict` SET `key_value` = concat(@trail_task_id, ',', key_value) WHERE `key_code` = '2' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '3' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '4' AND `type` = 'node_relate_task';

-- redis:
-- xyzop_178_start_point
-- trial_task_stop_change_number_xyzop_178
-- trial_task_config_xyzop_178
-- extra_task_id_normal_xyzop_178 => XYZOP-178-2.2.2

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('REFERRAL_CONFIG', '转介绍配置', 'xyzop_178_start_point', '1617206400', '转介绍新规则起始时间'),
('REFERRAL_CONFIG', '转介绍配置', 'trial_task_stop_change_number_xyzop_178', 6, '超过多少人后奖励不再变化'),
('REFERRAL_CONFIG', '转介绍配置', 'trial_task_config_xyzop_178', '{\"1\":335, \"2\":335, \"3\":336, \"4\":335, \"5\":337, \"6\":335}', '体验卡人数对应任务ID'),
('REFERRAL_CONFIG', '转介绍配置', 'extra_task_id_normal_xyzop_178', '339', '年卡额外奖励');


-- 修改对应task的wechat_config消息task_id
INSERT INTO `wechat_config` (`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`, `event_task_id`, `to`)
VALUES
    -- XYZOP-178-1.1
	(1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：邀请好友报名智能陪练体验营\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, @trail_task_id, 1),
    -- 	XYZOP-178-1.2
    (1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：好友付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 334, 1),
    (1, '奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：首次购买智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取', '3', 1, 'award', '', 1609214128, 0, 0, 0, 334, 2),

    -- 	XYZOP-178-2.1.1&2.1.2&2.1.4&2.1.6 : none
	(1, '奖励{{awardType}}已经发放，请及时查看，详情如下：\n任务名称：转介绍奖励\n任务内容：邀请好友报名智能陪练体验营\n完成情况：已完成，奖励{{duration}}已发放{{remark}}\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 335, 1),
    --  XYZOP-178-2.1.3
	(1, '奖励{{awardType}}已经发放，请及时查看，详情如下：\n任务名称：转介绍奖励\n任务内容：邀请好友报名智能陪练体验营\n完成情况：已完成，奖励{{duration}}已发放{{remark}}\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 336, 1),
    --  XYZOP-178-2.1.5
	(1, '奖励{{awardType}}已经发放，请及时查看，详情如下：\n任务名称：转介绍奖励\n任务内容：邀请好友报名智能陪练体验营\n完成情况：已完成，奖励{{duration}}已发放{{remark}}\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 337, 1),
    -- 	XYZOP-178-2.2.1
	(1, '奖励{{awardType}}已经发放，请及时查看，详情如下：\n任务名称：转介绍奖励\n任务内容：邀请好友报名智能陪练体验营\n完成情况：已完成，奖励{{duration}}已发放{{remark}}\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 338, 1),
    -- 	XYZOP-178-2.2.2
	(1, '额外奖励红包已经发放，请及时领取，详情如下：\n任务名称：转介绍奖励\n任务内容：邀请好友报名智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}元已发放，请点击红包领取\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1609214128, 0, 0, 0, 339, 1);

-- DSS:!!!
-- DSS:!!!
-- DSS:!!!
-- XYZOP-178-1.1
set @trail_task_id = 333;

-- 第一个：XYZOP-178-1.2
-- 第二个：XYZOP-178-2.2.1
set @year_task_id = '334,338';

UPDATE `dict` SET `key_value` = concat(@trail_task_id, ',', key_value) WHERE `key_code` = '2' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '3' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '4' AND `type` = 'node_relate_task';

