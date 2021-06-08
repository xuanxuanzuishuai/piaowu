ALTER TABLE `agent_info`
ADD COLUMN `quantity` int(11) NOT NULL DEFAULT 0 COMMENT '剩余年卡数量' AFTER `name`,
ADD COLUMN `amount` int(11) NOT NULL DEFAULT 0 COMMENT '客退金额 单位分' AFTER `quantity`;

ALTER TABLE `agent`
ADD COLUMN `organization` varchar(100) NOT NULL DEFAULT '' COMMENT '机构名称' AFTER `division_model`;

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
  ('agent_poster_config', '代理海报特殊配置', 'ORGANIZATION_WORD_X', '50', '机构文字x轴偏移量'),
  ('agent_poster_config', '代理海报特殊配置', 'ORGANIZATION_WORD_Y', '1080', '机构文字y轴偏移量'),
  ('agent_poster_config', '代理海报特殊配置', 'ORGANIZATION_WORD_COLOR', 'FFFFFF', '机构文字字体颜色'),
  ('agent_poster_config', '代理海报特殊配置', 'ORGANIZATION_WORD_SIZE', '35', '机构文字字体大小'),
  ('agent_poster_config', '代理海报特殊配置', 'RECOMMEND_WORD_X', '50', '推荐文字x轴偏移量'),
  ('agent_poster_config', '代理海报特殊配置', 'RECOMMEND_WORD_Y', '1130', '推荐文字y轴偏移量'),
  ('agent_poster_config', '代理海报特殊配置', 'RECOMMEND_WORD_COLOR', 'FFFF00', '推荐文字字体颜色'),
  ('agent_poster_config', '代理海报特殊配置', 'RECOMMEND_WORD_SIZE', '30', '推荐文字字体大小');

-- 权限设置
set @parentMenuId = (select id from privilege where unique_en_name = 'agent_manage');
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商退款申请', '/op_web/agent_storage/refund_add', unix_timestamp(), 'post', 0, '', 0, 'agent_refund_add', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商退款编辑', '/op_web/agent_storage/refund_update', unix_timestamp(), 'post', 0, '', 0, 'agent_refund_update', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商退款详情', '/op_web/agent_storage/refund_detail', unix_timestamp(), 'get', 0, '', 0, 'agent_refund_detail', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商退款列表', '/op_web/agent_storage/refund_list', unix_timestamp(), 'get', 1, '退款列表', @parentMenuId, 'agent_refund_list', 1);
INSERT INTO `privilege`( `name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('代理商退款审核', '/op_web/agent_storage/refund_verify', unix_timestamp(), 'post', 0, '', 0, 'agent_refund_verify', 1);
