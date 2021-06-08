-- 权限设置
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('机构代理商学员添加', '/op_web/agent_org/student_add', unix_timestamp(), 'post', 0, '', 0, 'agent_org_student_add', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('机构代理商学员删除', '/op_web/agent_org/student_del', unix_timestamp(), 'post', 0, '', 0, 'agent_org_student_del', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('机构代理商学员列表', '/op_web/agent_org/student_list', unix_timestamp(), 'get', 0, '', 0, 'agent_org_student_list', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('机构代理商学员批量导入', '/op_web/agent_org/student_import', unix_timestamp(), 'post', 0, '', 0, 'agent_org_student_import', 1);