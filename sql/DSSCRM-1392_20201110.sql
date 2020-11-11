SET @task_id = (SELECT id FROM erp_event_task WHERE `type`=13 AND `name`='11月转介绍活动红包-20元' LIMIT 1); 

INSERT INTO `wechat_config`(`id`, `type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`) VALUES (@task_id, 1, '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"\\u5956\\u52b1\\u7ea2\\u5305\\u5df2\\u7ecf\\u53d1\\u653e\\uff0c\\u8bf7\\u53ca\\u65f6\\u9886\\u53d6\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u4e0a\\u4f20\\u622a\\u56fe\\u9886\\u5956\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"11\\u6708\\u5168\\u52e4\\u5956\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u5df2\\u5b8c\\u6210\\uff0c\\u5956\\u52b1{{awardValue}}\\u5143\\u5df2\\u53d1\\u653e\\uff0c\\u8bf7\\u70b9\\u51fb\\u7ea2\\u5305\\u9886\\u53d6\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"\",\"color\":\"\"}}}', '3', 3, 'award', '', unix_timestamp(), 0, 0, 0);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('operation_common_node', '通用红包搜索节点', '18', '11月全勤奖-20', '');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('node_relate_task', '节点对应的task', '18', @task_id, '');
UPDATE `dict` SET `key_value` = concat(key_value, ',', 18) WHERE key_code = 'not_verify_refund';
UPDATE `dict` SET `key_value` = concat(key_value, ',', 18) WHERE key_code = 'not_display_wait';


SET @task_id = (SELECT id FROM erp_event_task WHERE `type`=13 AND `name`='11月转介绍活动红包-10元' LIMIT 1); 

INSERT INTO `wechat_config`(`id`, `type`, `content`, `msg_type`, `content_type`, `event_type`, `event_key`, `create_time`, `update_time`, `create_uid`, `update_uid`) VALUES (@task_id, 1, '{\"template_id\":\"mAOTPhrPgGYgw2eOjBBdDGAadf4FEoqoaRs1VGWTl2Y\",\"url\":\"{{url}}\",\"vars\":{\"first\":{\"value\":\"\\u5956\\u52b1\\u7ea2\\u5305\\u5df2\\u7ecf\\u53d1\\u653e\\uff0c\\u8bf7\\u53ca\\u65f6\\u9886\\u53d6\\uff0c\\u8be6\\u60c5\\u5982\\u4e0b\\uff1a\",\"color\":\"#FF8A00\"},\"keyword1\":{\"value\":\"\\u4e0a\\u4f20\\u622a\\u56fe\\u9886\\u5956\",\"color\":\"#FF8A00\"},\"keyword2\":{\"value\":\"11\\u6708\\u65b0\\u4eba\\u798f\\u5229\",\"color\":\"#FF8A00\"},\"keyword3\":{\"value\":\"\\u5df2\\u5b8c\\u6210\\uff0c\\u5956\\u52b1{{awardValue}}\\u5143\\u5df2\\u53d1\\u653e\\uff0c\\u8bf7\\u70b9\\u51fb\\u7ea2\\u5305\\u9886\\u53d6\",\"color\":\"#FF8A00\"},\"remark\":{\"value\":\"\",\"color\":\"\"}}}', '3', 3, 'award', '', unix_timestamp(), 0, 0, 0);

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('operation_common_node', '通用红包搜索节点', '19', '11月新人福利-10', '');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('node_relate_task', '节点对应的task', '19', @task_id, '');
UPDATE `dict` SET `key_value` = concat(key_value, ',', 19) WHERE key_code = 'not_verify_refund';
UPDATE `dict` SET `key_value` = concat(key_value, ',', 19) WHERE key_code = 'not_display_wait';
