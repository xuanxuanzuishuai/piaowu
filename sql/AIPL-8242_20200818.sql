INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('WEIXIN_STUDENT_CONFIG', '智能陪练微信端设置', 'shared_day_report_channel_id', '1550', '日报转介绍');

CREATE TABLE `day_report_fabulous` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `student_id` int(11) NOT NULL COMMENT '用户ID',
  `open_id` varchar(45) NOT NULL COMMENT '点赞人的open_id',
  `create_time` int(11) NOT NULL COMMENT '创建时间戳',
  `day_report_date` varchar(25) NOT NULL COMMENT '日报生成时间 y-m-d',
  PRIMARY KEY (`id`),
  KEY `student_report_time_index` (`student_id`,`day_report_date`)
) COMMENT='日报用户点赞表'

