-------------------- erp sql必须优先，确保指定的id没有被占用，之后op和dss才可以更新--------------------

-- 确认  event_id 是否正确
-- select * from erp_event;  pre event_id=4, prod event_id=2
INSERT INTO `erp_event_task`(`id`,`type`, `condition`, `event_id`, `name`, `desc`, `create_time`, `creator_id`, `update_time`, `updator_id`, `award`, `start_time`, `end_time`, `status`) VALUES
(476, 5, '{"can_repeat_attend":1}', 4, '上传截图领奖', '海报分享', 1588228856, 591, 1612087387, 591, '{"awards":[{"to":2,"amount":3000,"type":3,"need_check":0,"account_sub_type":"3002"}]}', 1611936000, 1893427200, 1),
(477, 5, '{"can_repeat_attend":1}', 4, '上传截图领奖', '海报分享', 1588228856, 591, 1613806910, 10038, '{"awards":[{"to":2,"amount":2000,"type":3,"need_check":0,"account_sub_type":"3002"}]}', 1612281600, 1740585599, 1),
(478, 5, '{"can_repeat_attend":1}', 4, '上传截图领奖', '海报分享', 1588228856, 591, 1612343404, 591, '{"awards":[{"to":2,"amount":1000,"type":3,"need_check":0,"account_sub_type":"3002"}]}', 1611936000, 1893427200, 1),
(481, 1, 'c', 1, '付费体验卡', 'AIPL-13989-4.22', 1616756138, 751, 1619082667, 10027, '{"awards":[{"to":1,"amount":1000,"type":3,"delay":15,"need_check":0,"account_sub_type":"3002"}]}', 1619082667, 1672416000, 1),
(482, 1, '{"count\":2}', 1, '付费年卡', 'AIPL-13989-4.22', 1619082667, 751, 1619082667, 10027, '{"awards":[{"to":1,"amount":20000,"type":3,"delay":15,"need_check":1,"account_sub_type":"3002"},{"to":2,"amount":5000,"type":3,"delay":0,"need_check":1,"account_sub_type":"3002"}]}', 1619082667, 1672416000, 1),
(483, 1, '{"count":5}', 1, '付费年卡', 'AIPL-13989-4.22', 1619082667, 751, 1619082667, 10027, '{"awards":[{"to":1,"amount":20000,"type":3,"delay":15,"need_check":1,"account_sub_type":"3002"},{"to":2,"amount":5000,"type":3,"delay":0,"need_check":1,"account_sub_type":"3002"}]}', 1619082667, 1672416000, 1),
(484, 1, '{"count":6}', 1, '付费年卡', 'AIPL-13989-4.22', 1619082667, 751, 1619082667, 10027, '{"awards":[{"to":1,"amount":20000,"type":3,"delay":15,"need_check":1,"account_sub_type":"3002"},{"to":2,"amount":5000,"type":3,"delay":0,"need_check":1,"account_sub_type":"3002"}]}', 1619082667, 1672416000, 1),
(485, 1, '{"can_repeat_attend":0}', 1, '付费年卡', 'AIPL-13989-4.22', 1619082667, 751, 1619082667, 10027, '{"awards":[{"to":1,"amount":10000,"type":3,"delay":15,"need_check":1,"account_sub_type":"3002"}]}', 1619082667, 1672416000, 1);

CREATE TABLE `erp_user_event_task_award_gold_leaf` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
    `status` tinyint(2) unsigned NOT NULL DEFAULT '1' COMMENT '奖励发放状态 0 不发放 1 待发放 2 审核中 3 发放成功 4 发放中/待领取 5 发放失败',
    `reason` varchar(128) NOT NULL DEFAULT '' COMMENT '拒绝发放原因',
    `operator_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '操作人id',
    `operate_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '操作时间戳',
    `reviewer_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '审核人id',
    `review_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '审核通过时间戳',
    `user_id` int(10) unsigned NOT NULL COMMENT '被奖励人id,erp_student表主键',
    `uuid` varchar(32) NOT NULL DEFAULT '' COMMENT '用户唯一id',
    `user_type` tinyint(4) NOT NULL COMMENT '被奖励人类型 用户类型 1 学员 2 老师 3 机构',
    `event_task_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '活动任务id 对应event_task表',
    `award_num` int(11) NOT NULL DEFAULT '0' COMMENT '奖励的额度',
    `delay` int(11) NOT NULL DEFAULT '0' COMMENT '奖励延迟时间，单位是秒',
    `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间戳',
    `update_time` int(10) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB CHARSET=utf8mb4 COMMENT='用户金叶子任务奖励明细';


