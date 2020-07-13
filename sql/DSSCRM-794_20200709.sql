CREATE TABLE `check_in_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) unsigned NOT NULL COMMENT '学生id',
  `type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '类型:1签到得积分',
  `last_date` date NOT NULL COMMENT '最近签到日期格式2020-07-01',
  `days` int(11) unsigned NOT NULL DEFAULT '1' COMMENT '连续签到天数',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_sid_type` (`student_id`,`type`) USING BTREE COMMENT '学生id和类型唯一索引'
) COMMENT='用户连续签到统计表';


CREATE TABLE `point_activity_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) unsigned NOT NULL COMMENT '学生id',
  `task_id` int(11) unsigned NOT NULL COMMENT '活动ID',
  `report_date` date NOT NULL COMMENT '上报日期',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `idx_sid_tid` (`student_id`,`task_id`) USING BTREE COMMENT '学生id和活动id索引'
) COMMENT='积分活动参与记录表';

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('student_account_log_op_type', '学生账户操作日志子类型', '1001', '每日签到', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('student_account_log_op_type', '学生账户操作日志子类型', '1002', '练琴时长', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('student_account_log_op_type', '学生账户操作日志子类型', '1003', '系统充值', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('student_account_log_op_type', '学生账户操作日志子类型', '1004', '完成评测', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('student_account_log_op_type', '学生账户操作日志子类型', '1005', '分享评测', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('student_account_log_op_type', '学生账户操作日志子类型', '2001', '兑换商品扣减', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('student_account_log_op_type', '学生账户操作日志子类型', '2002', '过期扣减', NULL);
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('student_account_log_op_type', '学生账户操作日志子类型', '2003', '系统扣减', NULL);