ALTER TABLE `gift_code` ADD COLUMN `bill_amount` INT NULL COMMENT '订单金额' AFTER `remarks`;

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('generate_channel', '生成渠道', '4', '用户兑换(禁止手动创建)');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES ('generate_channel', '生成渠道', '5', '用户订单(禁止手动创建)');
