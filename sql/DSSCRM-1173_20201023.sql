-- 智能陪练一级  -  智能体验营小程序注册
INSERT INTO `channel` (`id`, `name`, `create_time`, `status`, `parent_id`, `level`, `update_time`, `app_id`)
VALUES
	(2143, '智能体验营小程序注册', 1604045551, 1, 1205, 'A', 0, 1);

INSERT INTO `dict` ( `type`, `key_name`, `key_code`, `key_value`,`desc` )
VALUES
	('poster_qrcode_type', '推荐海报中二维码类型', 'qr_code_type', 0, '0:普通二维码;1:小程序码'),
	('STUDENT_INVITE', '学生转介绍渠道', 'REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT', '2143', '智能体验营小程序注册');

ALTER TABLE `user_qr_ticket` ADD COLUMN `landing_type` INT(1) NOT NULL DEFAULT '1' COMMENT '扫描二维码后的跳转类型 1 为普通landing页 2 为小程序';