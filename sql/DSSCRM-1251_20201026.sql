CREATE TABLE `gift_code_detailed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gift_code_id` int(11) NOT NULL COMMENT '激活码ID',
  `apply_user` int(11) NOT NULL COMMENT '激活码使用人',
  `code_start_date` int(11) NOT NULL COMMENT '激活码开始时间',
  `code_end_date` int(11) NOT NULL COMMENT '激活码结束时间',
  `package_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '课包类型类型 0非点评包 1体验包 2正式包',
  `valid_days` int(11) DEFAULT '0' COMMENT '有效期的天数',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) DEFAULT NULL COMMENT '修改时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '废除状态 0废除 1正常',
  `actual_days` int(11) DEFAULT '0' COMMENT '实际使用天数，只有用户退费才会更新此字段',
  PRIMARY KEY (`id`),
  KEY `gift_code_id` (`gift_code_id`),
  KEY `apply_user` (`apply_user`)
)COMMENT='激活码时长明细表'

SET @parentId = (select id from privilege where unique_en_name = 'community_operation');
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES ('学员请假', '/org_web/leave/student_leave', 1604383815, 'post', 0, '', @parentId, 'student_leave', 1);
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES ('取消请假', '/org_web/leave/cancel_leave', 1604383892, 'post', 0, '', @parentId, 'cancel_leave', 1);
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES ('请假状态', '/org_web/leave/leave_status', 1604389321, 'get', 0, '', @parentId, 'leave_status', 1);
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES ('可以请假时间段', '/org_web/leave/leave_period', 1604389262, 'get', 0, '', @parentId, 'leave_period', 1);
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`) VALUES ('请假记录', '/org_web/leave/list', 1604389182, 'get', 0, '', @parentId, 'leave_list', 1);