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
) COMMENT = '工单申请表';




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
) COMMENT = '工单申请回复表';



INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES
('swo_status', '工单状态', '1', '待审核', '待审核'),
('swo_status', '工单状态', '2', '未通过', '未通过'),
('swo_status', '工单状态', '3', '已通过', '已通过'),
('swo_status', '工单状态', '4', '制作中', '制作中'),
('swo_status', '工单状态', '5', '配置中', '配置中'),
('swo_status', '工单状态', '6', '已完成', '已完成'),
('swo_status', '工单状态', '7', '已撤销', '已撤销'),
('ORG_WEB_CONFIG', '制作人角色ID', 'maker_role', '41', '制作人角色ID'),
('ORG_WEB_CONFIG', '配置人角色ID', 'config_role', '42', '制作人角色ID'),
('make_opera_template', '微信打谱进度通知模板跳转链接', 'status_url', 'https://dss-weixin.xiongmaopeilian.com/student/makeScore', '微信打谱进度通知模板跳转链接'),
('make_opera_template', '微信打谱进度通知模板ID', 'template_id', 'G1hleUfvk7_lEi-Y5-2m5YBROuuySpwmQz9JcgmTHtM', '微信打谱进度通知模板ID');



INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES
('曲谱制作管理', '/org_web/make_opera/swo_list', unix_timestamp(now()), 'get', 1, '曲谱制作管理', 527, 'swo_list', 1),
('根据手机号获取用户信息', '/org_web/make_opera/user_info', unix_timestamp(now()), 'get', 0, '', 0, 'swo_user_info', 1),
('打谱权限和进度查询', '/org_web/make_opera/schedule_query', unix_timestamp(now()), 'get', 0, '', 0, 'swo_schedule_query', 1),
('打谱申请', '/org_web/make_opera/opera_apply', unix_timestamp(now()), 'post', 0, '', 0, 'swo_opera_apply', 1),
('打谱申请历史', '/org_web/make_opera/history', unix_timestamp(now()), 'get', 0, '', 0, 'swo_history_list', 1),
('曲谱详情', '/org_web/make_opera/opera_detail', unix_timestamp(now()), 'get', 0, '', 0, 'swo_opera_detail', 1),
('打谱申请撤销', '/org_web/make_opera/cancel', unix_timestamp(now()), 'get', 0, '', 0, 'swo_cancel', 1),
('获取角色列表', '/org_web/make_opera/get_role_list', unix_timestamp(now()), 'get', 0, '', 0, 'swo_role_list', 1),
('分配制作人和配置人', '/org_web/make_opera/distribute_maker_configure', unix_timestamp(now()), 'post', 0, '', 0, 'swo_distribute', 1),
('打谱申请审核', '/org_web/make_opera/swo_approve', unix_timestamp(now()), 'post', 0, '', 0, 'swo_approve', 1),
('曲谱开始制作', '/org_web/make_opera/make_start', unix_timestamp(now()), 'get', 0, '', 0, 'swo_make_start', 1),
('曲谱制作完成', '/org_web/make_opera/make_end', unix_timestamp(now()), 'post', 0, '', 0, 'swo_make_end', 1),
('曲谱启用', '/org_web/make_opera/opera_use', unix_timestamp(now()), 'post', 0, '', 0, 'swo_opera_use', 1);