-------------------- op --------------------
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES
('WE_CHAT_RED_PACK', '微信红包显示语', 'POINTS_EXCHANGE_RED_PACK_SEND_NAME', '{\"act_name\":\"\\u8f6c\\u4ecb\\u7ecd\\u5956\\u52b1\",\"send_name\":\"\\u79ef\\u5206\\u5151\\u6362{{order_amounts}}\\u5143\\u7ea2\\u5305\",\"wishing\":\"\\u79ef\\u5206\\u5151\\u6362{{order_amounts}}\\u5143\\u7ea2\\u5305\"}', '积分兑换红包');
('SERVICE_SIGN_KEY', '各个服务调取op接口签名秘钥', 'erp_service', 'AAAEbm8uZQAAAAACAAABAAABFwAAAAdzc3gtcn', NULL);

-- 推荐人年卡 - 被推荐人购买体验卡
-- 需要先执行 insert erp_event_task 然后查出原值 select * from dict where `type`='node_relate_task' and `key_code`='2';
UPDATE `dict` SET `key_value` = '481,462,464,465,466,307,285,201,203,52,2' WHERE `type`='node_relate_task' and `key_code`='2';

-- 推荐人年卡 - 被推荐人购买年卡
-- 需要先执行 insert erp_event_task 然后查出原值 select * from dict where `type`='REFERRAL_CONFIG' and `key_code`='normal_task_config';
UPDATE `dict` SET `key_value` = '{"1":482,"2":482,"3":483,"4":483,"5":483,"6":484}' WHERE `type`='REFERRAL_CONFIG' and `key_code`='normal_task_config';

-- 推荐人年卡 - 被推荐人购买年卡 额外奖励
-- 需要先执行 insert erp_event_task 然后查出原值 select * from dict where `type`='REFERRAL_CONFIG' and `key_code`='extra_task_id_normal_xyzop_178';
UPDATE `dict` SET `key_value` = '485' WHERE  `type`='REFERRAL_CONFIG' and `key_code`='extra_task_id_normal_xyzop_178';

