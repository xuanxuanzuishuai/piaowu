INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
	('WE_CHAT_RED_PACK', '微信红包显示语', 'REFERRER_PIC_WORD', '{\"act_name\":\"\\u8f6c\\u4ecb\\u7ecd\\u5956\\u52b1\",\"send_name\":\"\\u5956\\u52b1\",\"wishing\":\"\\u53c2\\u4e0e\\u591a\\u591a\\uff0c\\u5956\\u52b1\\u591a\\u591a\"}', NULL);

set @parent_id = (select id from privilege where menu_name = '转介绍管理');

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES 
('二期-红包发放审核', '/org_web/referee/award_list', 1602728910, 'get', 1, '二期-红包发放审核', @parent_id, 'referee_award_list', 1 ),
('二期-红包发放', '/org_web/referee/update_award', 1602728910, 'post', 0, '二期-红包发放', 0, 'referee_update_award', 1 );
