CREATE TABLE `referral_activity` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `event_id` int(11) unsigned NOT NULL COMMENT '关联事件ID',
  `task_id` int(11) unsigned NOT NULL COMMENT '任务ID',
  `name` varchar(255) NOT NULL COMMENT '活动名称',
  `guide_word` text NOT NULL COMMENT '引导语',
  `share_word` text NOT NULL COMMENT '分享语',
  `poster_url` varchar(255) NOT NULL COMMENT '海报图片',
  `start_time` int(10) unsigned NOT NULL COMMENT '活动开始时间戳',
  `end_time` int(10) unsigned NOT NULL COMMENT '活动结束时间戳',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '活动状态 0:未启用, 1:启用 2:禁用',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL COMMENT '更新时间',
  `operator_id` int(11) unsigned NOT NULL COMMENT '操作人',
  `remark` varchar(100) NOT NULL COMMENT '备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='转介绍活动表';


CREATE TABLE `message_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `type` tinyint(1) unsigned NOT NULL COMMENT '消息类型1短信2微信',
  `activity_id` int(11) unsigned NOT NULL COMMENT '活动ID',
  `success_num` int(11) unsigned NOT NULL COMMENT '发送成功数量',
  `fail_num` int(11) unsigned NOT NULL COMMENT '发送失败数量',
  `operator_id` int(11) unsigned NOT NULL COMMENT '操作人',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='信息发送记录表';


CREATE TABLE `share_poster` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `student_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '学员id',
  `activity_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '活动ID',
  `img_url` varchar(255) NOT NULL DEFAULT '' COMMENT '图片地址',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '审核状态 1待审核 2合格 3不合格',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  `check_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '审核时间',
  `operator_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '审核人uid',
  `reason` varchar(255) NOT NULL DEFAULT '' COMMENT '审核原因',
  `award_id` varchar(64) NOT NULL DEFAULT '0' COMMENT 'erp_user_event_task_award主键id',
  `remark` varchar(100) NOT NULL COMMENT '其他原因',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='用户分享海报表';


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES
('activity_time_status', '活动时间状态', '1', '待开始'),
('activity_time_status', '活动时间状态', '2', '进行中'),
('activity_time_status', '活动时间状态', '3', '已结束'),
('activity_status', '活动启用状态', '1', '已启用'),
('activity_status', '活动启用状态', '2', '已禁用'),
('activity_status', '活动启用状态', '0', '待启用'),
('ALI_OSS_CONFIG', '阿里OSS相关配置', 'img_size_h', 'h_960');


INSERT INTO `privilege` (`name`, `uri`, `method`, `unique_en_name`, `parent_id`, `is_menu`, `menu_name`, `created_time`)
VALUES
('转介绍活动管理', '/org_web/activity/list', 'get', 'activityList', '478', '1', '转介绍活动管理', 1588042491),
('分享截图审核', '/org_web/share_poster/list', 'get', 'posterList', '478', '1', '分享截图审核', 1588042491),
('转介绍活动详情', '/org_web/activity/detail', 'get', 'activityDetail', '0', '0', '', 1588042491),
('Erp事件', '/org_web/activity/event', 'get', 'activityEvent', '0', '0', '', 1588042491),
('添加转介绍活动', '/org_web/activity/add', 'post', 'addActivity', '0', '0', '', 1588042491),
('修改转介绍活动', '/org_web/activity/modify', 'post', 'modifyActivity', '0', '0', '', 1588042491),
('启用、禁用转介绍活动', '/org_web/activity/update_status', 'post', 'updateActivityStatus', '0', '0', '', 1588042491),
('发送转介绍活动短信', '/org_web/activity/send_msg', 'post', 'sendActivityMsg', '0', '0', '', 1588042491),
('发送转介绍活动微信消息', '/org_web/activity/push_weixin_msg', 'post', 'sendActivityWxMsg', '0', '0', '', 1588042491),
('分享截图审核通过', '/org_web/share_poster/approved', 'post', 'sharePosterApproved', '0', '0', '', 1588042491),
('分享截图审核拒绝', '/org_web/share_poster/refused', 'post', 'sharePosterRefused', '0', '0', '', 1588042491);