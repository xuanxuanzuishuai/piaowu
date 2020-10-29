ALTER TABLE `employee`
ADD COLUMN `ding_mobile` varchar(16) NULL COMMENT '钉钉手机号' AFTER `leads_max_nums` ;

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('ding_ding', 'go的钉钉接口', 'host', 'dd-tmp.xiongmaopeilian.com', NULL);

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('ding_ding', 'go的钉钉接口', 'flow_url', 'http://www.baidu.com?detail_id=', NULL);

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('ding_apply_status', '钉钉审批状态', '1', '发起审批 ', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('ding_apply_status', '钉钉审批状态', '2', '审批中', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('ding_apply_status', '钉钉审批状态', '3', '审批被拒绝', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('ding_apply_status', '钉钉审批状态', '4', '审批通过', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('ding_apply_status', '钉钉审批状态', '5', '已撤销', NULL);