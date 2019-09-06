CREATE TABLE `flags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL COMMENT '标签名',
  `desc` text COMMENT '描述',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态 0 关闭 1 开启',
  `operator` int(11) NOT NULL COMMENT '操作人',
  PRIMARY KEY (`id`)
);

CREATE TABLE `filter` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL COMMENT '过滤器名',
  `flag_id` int(11) NOT NULL COMMENT '对应标签',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) DEFAULT NULL COMMENT '更新时间',
  `operator` int(11) NOT NULL COMMENT '操作人',
  `conditions` text NOT NULL COMMENT '过滤条件(json)',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态 0 关闭 1 开启',
  PRIMARY KEY (`id`)
);

ALTER TABLE `student`
  ADD COLUMN `flags` BIT(64) NOT NULL DEFAULT 0 COMMENT '用户标签' AFTER `first_pay_time`;

INSERT INTO `privilege` (`name`, `uri`,  `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES
  ('标签管理', '', '1561367570', 'get', '1', '标签管理', '0', 'flags_menu'),
  ('标签列表', '/org_web/flags/list', '1561367570', 'get', '1', '标签列表', '435', 'flags_list'),
  ('过滤器列表', '/org_web/flags/filter_list', '1561367570', 'get', '1', '过滤器列表', '435', 'filter_list'),
  ('添加标签', '/org_web/flags/add', 0, 'post', 0, '', 0, 'flags_add'),
  ('编辑标签', '/org_web/flags/modify', 0, 'post', 0, '', 0, 'flags_modify'),
  ('编辑学员标签', '/org_web/flags/student_flags_modify', 0, 'post', 0, '', 0, 'flags_student_flags_modify'),
  ('可用标签列表', '/org_web/flags/valid_flags', 0, 'get', 0, '', 0, 'flags_valid_flags'),
  ('添加过滤器', '/org_web/flags/filter_add', 0, 'post', 0, '', 0, 'flags_filter_add'),
  ('编辑过滤器', '/org_web/flags/filter_modify', 0, 'post', 0, '', 0, 'flags_filter_modify');

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES
  ('FLAG_ID', '用户标签id', 'new_score', '1', '新曲谱灰测标签'),
  ('FLAG_ID', '用户标签id', 'res_free', '2', '资源免费标签');
