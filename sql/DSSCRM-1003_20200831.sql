CREATE TABLE `leads_pool` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '线索池名称',
  `target_type` tinyint NOT NULL COMMENT '流出目标类型',
  `target_set_id` int NOT NULL COMMENT '流出目标集合id，如果目标是人，这里为对应的dept_id',
  `status` tinyint NOT NULL COMMENT '状态',
  `create_time` int NOT NULL COMMENT '创建时间',
  `operator` int NOT NULL COMMENT '创建人',
  `type` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '池子类型:1总池 2自定义池子',
  PRIMARY KEY (`id`)
) COMMENT='线索池';


CREATE TABLE `leads_pool_rule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT '' COMMENT '规则名称',
  `pool_id` int NOT NULL COMMENT '对应线索池',
  `target_type` tinyint NOT NULL COMMENT '流出目标类型',
  `target_id` int NOT NULL COMMENT '流出目标id',
  `weight` tinyint NOT NULL COMMENT '权重',
  `status` tinyint NOT NULL COMMENT '状态',
  `create_time` int NOT NULL COMMENT '创建时间',
  `operator` int NOT NULL COMMENT '创建人',
  PRIMARY KEY (`id`),
  KEY `idx_pid` (`pool_id`) USING BTREE
) COMMENT='线索池规则';


CREATE TABLE `leads_pool_op_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `op_type` int NOT NULL COMMENT '操作类型',
  `create_time` int NOT NULL COMMENT '操作时间',
  `operator` int NOT NULL COMMENT '操作人',
  `before` int NOT NULL COMMENT '更新前值',
  `after` int NOT NULL COMMENT '更新后值',
  `detail` varchar(256) DEFAULT NULL COMMENT '详情json',
  PRIMARY KEY (`id`)
) COMMENT='线索池日志';


set @pid = (select id
            from privilege
            where menu_name = '社群运营');


INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('线索管理', '/org_web/collection/dept_statistics', UNIX_TIMESTAMP(), 'get', 1, '线索管理', @pid, 'dept_statistics', 1);

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('添加线索池', '/org_web/leads_pool/list', UNIX_TIMESTAMP(), 'get', 0, '', 0, 'leads_pool_list', 1);

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('线索池详情', '/org_web/leads_pool/detail', UNIX_TIMESTAMP(), 'get', 0, '', 0, 'leads_pool_detail', 1);

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('新增线索池', '/org_web/leads_pool/add', UNIX_TIMESTAMP(), 'post', 0, '', 0, 'leads_pool_add', 1);

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('编辑线索池', '/org_web/leads_pool/update', UNIX_TIMESTAMP(), 'post', 0, '', 0, 'leads_pool_update', 1);

INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('编辑线索池状态', '/org_web/leads_pool/update_pool_status', UNIX_TIMESTAMP(), 'post', 0, '', 0, 'leads_pool_update_status',
   1);


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`)
VALUES ('leads_pool_target_type', '线索池分配方式类型', '1', '池到池');
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`)
VALUES ('leads_pool_target_type', '线索池分配方式类型', '2', '池到人');
