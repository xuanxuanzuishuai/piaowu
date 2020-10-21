CREATE TABLE `message_record_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `open_id` varchar(36) DEFAULT NULL COMMENT '智能陪练公众号的openid',
  `activity_type` tinyint(1) DEFAULT NULL COMMENT '推送的类别 3 基于规则的手动push 4 基于规则的自动push',
  `relate_id` int(11) DEFAULT NULL COMMENT '不同类别关联不同表的id',
  `push_res` tinyint(1) DEFAULT NULL COMMENT '推送结果0失败1成功',
  `create_time` int(11) DEFAULT NULL COMMENT '记录时间',
  PRIMARY KEY (`id`)
) COMMENT = '消息规则推送详细记录';