CREATE TABLE `student_work_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `student_id` int(11) NOT NULL COMMENT '关联学员id',
  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '工单类型，1:曲谱需求 ',
  `student_opera_name` varchar(255) NOT NULL DEFAULT '' COMMENT '学员上传的曲谱名',
  `opera_num` int(11) NOT NULL COMMENT '曲谱数',
  `attachment` text NOT NULL COMMENT '附件',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1:待审核，2:未通过，3:已通过，4:制作中，5:配置中，6:已完成，7:已撤销',
  `assistant_id` int(11) NOT NULL DEFAULT '-1' COMMENT '助教id',
  `course_manage_id` int(11) NOT NULL COMMENT '课管id',
  `creator_id` int(11) NOT NULL DEFAULT '-1' COMMENT '创建人id',
  `creator_name` varchar(255) NOT NULL DEFAULT '' COMMENT '创建人姓名',
  `creator_type` tinyint(4) NOT NULL COMMENT '创建人平台来源',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间戳',
  `updator_id` int(11) NOT NULL DEFAULT '-1' COMMENT '最后更新人id',
  `update_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新时间戳',
  `estimate_day` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '预计完成时间',
  `refuse_msg` varchar(255) NOT NULL DEFAULT '' COMMENT '审核拒绝原因说明',
  `opera_maker_id` int(11) NOT NULL DEFAULT '-1' COMMENT '曲谱制作人ID',
  `opera_config_id` int(11) NOT NULL DEFAULT '-1' COMMENT '曲谱配置人ID',
  `textbook_name` varchar(255) NOT NULL DEFAULT '' COMMENT '教科书名',
  `opera_name` varchar(255) NOT NULL DEFAULT '' COMMENT '曲谱名',
  `view_guidance` varchar(255) NOT NULL DEFAULT '' COMMENT '查看指导说明',
  PRIMARY KEY (`id`,`update_time`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4;




CREATE TABLE `student_work_order_reply` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `swo_id` int(11) NOT NULL COMMENT '学员工单ID',
  `swo_status` tinyint(4) NOT NULL COMMENT '1:待审核，2:未通过，3:已通过，4:制作中，5:配置中，6:已完成，7:已撤销',
  `status` tinyint(4) NOT NULL COMMENT '1:待处理，2:处理中，3:完成，4:驳回',
  `is_cur` tinyint(4) NOT NULL COMMENT '是否是当前节点 0:否，1:是',
  `creator_id` int(11) NOT NULL COMMENT '分配人id',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '回复时间戳',
  `reply_id` int(11) NOT NULL COMMENT '回复人id',
  `reply_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '回复时间戳',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4;