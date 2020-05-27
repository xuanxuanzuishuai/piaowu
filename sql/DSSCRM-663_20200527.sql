CREATE TABLE `faq` (
  `id`          int(11)     NOT NULL AUTO_INCREMENT
  COMMENT '自增id',
  `title`       varchar(64) NOT NULL
  COMMENT '标题',
  `desc`        text COMMENT '描述',
  `type`        tinyint(2)  NOT NULL DEFAULT '1'
  COMMENT '问题类型',
  `status`      tinyint(2)  NOT NULL DEFAULT '1'
  COMMENT '问题状态 0 不可用 1 正常',
  `create_time` int(10)     NOT NULL DEFAULT '0'
  COMMENT '创建时间戳',
  `creator_id`  int(11)     NOT NULL
  COMMENT '创建人id',
  `update_time` int(10)     NOT NULL DEFAULT '0'
  COMMENT '最后更新时间戳',
  `updator_id`  int(11)     NOT NULL
  COMMENT '最后更新人id',
  PRIMARY KEY (`id`)
)
  COMMENT '话术表';

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('话术详情', '/org_web/faq/detail', 1590548820, 'get', 0, '话术详情', 0, 'faq_detail', 1);
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('话术修改', '/org_web/faq/modify', 1590548820, 'post', 0, '话术修改', 0, 'faq_modify', 1);
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('话术添加', '/org_web/faq/add', 1590548820, 'post', 0, '话术添加', 0, 'faq_add', 1);
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('话术搜索', '/org_web/faq/search', 1590548820, 'get', 1, '话术搜索', 514, 'faq_search', 1);
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('话术管理', '/org_web/faq/list', 1590548820, 'get', 1, '话术管理', 514, 'faq_list', 1);


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('faq_status', '话术状态', '0', '禁用', NULL);
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('faq_status', '话术状态', '1', '启用', NULL);