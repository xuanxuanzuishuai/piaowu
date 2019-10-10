CREATE TABLE `user_idfa` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `idfa` char(36) NOT NULL COMMENT '苹果设备的 IDFA',
  `imei` varchar(36) NOT NULL COMMENT 'Android的 imei',
  `os` tinyint(1) not null default 3 comment '操作系统类型 0 android 1 ios 3 other',
  `source` varchar(45) DEFAULT NULL,
  `callback_url` varchar(256) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT '注册用户id',
  `create_time` int(10) NOT NULL COMMENT '创建时间戳',
  `update_time` int(10) NOT NULL COMMENT '最后更新时间戳',
  `is_callback` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已经发送过callback 0 未发送 1 已发送'
  PRIMARY KEY (`id`),
  KEY `idfa` (`idfa`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;