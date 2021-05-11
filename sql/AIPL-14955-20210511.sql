-- erp ------------------------
-- 增加奖励事件字段
alter table erp_user_event_task_award_gold_leaf add column `invite_detail_id` int(10) unsigned not null default 0 comment '转介绍关系详情表主键' after `package_type`,
add column `award_node` varchar(70) not null default '' comment '奖励节点' after `invite_detail_id`;

-- 增加dict
INSERT INTO `erp_dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('student_account_log_op_type', '学生账户操作日志子类型', '5008', '累计邀请购买年卡', '购买年卡邀请到一定人数的奖励');

-- 更新体验卡奖励
UPDATE `erp_event_task` SET `desc` = 'AIPL-14955-5.12体验卡校验是否练琴', `award` = '{\"awards\":[{\"to\":1,\"amount\":1000,\"type\":3,\"delay\":1036800,\"need_check\":0,\"account_sub_type\":\"3002\",\"award_node\":\"buy_trial_card\"}]}' WHERE `id` = 481;
-- 增加年卡会员推荐好友购买年卡累计奖励
INSERT INTO `erp_event_task`(`id`, `type`, `condition`, `event_id`, `name`, `desc`, `create_time`, `creator_id`, `update_time`, `updator_id`, `award`, `start_time`, `end_time`, `status`)
 VALUES (522, 1, 'c', 1, '付费年卡累计邀请', 'AIPL-14954-5.12', 1616756138, 751, 1619082667, 10027, '{\"awards\":[{\"to\":1,\"amount\":40000,\"type\":3,\"delay\":0,\"need_check\":0,\"account_sub_type\":\"3002\",\"award_node\":\"cumulative_invite_buy_year_card\"}]}', 1619082667, 1798646400, 1);


-- op ------------------------
-- 增加年卡累计奖励任务id
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('REFERRAL_CONFIG', '转介绍配置', 'cumulative_invite_buy_year_card', '522', '转介绍累计邀请好友奖励');



