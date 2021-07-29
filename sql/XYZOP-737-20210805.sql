-- 权限设置
set @parentMenuId = (select id from privilege where unique_en_name = 'agent_manage');
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('商家代理添加', '/op_web/agent_business/add', unix_timestamp(), 'post', 0, '', 0, 'agent_business_add', 1),
 ('商家代理编辑', '/op_web/agent_business/update', unix_timestamp(), 'post', 0, '', 0, 'agent_business_update', 1),
 ('商家代理详情', '/op_web/agent_business/detail', unix_timestamp(), 'get', 0, '', 0, 'agent_business_detail', 1),
 ('商家代理列表', '/op_web/agent_business/list', unix_timestamp(), 'get', 1, '商家代理', @parentMenuId, 'agent_business_list', 1),
 ('商家代理冻结', '/op_web/agent_business/freeze', unix_timestamp(), 'post', 0, '', 0, 'agent_business_freeze', 1),
 ('商家代理解封', '/op_web/agent_business/unfreeze', unix_timestamp(), 'post', 0, '', 0, 'agent_business_unfreeze', 1);


 update `privilege` set menu_name = '在线代理'  where  unique_en_name = 'agent_list';
 update `dict` set key_value = '分销代理'  where `type` = 'agent_type' and key_code = 1;