ALTER TABLE `user_qr_ticket`
ADD COLUMN `channel_id` int(11) NULL COMMENT '二维码代表的渠道id' AFTER `qr_url`;

INSERT INTO `dict` (
	`type`,
	`key_name`,
	`key_code`,
	`key_value`,
	`desc`
)
VALUES
	(
		'STUDENT_INVITE',
		'学生转介绍渠道',
		'NORMAL_STUDENT_INVITE_STUDENT',
		'1220',
	'学生点击邀请好友渠道'
	);