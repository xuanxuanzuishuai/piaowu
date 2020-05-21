CREATE TABLE `student_acquired_log` (
  `id`           int(11) unsigned    NOT NULL AUTO_INCREMENT
  COMMENT '自增id',
  `student_id`   int(11) unsigned    NOT NULL
  COMMENT '学员id',
  `create_time`  int(10) unsigned    NOT NULL
  COMMENT '创建时间',
  `operator_id`  int(11) unsigned    NOT NULL
  COMMENT '操作人id',
  `operate_type` tinyint(1) unsigned NOT NULL DEFAULT '1'
  COMMENT '操作类型 1获取手机号',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
)
  COMMENT ='学生信息获取记录日志表';


INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('获取学生手机号', '/org_web/student/student_mobile', 1590116903, 'get', 0, '获取学生手机号', 0, 'get_student_mobile', 1);