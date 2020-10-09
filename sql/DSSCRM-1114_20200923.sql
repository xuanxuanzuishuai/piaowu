
CREATE TABLE `message_push_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `name` char(12) NOT NULL DEFAULT '' COMMENT '规则名称',
  `type` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '推送形式:1客服消息;2模板消息;',
  `target` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '推送人群:1:全部用户;2:当日开班用户;3:开班第7天用户;4:年卡C级用户;5:体验C级用户;6:注册C级用户;',
  `is_active` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否启用:0:未启用;1:已启用;',
  `time` json DEFAULT NULL COMMENT '推送时间',
  `content` json DEFAULT NULL COMMENT '文案内容',
  `remark` varchar(200) DEFAULT '' COMMENT '备注',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  `update_time` int(10) NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT 'DSS消息推送规则表';

INSERT INTO `message_push_rules` (`id`, `name`, `type`, `target`, `is_active`, `time`, `content`, `remark`, `create_time`, `update_time`)
VALUES
  (1,'首关欢迎语推送',1,1,1,'{\"desc\": \"当用户关注时\", \"delay_time\": 0}','[{"key": "content_1", "type": 1, "value": ""}, {"key": "content_2", "type": 1, "value": ""}, {"key": "image", "type": 2, "value": ""}]','',1600848505,1600848505),
  (2,'红包领取成功后推送',1,1,0,'{\"desc\": \"用户领取红包成功时\", \"delay_time\": 0}','[]','',1600848505,1600848505),
  (3,'开班日当天消息推送',1,2,0,'{\"desc\": \"开班日当天18点\", \"delay_time\": 0}','[]','',1600848505,1600848505),
  (4,'开班第7天消息推送',1,3,0,'{\"desc\": \"开班第7天18点\", \"delay_time\": 0}','[]','',1600848505,1600848505),
  (5,'年卡C交互后消息推送',1,4,0,'{\"desc\": \"与公众号交互10分钟后\", \"delay_time\": 600}','[]','',1600848505,1600848505),
  (6,'体验C交互后消息推送',1,5,0,'{\"desc\": \"与公众号交互10分钟后\", \"delay_time\": 600}','[]','',1600848505,1600848505),
  (7,'注册C交互后消息推送',1,6,0,'{\"desc\": \"与公众号交互10分钟后\", \"delay_time\": 600}','[]','',1600848505,1600848505);

CREATE TABLE `message_manual_push_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '推送形式:1客服消息;2模板消息;',
  `file` varchar(256) NOT NULL DEFAULT '' COMMENT '用户EXCEL地址',
  `data` json DEFAULT NULL COMMENT '发送数据JSON',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT '手动推送历史记录';

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`) VALUES
        ('message_push_type', '消息推送形式', '1', '客服消息'),
        ('message_push_type', '消息推送形式', '2', '模板消息'),
        ('message_rule_active_status', '消息推送规则启动状态', '1', '已启用'),
        ('message_rule_active_status', '消息推送规则启动状态', '0', '未启用');

set @parent_id = (select id from privilege where menu_name = '转介绍管理');

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES 
('DSS消息推送规则列表', '/org_web/message/rules_list', 1600912629, 'get', 1, '自动推送设置', @parent_id, 'message_rules_list', 1 );

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ('DSS消息推送规则详情', '/org_web/message/rule_detail', 1600912629, 'get', 0, '', 0, 'message_rule_detail', 1 );

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ('DSS消息推送规则更新状态', '/org_web/message/rule_update_status', 1600912629, 'post', 0, '', 0, 'message_rule_update_status', 1 );
  
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ('DSS消息推送规则更新内容', '/org_web/message/rule_update', 1600912629, 'post', 0, '', 0, 'message_rule_update', 1 );
  
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ('DSS消息手动上次推送内容', '/org_web/message/manual_last_push', 1600912629, 'get', 0, '', 0, 'message_manual_last_push', 1 );
  
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status` )
VALUES
  ('DSS消息手动推送', '/org_web/message/manual_push', 1600912629, 'post', 0, '', 0, 'message_manual_push', 1 );
  
