CREATE TABLE `student_certificate` (
  `id`          int(11) unsigned    NOT NULL AUTO_INCREMENT,
  `student_id`  int(11) unsigned    NOT NULL
  COMMENT '学生id',
  `type`        tinyint(1) unsigned NOT NULL DEFAULT '1'
  COMMENT '证书类型:1班级毕业证书',
  `save_path`   varchar(100)        NOT NULL DEFAULT ''
  COMMENT '证书图片保存路径',
  `operator_id` int(11) unsigned    NOT NULL
  COMMENT '创建人uid',
  `status`      tinyint(1) unsigned NOT NULL DEFAULT '1'
  COMMENT '状态: 0无效1有效',
  `create_time` int(10) unsigned    NOT NULL
  COMMENT '创建时间',
  `update_time` int(10) unsigned    NOT NULL
  COMMENT '修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_sid_type` (`student_id`,`type`) USING BTREE
)
  COMMENT ='学生证书数据表';


set @parentMenuId = (select id from privilege where unique_en_name = 'community_operation');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('证书生成', '', 1594968995, 'get', 1, '证书生成', @parentMenuId, 'certificate_menu', 1);
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('证书添加', '/org_web/student_certificate/create_certificate', 1594968995, 'post', 0, '', 0, 'certificate_add', 1);
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('学生模糊搜索', '/org_web/student/fuzzy_search_student', 1594968995, 'get', 0, '', 0, 'mobile_search_student', 1);

INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('student_certificate_base_img', '学生证书图片底图', 'graduate', 'prod/img/remark_img/jyzs.png', NULL);