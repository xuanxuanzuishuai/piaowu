CREATE TABLE `student_account_award_points_file` (
     `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
     `operator_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT '操作人id',
     `app_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '应用id',
     `sub_type` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '账户子类型，同erp.student_account表一致',
     `org_file` varchar(200) NOT NULL DEFAULT '' COMMENT '上传原始文件oss地址',
     `chunk_file` varchar(200) NOT NULL DEFAULT '' COMMENT '原始文件分割后的小文件oss地址',
     `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0:等待执行 1:正在执行 2:执行完成',
     `remark` varchar(200) NOT NULL DEFAULT '' COMMENT '备注',
     `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
     `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '最后更新时间',
     PRIMARY KEY (`id`),
     KEY `idx_operator` (`operator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='学生批量发放积分excel文件信息记录';


CREATE TABLE `student_account_award_points_log` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
    `operator_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT '操作人id',
    `operator_type` tinyint(2) unsigned NOT NULL DEFAULT '1' COMMENT '操作者类型 0 系统 1 学员 2 员工 ',
    `app_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '应用id',
    `sub_type` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '账户子类型，同erp.student_account表一致',
    `num` int(10) NOT NULL DEFAULT '0' COMMENT '积分数量，乘以100保留2为小数',
    `student_id` int(11) unsigned NOT NULL COMMENT '学员id',
    `uuid` varchar(200) NOT NULL DEFAULT '' COMMENT '用户uuid',
    `mobile` varchar(11) NOT NULL DEFAULT '' COMMENT '手机号',
    `remark` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '备注',
    `file_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'student_account_award_points_file主键',
    `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_operator` (`operator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='学生批量发放积分详细日志';


insert into dict (`type`,`key_name`,`key_code`,`key_value`,`desc`) values
('send_mail_config','发送邮件设置','from_mail','opxitong@xiaoyezi.com','发送人'),
('send_mail_config','发送邮件设置','from_name','opxitong','发送人名称'),
('send_mail_config','发送邮件设置','from_mail_pasd','TheONE123','发送人密码'),
('send_mail_config','发送邮件设置','smtp_server','smtp.exmail.qq.com','smtp服务地址'),
('send_mail_config','发送邮件设置','smtp_port','465','smtp服务端口'),
('award_points_send_mail_config','批量发放积分邮件通知配置','to_mail','lianqingfeng@xiaoyezi.com','接受人邮件'),
('award_points_send_mail_config','批量发放积分邮件通知配置','title','批量发放积分','标题');