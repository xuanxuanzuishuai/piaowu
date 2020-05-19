-- 1、现“运营管理”改为“转介绍管理”，为一级导航。下级包括：推荐学员、红包审核、海报管理、转介绍活动管理、分享截图审核。
UPDATE privilege
SET name = "转介绍管理",menu_name = "转介绍管理"
WHERE
	unique_en_name = 'operations_management';

-- 2、新增“社群运营”，为一级导航。下级包括：助教班级、班级管理、学员管理
INSERT INTO `privilege` ( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name` )
VALUES
	( '社群运营', NULL, 1589855629, 'get', 1, '社群运营', 0, 'community_operation' );

UPDATE privilege
SET parent_id = ( SELECT * FROM ( SELECT id FROM privilege WHERE unique_en_name = 'community_operation' ) AS a )
WHERE
	unique_en_name IN ( 'collection_total_list', 'collection_list', 'studentList' );


-- 3、现“基础”改为“通用管理”，为一级导航。下级包括：产品包配置、Banner管理、伪造验证码。
UPDATE privilege
SET name = "通用管理",menu_name = "通用管理"
WHERE
	unique_en_name = 'basic';

UPDATE privilege
SET parent_id = ( SELECT * FROM ( SELECT id FROM privilege WHERE unique_en_name = 'basic' ) AS a )
WHERE
	unique_en_name IN ( 'package_dict_detail', 'banner_list', 'fake_sms_code' );