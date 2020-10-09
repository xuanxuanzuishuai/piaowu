CREATE TABLE `student_sign_up` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `student_id` int(11) NOT NULL COMMENT '学生ID',
  `collection_id` int(11) NOT NULL COMMENT '集合ID',
  `lesson_count` int(11) NOT NULL DEFAULT '0' COMMENT '集合课程总数',
  `start_week` tinyint(4) NOT NULL DEFAULT '1' COMMENT '开课时间（天）1：周一；2：周二；……；7：周日',
  `start_time` int(10) NOT NULL COMMENT '开课时间（小时:分）',
  `last_course_time` int(10) NOT NULL COMMENT '最后一节课上课时间',
  `bind_status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '报名状态，1：报名成功； 2：取消报名',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  `first_course_time` int(10) NOT NULL DEFAULT '0' COMMENT '第一节课上课时间',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `collection_id` (`collection_id`)
) COMMENT='用户报名表';


CREATE TABLE `student_learn_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `student_id` int(11) NOT NULL COMMENT '学生ID',
  `collection_id` int(11) NOT NULL COMMENT '集合ID',
  `lesson_id` int(11) NOT NULL COMMENT '课程ID',
  `learn_status` tinyint(4) NOT NULL COMMENT '课程学习进度，1：学习完成；2：已补课',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `collection_id` (`collection_id`)
) COMMENT='学习记录表';

CREATE TABLE `student_collection_expect` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `student_id` int(11) NOT NULL COMMENT '学员ID',
  `collection_id` int(11) NOT NULL COMMENT '集合ID',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `collection_id` (`collection_id`)
) COMMENT='待上线课程期待表';