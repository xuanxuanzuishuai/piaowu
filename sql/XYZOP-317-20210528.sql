ALTER TABLE `agent_info`
  ADD INDEX `idx_quantity`(`quantity`) USING BTREE;


CREATE TABLE `agent_pre_storage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `agent_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '代理商ID',
  `package_amount` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '课包数量',
  `package_unit_price` varchar(10) NOT NULL COMMENT '课包单价:单位分',
  `payment_serial_number` char(32) NOT NULL COMMENT '第三方支付流水号',
  `payment_mode` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '支付方式:1支付宝2微信3银行账号',
  `payment_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '支付时间:商户付款时间',
  `payment_screen_shot` varchar(100) NOT NULL COMMENT '支付结果截图url地址',
  `remark` varchar(600) NOT NULL COMMENT '备注',
  `package_type` tinyint(1) unsigned NOT NULL DEFAULT '2' COMMENT '课包类型:1体验包 2正式包',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态1待审核2审核通过3审核不通过',
  `creater_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建人uid',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL COMMENT '最后一次修改时间',
  PRIMARY KEY (`id`),
  KEY `idx_serial_number` (`payment_serial_number`) USING BTREE,
  KEY `idx_agent_id` (`agent_id`) USING BTREE
) COMMENT='代理商预存课包订单表';

CREATE TABLE `agent_pre_storage_detail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `pre_storage_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'agent_pre_storage数据表主键ID',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态:1未消耗2已消耗',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `parent_bill_id` varchar(30) NOT NULL COMMENT '订单ID',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '年卡消耗时间',
  PRIMARY KEY (`id`),
  KEY `idx_bill_id` (`parent_bill_id`) USING BTREE,
  KEY `idx_storage_id` (`pre_storage_id`) USING BTREE
) COMMENT='预存年卡数据详细数据表';

CREATE TABLE `agent_pre_storage_process_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `agent_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '代理商ID',
  `bill_id` varchar(30) NOT NULL COMMENT '关联订单ID',
  `amount` char(4) NOT NULL DEFAULT '0' COMMENT '数量',
  `type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '类型:1推广消耗2年卡预存',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_agent_id` (`agent_id`) USING BTREE,
  KEY `idx_bill_id` (`bill_id`) USING BTREE
) COMMENT='代理商预存数据产生和消费过程日志表';

CREATE TABLE `agent_pre_storage_refund` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `agent_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '代理商ID',
  `amount` char(4) NOT NULL DEFAULT '0' COMMENT '金额',
  `type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '类型:1推广消耗2退款打款',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态1待审核2审核通过退款成功3审核不通过',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `bill_id` varchar(30) NOT NULL COMMENT 'type是1时存储年卡订单ID',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `employee_id` int(11) NOT NULL DEFAULT '0' COMMENT '员工id',
  PRIMARY KEY (`id`),
  KEY `idx_agent_id` (`agent_id`) USING BTREE,
  KEY `idx_bill_id` (`bill_id`) USING BTREE
) COMMENT='代理商预存数据退款申请数据表';

CREATE TABLE `agent_pre_storage_review_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `data_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '审核的数据ID:agent_pre_storage/agent_pre_storage_refund数据表主键ID',
  `data_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '审核的数据类型:1预存年卡订单审核2退款申请审核',
  `type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '操作类型:1待审核2审核通过3驳回审核不通过4重新提交',
  `remark` varchar(600) NOT NULL COMMENT '审核不通过时的驳回原因备注',
  `reviewer_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建人uid',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_data_id` (`data_id`)
) COMMENT='预存订单审核/退款申请数据审核操作日志表';


INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('agent_storage_config', '代理商预存年卡配置', 'interval_time_days', '7', '年卡订单有效确认间隔期');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('agent_storage_process_log_type', '预存年卡数据产生和消费过程日志类型', '1', '推广消耗', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('agent_storage_process_log_type', '预存年卡数据产生和消费过程日志类型', '2', '年卡预存', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('agent_storage_approved_action', '预存订单审核操作行为', '1', '提交预存', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('agent_storage_approved_action', '预存订单审核操作行为', '2', '审核通过', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('agent_storage_approved_action', '预存订单审核操作行为', '3', '驳回', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('agent_storage_approved_action', '预存订单审核操作行为', '4', '重新提交', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('check_status', '审核状态', '1', '待审核', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('check_status', '审核状态', '2', '已通过', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('check_status', '审核状态', '3', '未通过', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('payment_mode', '支付方式类型', '3', '银行卡', '');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('payment_mode', '支付方式类型', '2', '微信', '');
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('payment_mode', '支付方式类型', '1', '支付宝', '');


-- 权限设置
set @parentMenuId = (select id from privilege where unique_en_name = 'agent_manage');
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商预存订单增加', '/op_web/agent_storage/add', unix_timestamp(), 'post', 0, '', 0, 'agent_storage_add', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商预存订单编辑', '/op_web/agent_storage/update', unix_timestamp(), 'post', 0, '', 0, 'agent_storage_update', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商预存订单详情', '/op_web/agent_storage/detail', unix_timestamp(), 'get', 0, '', 0, 'agent_storage_detail', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商预存订单列表', '/op_web/agent_storage/list', unix_timestamp(), 'get', 1, '预存订单', @parentMenuId, 'agent_storage_detail', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商预存订单审核', '/op_web/agent_storage/approval', unix_timestamp(), 'post', 0, '', 0, 'agent_storage_approval', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商预存年卡变化过程明细列表', '/op_web/agent_storage/process_log', unix_timestamp(), 'get', 0, '', 0, 'agent_storage_process_log', 1);