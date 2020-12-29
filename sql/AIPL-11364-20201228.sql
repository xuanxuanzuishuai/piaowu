-- EPR：!!!
-- EPR：!!!
-- EPR：!!!
INSERT INTO `erp_event_task` (`type`, `condition`, `event_id`, `name`, `desc`, `create_time`, `creator_id`, `update_time`, `updator_id`, `award`, `start_time`, `end_time`, `status`)
VALUES
	(1, 'c', 1, '付费体验卡', 'c', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":1000,\"type\":1,\"delay\":0,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1),
	(1, 'c', 1, '付费年卡', 'c', unix_timestamp(), 751, unix_timestamp(), 10027, '{\"awards\":[{\"to\":1,\"amount\":16800,\"type\":1,\"delay\": 604800,\"need_check\":1},{\"to\":2,\"amount\":5000,\"type\":1,\"delay\":604800,\"need_check\":1}]}', unix_timestamp(), 1672416000, 1);


-- OP:
-- OP:
-- OP:
set @trail_task_id = 0;
set @year_task_id = 0;
-- 统一体验卡消息：
INSERT INTO `wechat_config` (`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`, `event_task_id`, `to`)
VALUES
-- PROD:G1hleUfvk7_lEi-Y5-2m5YBROuuySpwmQz9JcgmTHtM
  (1, '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"{{userName}}\\u60a8\\u597d\\uff0c\\u60a8\\u7684\\u7ea2\\u5305\\u5df2\\u5230\\u8d26\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u5206\\u4eab\\u6709\\u793c\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u9080\\u597d\\u53cb\\u52a0\\u5165\\u4f53\\u9a8c\\u8425\\uff0c\\u8d62\\u767e\\u5143\\u73b0\\u91d1\\u7ea2\\u5305\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u60a8\\u7684\\u597d\\u53cb{{mobile}}\\u5df2\\u62a5\\u540d\\u667a\\u80fd\\u966a\\u7ec3\\u4f53\\u9a8c\\u8425\\uff0c{{awardValue}}\\u5143\\u7ea2\\u5305\\u5956\\u52b1\\u5df2\\u53d1\\u653e\\uff0c\\u591a\\u9080\\u591a\\u5f97\\uff0c\\u4e0a\\u4e0d\\u5c01\\u9876\\uff01\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"\\u60a8\\u5df2\\u7d2f\\u8ba1\\u83b7\\u5f97\\u5956\\u52b1{{totalAward}}\\u5143\\u3010\\u70b9\\u6b64\\u6d88\\u606f\\u3011\\u67e5\\u770b\\u66f4\\u591a\\u9080\\u8bf7\\u8bb0\\u5f55\",\"color\":\"#FF8A00\"}}}', '3', 3, 'award', '', unix_timestamp(), 0, 0, 0, @trail_task_id, 1);

-- 统一购买年卡：
INSERT INTO `wechat_config` (`type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`, `event_task_id`, `to`)
VALUES
  (1, '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"{{userName}}\\u60a8\\u597d\\uff0c\\u60a8\\u7684\\u7ea2\\u5305\\u5df2\\u5230\\u8d26\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u5206\\u4eab\\u6709\\u793c\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u9080\\u597d\\u53cb\\u8d2d\\u4e70\\u5e74\\u5361\\u670d\\u52a1\\uff0c\\u8d62\\u767e\\u5143\\u73b0\\u91d1\\u7ea2\\u5305\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u60a8\\u7684\\u597d\\u53cb{{referralMobile}}\\u5df2\\u8d2d\\u4e70\\u667a\\u80fd\\u966a\\u7ec3\\u5e74\\u5361\\u670d\\u52a1\\uff0c{{awardValue}}\\u5143\\u7ea2\\u5305\\u5956\\u52b1\\u5df2\\u53d1\\u653e\\uff0c\\u591a\\u9080\\u591a\\u5f97\\uff0c\\u4e0a\\u4e0d\\u5c01\\u9876\\uff01\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"\\u60a8\\u5df2\\u7d2f\\u8ba1\\u83b7\\u5f97\\u5956\\u52b1{{totalAward}}\\u5143\\u3010\\u70b9\\u6b64\\u6d88\\u606f\\u3011\\u67e5\\u770b\\u66f4\\u591a\\u9080\\u8bf7\\u8bb0\\u5f55\",\"color\":\"#FF8A00\"}}}', '3', 3, 'award', '', unix_timestamp(), 0, 0, 0, @year_task_id, 1),
  (1, '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"{{userName}}\\u60a8\\u597d\\uff0c\\u60a8\\u7684\\u7ea2\\u5305\\u5df2\\u5230\\u8d26\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u5206\\u4eab\\u6709\\u793c\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u5c0f\\u53f6\\u5b50\\u9001\\u597d\\u793c\\uff0c\\u597d\\u53cb\\u63a8\\u8350\\u8d2d\\u4e70\\u5c0f\\u53f6\\u5b50\\u667a\\u80fd\\u966a\\u7ec3\\u5e74\\u5361\\u670d\\u52a1\\uff0c\\u4eab{{awardValue}}\\u5143\\u8d2d\\u8bfe\\u7ea2\\u5305\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u5df2\\u5b8c\\u6210\\uff0c{{awardValue}}\\u5143\\u8d2d\\u8bfe\\u7ea2\\u5305\\u5df2\\u53d1\\u653e\\uff0c\\u8bf7\\u70b9\\u51fb\\u7ea2\\u5305\\u9886\\u53d6\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"\",\"color\":\"#FF8A00\"}}}', '3', 3, 'award', '', unix_timestamp(), 0, 0, 0, @year_task_id, 2);

-- const EXPECT_TRAIL_PAY    = 2; //付费体验卡
-- const EXPECT_YEAR_PAY     = 3; //付费年卡
-- const EXPECT_FIRST_NORMAL = 4; //首购智能正式课
UPDATE `dict` SET `key_value` = concat(@trail_task_id, ',', key_value) WHERE `key_code` = '2' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '3' AND `type` = 'node_relate_task';
UPDATE `dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '4' AND `type` = 'node_relate_task';

-- 上传截图红包消息：
UPDATE `wechat_config` SET `content` = '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"{{userName}}\\u60a8\\u597d\\uff0c\\u60a8\\u7684\\u7ea2\\u5305\\u5df2\\u5230\\u8d26\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\"},\"keyword1\":{\"value\":\"\\u5206\\u4eab\\u6709\\u793c\\u9886\\u5956\"},\"keyword2\":{\"value\":\"{{activityName}}\"},\"keyword3\":{\"value\":\"\\u5ba1\\u6838\\u901a\\u8fc7\\uff0c\\u5c0f\\u53f6\\u5b50\\u5956\\u52b1\\u60a8{{awardValue}}\\u5143\\u7ea2\\u5305\\uff0c\\u8bf7\\u518d\\u63a5\\u518d\\u5389\\u5440\\uff01\"},\"remark\":{\"value\":\"\\u60a8\\u5df2\\u7d2f\\u8ba1\\u83b7\\u5f97\\u5956\\u52b1{{totalAward}}\\u5143\\uff0c\\u3010\\u70b9\\u6b64\\u6d88\\u606f\\u3011\\u67e5\\u770b\\u66f4\\u591a\\u4efb\\u52a1\\u8bb0\\u5f55\"}}}' WHERE `id` = 259;
UPDATE `wechat_config` SET `content` = '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"{{userName}}\\u60a8\\u597d\\uff0c\\u8865\\u53d1\\u5956\\u52b1\\u7ea2\\u5305\\u5df2\\u53d1\\u653e\\uff0c\\u8bf7\\u53ca\\u65f6\\u9886\\u53d6\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u8865\\u53d1\\u7ea2\\u5305\\u5956\\u52b1\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"\\u5c0f\\u53f6\\u5b50\\u5956\\u52b1\\u7ea2\\u5305\\u8865\\u53d1\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u5df2\\u5b8c\\u6210\\uff0c\\u5956\\u52b1{{awardValue}}\\u5143\\u5df2\\u53d1\\u653e\\uff0c\\u8bf7\\u70b9\\u51fb\\u7ea2\\u5305\\u9886\\u53d6\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"\",\"color\":\"\"}}}' WHERE `id` = 240;
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) 
VALUES 
('REFERRAL_CONFIG', '转介绍配置', 'new_rule_start_time', 1609430400, '转介绍新规则启用时间点');

-- DSS:
-- DSS:
-- DSS:
set @trail_task_id = 0;
set @year_task_id = 0;
UPDATE dss_pre.`dict` SET `key_value` = concat(@trail_task_id, ',', key_value) WHERE `key_code` = '2' AND `type` = 'node_relate_task';
UPDATE dss_pre.`dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '3' AND `type` = 'node_relate_task';
UPDATE dss_pre.`dict` SET `key_value` = concat(@year_task_id, ',', key_value) WHERE `key_code` = '4' AND `type` = 'node_relate_task';

-- REDIS CACHE:
-- DSS:
-- dict_list_node_relate_task

-- OP:
-- dict_list_node_relate_task
-- op_wechat_config_259
-- op_wechat_config_240

