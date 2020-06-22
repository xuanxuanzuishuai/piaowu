CREATE TABLE `app_voice_call_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `receive_id` int(11) NOT NULL DEFAULT '0' COMMENT '接收者id',
  `app_id` tinyint(4) NOT NULL DEFAULT '1' COMMENT '业务线 1 熊猫',
  `receive_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '接收者类型 1 学生 ',
  `relate_schedule_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联课程id',
  `unique_id` varchar(32) NOT NULL DEFAULT '' COMMENT '一通呼叫的唯一标识',
  `customer_number` varchar(20) NOT NULL DEFAULT '' COMMENT '客户号码',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间戳',
  `start_time` int(11) NOT NULL DEFAULT '0' COMMENT '响铃时间戳',
  `answer_time` int(11) NOT NULL DEFAULT '0' COMMENT '接通时间戳',
  `end_time` int(11) NOT NULL DEFAULT '0' COMMENT '挂机时间戳',
  `ring_duration` int(11) NOT NULL DEFAULT '0' COMMENT '响铃时长',
  `answer_duration` int(11) NOT NULL DEFAULT '0' COMMENT '通话时间',
  `key` varchar(4) NOT NULL DEFAULT '' COMMENT '用户按键',
  `call_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '呼叫状态',
  `exec_time` int(11) NOT NULL DEFAULT '0' COMMENT '执行时间',
  `exec_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '执行状态 1 成功 ',
  `exec_msg` varchar(256) NOT NULL DEFAULT '' COMMENT '执行结果',
  `call_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '外呼类型 1开始上课前提醒 2学生迟到 3老师迟到',
  PRIMARY KEY (`id`),
  KEY `idx_unique_id` (`unique_id`),
  KEY `idx_receive_id` (`receive_id`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COMMENT='自动语音外呼日志表';

INSERT INTO `dss_dev`.`dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('VOICE_CALL_CONFIG', '天润语音通知', 'tianrun_voice_call_host', 'https://api.vlink.cn', NULL);
INSERT INTO `dss_dev`.`dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('VOICE_CALL_CONFIG', '天润语音通知', 'tianrun_voice_call_appid', '5000927', NULL);
INSERT INTO `dss_dev`.`dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('VOICE_CALL_CONFIG', '天润语音通知', 'tianrun_voice_call_token', 'b7d95b67f82277ba21a180434d7d659a', NULL);