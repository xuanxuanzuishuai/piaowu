-- 添加Dict数据
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
('action_type', 'banner触发动作类型', '0', '无跳转', ''),
('action_type', 'banner触发动作类型', '1', '跳转小程序', ''),
('action_type', 'banner触发动作类型', '2', '跳转网页', ''),

('action_detail', 'banner触发动作详情', '0', '', ''),
('action_detail', 'banner触发动作详情', '1', '["app_id","path","no_wx_text","no_wx_image"]', ''),
('action_detail', 'banner触发动作详情', '2', '["href","title","in_app","need_token","return"]', '');

-- 添加权限数据
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`)
VALUES
('Banner列表', '/org_web/banner/list', 1582271949, 'get', 1, 'Banner管理', 478, 'banner_list'),
('Banner详情', '/org_web/banner/detail', 1582271949, 'get', 0, '', 478, 'banner_detail'),
('添加Banner', '/org_web/banner/add', 1582272016, 'post', 0, '', 478, 'banner_add'),
('编辑Banner', '/org_web/banner/edit', 1582272088, 'post', 0, '', 478, 'banner_edit');