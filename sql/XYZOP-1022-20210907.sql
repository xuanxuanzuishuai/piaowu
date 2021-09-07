
set @parentMenuId = (select id from privilege where unique_en_name = 'operations_management');


INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('活动中心列表', '/op_web/activity_center/list', unix_timestamp(), 'get', 1, '活动中心', @parentMenuId, 'activity_center_list', 1),
 ('活动中心详情', '/op_web/activity_center/detail', unix_timestamp(), 'get', 0, '', 0, 'activity_center_detail', 1),
 ('活动中心上下架', '/op_web/activity_center/edit_status', unix_timestamp(), 'post', 0, '', 0, 'activity_center_update_status', 1),
 ('活动中心修改权重', '/op_web/activity_center/edit_weight', unix_timestamp(), 'post', 0, '', 0, 'activity_center_edit_weight', 1),
 ('活动中心更新', '/op_web/activity_center/update', unix_timestamp(), 'post', 0, '', 0, 'activity_center_update', 1),
 ('活动中心添加', '/op_web/activity_center/add', unix_timestamp(), 'post', 0, '', 0, 'activity_center_add', 1);

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('activity_center_show_rule', '活动中心显示规则', '1', '仅注册', ''),
  ('activity_center_show_rule', '活动中心显示规则', '2', '体验期中', ''),
  ('activity_center_show_rule', '活动中心显示规则', '3', '体验过期未付费正式课', ''),
  ('activity_center_show_rule', '活动中心显示规则', '4', '付费正式课有效期中', ''),
  ('activity_center_show_rule', '活动中心显示规则', '5', '付费正式课已过期', ''),
  ('up_down_shelf', '上下架状态', '1', '上架', ''),
  ('up_down_shelf', '上下架状态', '2', '下架', '');



CREATE TABLE `activity_center` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '名称',
  `url` varchar(255) NOT NULL COMMENT '活动地址',
  `banner` varchar(255) NOT NULL COMMENT '活动封面图',
  `show_rule` varchar(100) NOT NULL DEFAULT '' COMMENT '显示规则 1仅注册、2体验期中、3体验过期未付费正式课、4付费正式课有效期中、5付费正式课已过期',
  `button` varchar(255) NOT NULL COMMENT '按钮文案',
  `label` varchar(32) NOT NULL COMMENT '活动标签',
  `channel` varchar(100) NOT NULL DEFAULT '' COMMENT '活动渠道',
  `weight` int(11) unsigned NOT NULL COMMENT '权重',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 1上架 2下架',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  `update_time` int(10) NOT NULL COMMENT '修改时间',
  `create_by` int(11) NOT NULL COMMENT '创建人',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;