CREATE TABLE `package_ext` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL COMMENT '产品包id',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `package_type` tinyint(4) NOT NULL COMMENT '产品包类型 1体验 2正式',
  `trial_type` tinyint(4) NOT NULL COMMENT '体验包类型 1 49两周 2 9块9',
  `apply_type` tinyint(4) NOT NULL COMMENT '发货类型 1 自动生效 2 短信发送激活码',
  `update_time` int(11) DEFAULT NULL,
  `operator` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `package_id` (`package_id`)
) COMMENT='产品包扩展信息';

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_CHANNEL', '课包购买渠道', '1', 'APP');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_CHANNEL', '课包购买渠道', '2', '公众号');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_CHANNEL', '课包购买渠道', '3', 'ERP');

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_STATUS', '课包状态', '-1', '未发布');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_STATUS', '课包状态', '0', '不可用');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_STATUS', '课包状态', '1', '正常');

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_TYPE', '课包类型', '1', '体验课');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('PACKAGE_TYPE', '课包类型', '2', '正式课');

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('APPLY_TYPE', '发货类型', '1', '自动使用');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('APPLY_TYPE', '发货类型', '2', '发送激活码');

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('TRIAL_TYPE', '体验课类型', '1', '两周体验课');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('TRIAL_TYPE', '体验课类型', '2', '不到两周体验课');