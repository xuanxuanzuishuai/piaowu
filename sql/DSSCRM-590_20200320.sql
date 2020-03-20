ALTER TABLE `student`
ADD COLUMN `wechat_account` varchar(25) NOT NULL COMMENT '微信账号' AFTER `allot_course_id`;

ALTER TABLE `student_assistant_log`
MODIFY COLUMN `operate_type` tinyint(4) NOT NULL DEFAULT 1 COMMENT '操作类型 1 分配助教 2班级分配助教触发的学生分配助教' AFTER `operator_id`,
ADD COLUMN `extra_info` varchar(255) NOT NULL COMMENT '扩展信息' AFTER `group_id`;

CREATE TABLE `collection_assistant_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `collection_id` int(11) NOT NULL COMMENT '班级id',
  `old_assistant_id` int(11) NOT NULL DEFAULT '0' COMMENT '原助教id',
  `new_assistant_id` int(11) NOT NULL COMMENT '新助教id',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `create_uid` int(11) NOT NULL COMMENT '操作人id',
  `operate_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '操作类型 1调整班级助教',
  PRIMARY KEY (`id`),
  KEY `collection_id` (`collection_id`) USING BTREE COMMENT '班级ID普通索引'
) COMMENT='班级信息调整日志表';

INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('编辑学生微信号', '/student/student/updateAddWeChatAccount', 1584935747, 'post', 0, '编辑学生微信号', 0, 'updateadd_wechat_account');
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('班级分配助教', '/org_web/collection/reAllotCollectionAssistant', 1584935747, 'post', 0, '班级分配助教', 0, 'allot_collection_assistant');