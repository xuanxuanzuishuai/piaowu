CREATE TABLE `gift_code_detailed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gift_code_id` int(11) NOT NULL COMMENT '激活码ID',
  `apply_user` int(11) NOT NULL COMMENT '激活码使用人',
  `code_start_date` int(11) NOT NULL COMMENT '激活码开始时间',
  `code_end_date` int(11) NOT NULL COMMENT '激活码结束时间',
  `package_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '课包类型类型 0非点评包 1体验包 2正式包',
  `valid_days` int(11) DEFAULT '0' COMMENT '有效期的天数',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) DEFAULT NULL COMMENT '修改时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '废除状态 0废除 1正常',
  `actual_days` int(11) DEFAULT '0' COMMENT '实际使用天数，只有用户退费才会更新此字段',
  PRIMARY KEY (`id`),
  KEY `gift_code_id` (`gift_code_id`),
  KEY `apply_user` (`apply_user`)
)COMMENT='激活码时长明细表'