CREATE TABLE `student_certificate_template` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `type`        TINYINT UNSIGNED NOT NULL DEFAULT '1'
  COMMENT '证书类型：1勤奋榜2王者榜3卓越奖4结业证书',
  `save_path`   VARCHAR(100)     NOT NULL DEFAULT ''
  COMMENT '证书图片保存路径',
  `operator_id` INT UNSIGNED     NOT NULL
  COMMENT '创建人uid',
  `status`      TINYINT UNSIGNED NOT NULL DEFAULT '1'
  COMMENT '状态0无效1有效',
  `create_time` INT UNSIGNED     NOT NULL
  COMMENT '创建时间',
  `update_time` INT UNSIGNED     NOT NULL
  COMMENT '修改时间',
  PRIMARY KEY (`id`)
)
  COMMENT = '学生证书模板数据表';


INSERT INTO `privilege` ( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ( '编辑学生宝宝名称', '/org_web/student/update_real_name', unix_timestamp( ), 'post', 0, '', 0, 'update_real_name', 1 );
INSERT INTO `privilege` ( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ( '搜索班级下的学生', '/org_web/student/collection_student', unix_timestamp( ), 'get', 0, '', 0, 'collection_student', 1 );

SET @pid = ( SELECT id FROM privilege WHERE NAME = "社群运营" );
INSERT INTO `privilege` ( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ( '榜单生成', '/org_web/student_certificate/certificate_template', unix_timestamp( ), 'get', 1, '榜单生成', @pid, 'list_menu', 1 );