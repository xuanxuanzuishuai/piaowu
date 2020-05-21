ALTER TABLE `student`
  ADD COLUMN `course_manage_id` INT(11) UNSIGNED NOT NULL
COMMENT '课管ID'
  AFTER `sync_status`;

CREATE TABLE `student_course_manage_log` (
  `id`            int(11) unsigned    NOT NULL AUTO_INCREMENT
  COMMENT '自增id',
  `student_id`    int(11) unsigned    NOT NULL
  COMMENT '学员id',
  `old_manage_id` int(11) unsigned    NOT NULL DEFAULT '0'
  COMMENT '原课管id',
  `new_manage_id` int(11) unsigned    NOT NULL
  COMMENT '新课管id',
  `create_time`   int(10) unsigned    NOT NULL
  COMMENT '创建时间',
  `operator_id`   int(11) unsigned    NOT NULL
  COMMENT '操作人id',
  `operate_type`  tinyint(1) unsigned NOT NULL DEFAULT '1'
  COMMENT '操作类型 1 手动分配课管',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
)
  COMMENT ='学生课管分配日志表';

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('role_id', '角色ID', 'COURSE_MANAGE_ROLE_ID', (SELECT id FROM role WHERE NAME = '课管'), '课管');


INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('学生分配课管', '/org_web/student/allot_course_manage', 1590116903, 'post', 0, '学生分配课管', 0, 'allot_course_manage', 1);