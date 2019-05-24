CREATE TABLE `user_qr_ticket` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户类型为教师的学生的都用user_id',
  `qr_ticket` varchar(128) DEFAULT NULL COMMENT '申请二维码所需ticket',
  `qr_url` varchar(128) DEFAULT NULL COMMENT '二维码图片路径',
  `type` int(1) NOT NULL DEFAULT '1' COMMENT '推荐人类型 1 学生 2 老师',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '生成时间戳',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;