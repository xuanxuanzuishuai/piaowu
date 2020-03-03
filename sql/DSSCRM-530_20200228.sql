-- student表添加新字段
ALTER table `student`
  ADD `collection_id` int(11) unsigned DEFAULT NULL COMMENT '集合ID',
  ADD `allot_collection_time` int(11) NOT NULL DEFAULT '0' COMMENT '分配集合时间',
  ADD `assistant_id` int(10) unsigned DEFAULT NULL COMMENT '助教ID',
  ADD `allot_assistant_time` int(11) NOT NULL DEFAULT '0' COMMENT '分配助教时间',
  ADD `is_add_assistant_wx` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否添加助教微信 0 未添加 1 已添加';

-- 新建学员集合日志表
CREATE TABLE `student_collection_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
    `student_id` int(11) NOT NULL COMMENT '学员id',
    `old_collection_id` int(11) NOT NULL DEFAULT '0' COMMENT '原集合id',
    `new_collection_id` int(11) NOT NULL COMMENT '新集合id',
    `create_time` int(10) NOT NULL COMMENT '创建时间',
    `operator_id` int(11) NOT NULL COMMENT '操作人id',
    `operate_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '操作类型 1 分配集合',
    `group_id` varchar(32) NOT NULL COMMENT '操作组id',
    PRIMARY KEY (`id`),
    KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='学员集合日志表';

-- 新建学员助教日志表
CREATE TABLE `student_assistant_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
    `student_id` int(11) NOT NULL COMMENT '学员id',
    `old_assistant_id` int(11) NOT NULL DEFAULT '0' COMMENT '原助教id',
    `new_assistant_id` int(11) NOT NULL COMMENT '新助教id',
    `create_time` int(10) NOT NULL COMMENT '创建时间',
    `operator_id` int(11) NOT NULL COMMENT '操作人id',
    `operate_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '操作类型 1 分配教师',
    `group_id` varchar(32) NOT NULL COMMENT '操作组id',
    PRIMARY KEY (`id`),
    KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='学员助教日志表';

-- 添加学生状态dict
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('review_course_status', '学生状态', '0', '已注册', ''),
('review_course_status', '学生状态', '1', '付费体验课', ''),
('review_course_status', '学生状态', '2', '付费正式课', '');

-- 添加助教微信状态dict
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('add_assistant_wx_status', '添加助教微信状态', '0', '未加', ''),
('add_assistant_wx_status', '添加助教微信状态', '1', '已加', '');

-- 添加学员微信绑定状态dict
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('wx_bind_status', '学员微信绑定状态', '0', '已绑定', ''),
('wx_bind_status', '学员微信绑定状态', '1', '未绑定', '');

-- 添加学员有效期状态dict
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('effect_status', '学员有效期状态', '0', '未过期', ''),
('effect_status', '学员有效期状态', '1', '已过期', '');


-- 添加权限
INSERT INTO `privilege` (`id`, `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`)
VALUES
  ('464', '基础', '', 1583224153, 'get', 1, '基础', 0, 'basic');

/**
 * => Notice: 如果上一条数据插入失败，下列数据parent_id 字段应为上一段sql执行后ID。
 */
INSERT INTO `privilege` (`name`, `uri`, `method`, `unique_en_name`, `parent_id`, `is_menu`, `menu_name`, `created_time`)
VALUES
  ('学员管理', '/student/student/searchList', 'get', 'studentList', '464', '1', '学员管理', 1583224153),
  ('学员详情接口', '/student/student/detail', 'get', 'student_detail', '464', '0', '', 1583224153),
  ('学员更新添加助教微信状态', '/student/student/updateAddAssistantStatus', 'post', 'addAssistantStatus', '464', '0', '', 1583224153),
  ('学员分配班级接口', '/student/student/allotCollection', 'post', 'allotCollection', '464', '0', '', 1583224153),
  ('学员分配助教接口', '/student/student/allotAssistant', 'post', 'allotAssistant', '464', '0', '', 1583224153);
