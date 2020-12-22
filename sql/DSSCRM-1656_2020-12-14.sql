CREATE TABLE `param_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `app_id` int(11) NOT NULL COMMENT '数据来源应用ID',
  `type` tinyint(255) NOT NULL COMMENT '学生：1，教师：2',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `param_info` varchar(255) NOT NULL DEFAULT '' COMMENT '参数信息',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `student_id` (`user_id`)
) COMMENT='分享海报参数映射表';