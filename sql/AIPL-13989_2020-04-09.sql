-------------------- op --------------------
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('SERVICE_SIGN_KEY', '各个服务调取op接口签名秘钥', 'erp_service', 'AAAEbm9uZQAAAAAAAAABAAABFwAAAAdzc2gtcn', NULL);

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
    `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0 废除 1 待发放 2 审核中 3 发放成功 4 拒绝发放 5 发放中 6 发放失败 7 发放成功',
    `status_code` varchar(50) NOT NULL DEFAULT '' COMMENT '状态对应的说明',
    `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unq_order_id` (`order_type`,`order_id`)
) ENGINE=InnoDB CHARSET=utf8mb4 COMMENT='用户积分兑换订单';


CREATE TABLE `user_points_exchange_order_wx` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_points_exchange_order_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT 'user_points_exchange_order表主键',
    `uuid` varchar(32) NOT NULL DEFAULT '' COMMENT '用户uuid',
    `user_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT 'dss.student表用户id',
    `mch_billno` varchar(32) NOT NULL DEFAULT '' COMMENT '请求微信付款的交易号',
    `order_amounts` int(10) NOT NULL DEFAULT '0' COMMENT '订单金额 单位:分',
    `status` tinyint(1) NOT NULL DEFAULT '4' COMMENT '请求微信的结果 3发放成功，4发放中/已发放待领取，5发放失败 ',
    `open_id` varchar(32) DEFAULT '' COMMENT '用户微信标识',
    `app_id` tinyint(4) DEFAULT '0' COMMENT '业务标识',
    `busi_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '业务类型 1：学生服务号 2：老师服务号 3：学生订阅号 4: 老师订阅号 5: XX小程序',
    `result_code` varchar(500) NOT NULL DEFAULT '' COMMENT '请求微信的返回值',
    `create_time` int(11) NOT NULL DEFAULT '0',
    `update_time` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `mch_billno` (`mch_billno`),
    KEY `user_points_exchange_order_id` (`user_points_exchange_order_id`)
) ENGINE=InnoDB CHARSET=utf8mb4 COMMENT='积分兑换红包微信交易信息';


-------------------- dss --------------------
alter table share_poster add points_award_id varchar (128) not null default '' comment "积分奖励id"

-- 确认  event_id 是否正确
INSERT INTO `erp_event_task`(`type`, `condition`, `event_id`, `name`, `desc`, `create_time`, `creator_id`, `update_time`, `updator_id`, `award`, `start_time`, `end_time`, `status`) VALUES
(5, '{"can_repeat_attend":1}', 2, '上传截图领奖', '海报分享', 1588228856, 591, 1612087387, 591, '{"awards":[{"to":2,"amount":3000,"type":3,"need_check":0,"account_sub_type":"3002"}]}', 1611936000, 1893427200, 1),
(5, '{"can_repeat_attend":1}', 2, '上传截图领奖', '海报分享', 1588228856, 591, 1613806910, 10038, '{"awards":[{"to":2,"amount":2000,"type":3,"need_check":0,"account_sub_type":"3002"}]}', 1612281600, 1740585599, 1),
(5, '{"can_repeat_attend":1}', 2, '上传截图领奖', '海报分享', 1588228856, 591, 1612343404, 591, '{"awards":[{"to":2,"amount":1000,"type":3,"need_check":0,"account_sub_type":"3002"}]}', 1611936000, 1893427200, 1);
(1, 'c', 1, '付费体验卡', 'XYZOP-178-1.1', 1616756138, 751, 1616756138, 10027, '{"awards":[{"to":1,"amount":100,"type":3,"delay":0,"need_check":1,"account_sub_type":"3002"}]}', 1616756138, 1672416000, 1);

-- 确认 key_value 对应的 event_task_id 是多少   key_value = event_task_id
UPDATE `dict` SET `key_value` = '302' WHERE `type` = 'normal_upload_poster_task' and `key_value`='302' and `key_code`='-1';
UPDATE `dict` SET `key_value` = '300' WHERE `type` = 'normal_upload_poster_task' and `key_value`='300' and `key_code`='0';
UPDATE `dict` SET `key_value` = '301' WHERE `type` = 'normal_upload_poster_task' and `key_value`='301' and `key_code`='1';
UPDATE `dict` SET `key_value` = '301' WHERE `type` = 'normal_upload_poster_task' and `key_value`='301' and `key_code`='2';
-- select * from dict where `type`='node_relate_task' and `key_code`='2';
UPDATE `dict` SET `key_value` = '301' WHERE `type`='node_relate_task' and `key_code`='2';


-------------------- erp --------------------
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
    KEY `user_id` (`user_id`),
    KEY `create_time` (`create_time`),
    KEY `review_time` (`review_time`),
    KEY `operate_time` (`operate_time`)
) ENGINE=InnoDB CHARSET=utf8mb4 COMMENT='用户金叶子任务奖励明细';