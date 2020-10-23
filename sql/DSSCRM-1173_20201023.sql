INSERT INTO `dict` ( `type`, `key_name`, `key_code`, `key_value`,`desc` )
VALUES
	('poster_qrcode_type', '推荐海报中二维码类型', 'poster_qrcode_type', 0, '0:普通二维码;1:小程序码');

ALTER TABLE `user_qr_ticket` ADD COLUMN `landing_type` INT(1) NOT NULL DEFAULT '1' COMMENT '扫描二维码后的跳转类型 1 为普通landing页 2 为小程序';