-- 红包表
CREATE TABLE `user_points_exchange_order` (
    `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
    `uuid` varchar(32) NOT NULL DEFAULT '' COMMENT '用户uuid',
    `user_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT 'dss.student表用户id',
    `order_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单id',
    `order_type` varchar(20) NOT NULL DEFAULT '' COMMENT '订单类型 red_pack:兑换红包',
    `order_from` varchar(20) NOT NULL DEFAULT '' COMMENT '订单来源 erp:erp服务',
    `points` int(10) NOT NULL DEFAULT '0' COMMENT '积分数量',
    `app_id` int(10) NOT NULL DEFAULT '0' COMMENT 'app id',
    `account_sub_type` smallint(4) NOT NULL COMMENT '账户子类型',
    `order_amounts` int(10) NOT NULL DEFAULT '0' COMMENT '订单金额 单位:分',
    `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_tiem` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARSET=utf8mb4 COMMENT='用户积分兑换订单';
CREATE TABLE `user_points_exchange_order_wx` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_points_exchange_order_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT 'user_points_exchange_order表主键',
    `uuid` varchar(32) NOT NULL DEFAULT '' COMMENT '用户uuid',
    `user_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT 'dss.student表用户id',
    `mch_billno` varchar(32) NOT NULL DEFAULT '' COMMENT '请求微信付款的交易号',
    `order_amounts` int(10) NOT NULL DEFAULT '0' COMMENT '订单金额 单位:分',
    `status` tinyint(1) NOT NULL DEFAULT '4' COMMENT '状态 0 不发放 1 待发放 2 审核中 3 发放成功 4 发放中/已发放待领取 5 发放失败',
    `status_code` varchar(50) NOT NULL DEFAULT '' COMMENT '失败原因状态码',
    `open_id` varchar(32) DEFAULT '' COMMENT '用户微信标识',
    `app_id` tinyint(4) DEFAULT '0' COMMENT '业务标识',
    `busi_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '业务类型 1：学生服务号 2：老师服务号 3：学生订阅号 4: 老师订阅号 5: XX小程序',
    `result_status` tinyint(1) NOT NULL DEFAULT '4' COMMENT '请求微信的结果 3发放成功，4发放中/已发放待领取，5发放失败 ',
    `result_code` varchar(500) NOT NULL DEFAULT '' COMMENT '请求微信的返回值',
    `record_sn` int(10) NOT NULL DEFAULT '0' COMMENT '全局唯一记录标识',
    `create_time` int(11) NOT NULL DEFAULT '0',
    `update_time` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `mch_billno` (`mch_billno`),
    KEY `user_points_exchange_order_id` (`user_points_exchange_order_id`)
) ENGINE=InnoDB CHARSET=utf8mb4 COMMENT='积分兑换红包微信交易信息';

-- 更新客服消息
UPDATE `wechat_config` SET `content` = '您好，您的金叶子已到账，详情如下：\n任务名称：上传截图领奖\n任务内容：{{activityName}}\n完成情况：审核通过，小叶子奖励您{{awardValue}}金叶子，请再接再厉呀！\n<a href="{{url}}">更多的奖励活动期待您的参与，【点此消息】分享海报赢金叶子</a>' WHERE `id` = 259;
INSERT INTO `wechat_config`(`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`, `event_task_id`, `to`) VALUES
(1, '奖励金叶子已经发放，详情如下：\n任务名称：转介绍奖励\n任务内容：邀请好友报名智能陪练体验营\n完成情况：已完成，奖励{{awardValue}}金叶子已发放，请到【我的】及时查看\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1583918495, 0, 0, 0, 481, 1),
(1, '奖励金叶子已经发放，详情如下：\n任务名称：转介绍奖励\n任务内容：好友付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}金叶子已发放，请到【我的】及时查看\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1583918495, 0, 0, 0, 482, 1),
(1, '奖励金叶子已经发放，详情如下：\n任务名称：转介绍奖励\n任务内容：付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}金叶子已发放，请到【我的】及时查看\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1583918495, 0, 0, 0, 482, 2),
(1, '奖励金叶子已经发放，详情如下：\n任务名称：转介绍奖励\n任务内容：好友付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}金叶子已发放，请到【我的】及时查看\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1583918495, 0, 0, 0, 483, 1),
(1, '奖励金叶子已经发放，详情如下：\n任务名称：转介绍奖励\n任务内容：付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}金叶子已发放，请到【我的】及时查看\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1583918495, 0, 0, 0, 483, 2),
(1, '奖励金叶子已经发放，详情如下：\n任务名称：转介绍奖励\n任务内容：好友付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}金叶子已发放，请到【我的】及时查看\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1583918495, 0, 0, 0, 484, 1),
(1, '奖励金叶子已经发放，详情如下：\n任务名称：转介绍奖励\n任务内容：付费智能陪练年卡\n完成情况：已完成，奖励{{awardValue}}金叶子已发放，请到【我的】及时查看\n<a href=\"{{url}}\">【点此消息】查看更多邀请记录</a>', '3', 1, 'award', '', 1583918495, 0, 0, 0, 484, 2);


-------------------- dss --------------------
alter table share_poster add points_award_id varchar (128) not null default '' comment "积分奖励id";
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES
('node_setting', '节点的特殊设置', 'points_exchange_red_pack_id', 'points_red_pack', '积分兑换红包节点id'),
('operation_common_node', '通用红包搜索节点', 'points_red_pack', '金叶子商城兑换红包', '这个节点并不是event_task_id里面的人物');


-- 需要先执行 insert erp_event_task  然后查出原有值，最后更新 确认 key_value 对应的 event_task_id 是多少   key_value = event_task_id
UPDATE `dict` SET `key_value` = '478' WHERE `type` = 'normal_upload_poster_task' and `key_value`='302' and `key_code`='-1';
UPDATE `dict` SET `key_value` = '476' WHERE `type` = 'normal_upload_poster_task' and `key_value`='300' and `key_code`='0';
UPDATE `dict` SET `key_value` = '477' WHERE `type` = 'normal_upload_poster_task' and `key_value`='301' and `key_code`='1';
UPDATE `dict` SET `key_value` = '478' WHERE `type` = 'normal_upload_poster_task' and `key_value`='301' and `key_code`='2';

