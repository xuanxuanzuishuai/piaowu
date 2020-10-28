CREATE TABLE `student_leave_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT '学生ID',
  `gift_code_id` int(11) NOT NULL COMMENT '激活码',
  `leave_operator` int(11) NOT NULL COMMENT '请假操作人',
  `leave_time` int(11) NOT NULL COMMENT '请假时间',
  `start_leave_time` int(11) NOT NULL COMMENT '请假开始时间',
  `end_leave_time` int(11) NOT NULL COMMENT '请假结束时间',
  `leave_days` int(11) NOT NULL COMMENT '请假天数',
  `actual_end_time` int(11) DEFAULT NULL COMMENT '实际截止日期',
  `actual_days` int(11) DEFAULT '0' COMMENT '实际请假天数',
  `cancel_operator` int(11) DEFAULT NULL COMMENT '取消操作人',
  `cancel_time` int(11) DEFAULT NULL COMMENT '取消操作时间',
  `leave_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '请假状态 1正常 2取消',
  `cancel_operator_type` tinyint(11) NOT NULL DEFAULT '0' COMMENT '取消请假操作人类型 1课管  2用户 3系统（用户退费）',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `gift_code_id` (`gift_code_id`)
)COMMENT='学生请假表'