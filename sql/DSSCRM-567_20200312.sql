INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('修改产品包配置', '/org_web/package/packageDictEdit', 1572315610, 'post', 0, '修改产品包配置', 0, 'package_dict_edit');
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('产品包配置', '/org_web/package/packageDictDetail', 1572315610, 'get', 1, '产品包配置', 478, 'package_dict_detail');
INSERT INTO `privilege`(`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`) VALUES ('学生集合产品包列表', '/org_web/collection/getCollectionPackageList', 1572315610, 'get', 0, '学生集合产品包列表', 0, 'collection_package_list');

update dict set `desc`="体验课产品包" where type='WEB_STUDENT_CONFIG' and key_code='package_id';
update dict set `desc`="正式课产品包" where type='WEB_STUDENT_CONFIG' and key_code='plus_package_id';