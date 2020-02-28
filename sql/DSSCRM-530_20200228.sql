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
('review_course_status', '学生状态', '0', '无', ''),
('review_course_status', '学生状态', '1', '体验卡40', ''),
('review_course_status', '学生状态', '2', '年卡1980', '');

-- 添加助教微信状态dict
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('add_assistant_wx_status', '添加助教微信状态', '0', '未加', ''),
('add_assistant_wx_status', '添加助教微信状态', '1', '已加', '');
