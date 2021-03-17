-- !!!erp项目sql开始!!!
-- 增加售卖渠道类型
INSERT INTO `erp_dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('package_v1_channel', '新产品包渠道', '32', '运营系统代理', NULL);
-- !!!erp项目sql结束!!!



-- !!!op项目开始!!!
-- 新建表
CREATE TABLE `agent_sale_package` (
  `id`          int(10) unsigned    NOT NULL AUTO_INCREMENT
  COMMENT '主键',
  `package_id`  int(10) unsigned    NOT NULL DEFAULT '0'
  COMMENT '课包ID',
  `agent_id`    int(10) unsigned    NOT NULL DEFAULT '0'
  COMMENT '代理商ID',
  `status`      int(10) unsigned    NOT NULL DEFAULT '1'
  COMMENT '状态1正常2删除',
  `app_id`      tinyint(3) unsigned NOT NULL DEFAULT '8'
  COMMENT '代理业务线ID:1真人 8智能',
  `create_time` int(10) unsigned    NOT NULL DEFAULT '0'
  COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_agent_package` (`agent_id`, `package_id`) USING BTREE COMMENT '代理ID和课包ID组合索引'
)
  COMMENT ='代理商售卖商品包列表';


CREATE TABLE `agent_award_bill_ext` (
  `id`                    int(10) unsigned    NOT NULL AUTO_INCREMENT
  COMMENT '主键',
  `student_id`            int(10) unsigned    NOT NULL
  COMMENT '学生ID',
  `parent_bill_id`        varchar(32)         NOT NULL
  COMMENT '主订单ID',
  `student_referral_id`   int(10) unsigned    NOT NULL DEFAULT '0'
  COMMENT '学生转介绍人ID',
  `own_agent_id`          int(10) unsigned    NOT NULL DEFAULT '0'
  COMMENT '订单归属代理商ID',
  `own_agent_status`      tinyint(1) unsigned NOT NULL DEFAULT '1'
  COMMENT '订单归属代理商冻结状态1正常2冻结',
  `signer_agent_id`       int(10) unsigned    NOT NULL DEFAULT '0'
  COMMENT '订单成单代理商ID',
  `signer_agent_status`   tinyint(1) unsigned NOT NULL DEFAULT '1'
  COMMENT '订单成单代理商冻结状态1正常2冻结',
  `is_hit_order`          tinyint(1) unsigned NOT NULL DEFAULT '2'
  COMMENT '是否撞单1是2不是',
  `is_first_normal_order` tinyint(1) unsigned NOT NULL DEFAULT '2'
  COMMENT '是否是绑定关系建立后年卡首单 1是2不是',
  `is_agent_channel_buy`  tinyint(1) unsigned NOT NULL DEFAULT '2'
  COMMENT '是否是代理渠道购买1是2不是',
  `create_time`           int(11) unsigned    NOT NULL DEFAULT '0'
  COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_bill_id` (`parent_bill_id`) USING BTREE COMMENT '主订单索引',
  KEY `idx_signer` (`signer_agent_id`) USING BTREE COMMENT '成单人ID索引',
  KEY `idx_student_id` (`student_id`) USING BTREE COMMENT '学生ID索引'
)
  COMMENT ='代理奖励订单扩展表';

-- 增加字段 修改表字段注释
ALTER TABLE `agent`
  ADD COLUMN `division_model` tinyint(4) UNSIGNED NOT NULL DEFAULT 0
COMMENT '分成模式:0代表代理账户是二级代理，此数据读取父类 1线索获量 2线索获量+售卖模式'
  AFTER `update_time`;

ALTER TABLE `agent`
  MODIFY COLUMN `type` tinyint(1) UNSIGNED NOT NULL DEFAULT 1
  COMMENT '账户代理模式类型:1分销平台 2个人家长代理 3线下代理 4个人老师代理';


ALTER TABLE `agent_award_detail`
  MODIFY COLUMN `is_bind` tinyint(1) UNSIGNED NOT NULL DEFAULT 1
  COMMENT '是否绑定期中订单:0否 1是 2无代理商绑定关系';

ALTER TABLE `agent_operation_log`
  MODIFY COLUMN `type` tinyint(1) UNSIGNED NOT NULL
  COMMENT '操作类型:1冻结代理 2解冻代理 3上级代理冻结下级代理 4上级代理解冻下级代理 5代理详情数据修改';

-- 设置旧版数据中一级代理商的分成模式为1
update agent
set division_model = 1
where parent_id = 0;

-- 增加旧奖励数据的扩展信息
update agent_award_detail
set ext = json_set(ext, "$.division_model", "1", "$.agent_type", "1")
where action_type != 3;

-- 修改代理商分成模式名称，并新增一个新的分成模式类型
update dict
set key_value = '个人家长代理'
where type = 'agent_type' and key_code = '2';

-- 修改创建人id=13的代理商代理模式为个人教师代理
update agent
set type = 4
where employee_id = 13;

-- 已绑定并且购买年卡的学员的永久绑定期改为：上线后的90天有效期，其他的绑定有效期不变
update agent_user
set bind_time = UNIX_TIMESTAMP("2021-04-09"), deadline = UNIX_TIMESTAMP("2021-04-09") + 7776000
where stage = 2;

-- 补全数据：由于一级代理商历史数据不存在可售卖课包数据，所以执行sql把缺失的数据补全
INSERT INTO agent_sale_package
(`package_id`, `agent_id`, `create_time`) SELECT
                                            10445,
                                            id,
                                            UNIX_TIMESTAMP()
                                          FROM
                                            agent
                                          WHERE
                                            parent_id = 0;

-- 权限sql
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES ('推广商品', '/op_web/package/list', 1615544350, 'get', 1, '推广商品', 677, 'recommend_goods', 1);
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `menu_name`, `parent_id`, `unique_en_name`, `status`)
VALUES
  ('获取代理分成模式可推广的商品列表', '/op_web/agent/division_to_package', 1612496386, 'get', 0, '', 0, 'division_model_package', 1);

-- 增加dict配置
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('agent_division_model', '代理分成模式', '1', '线索获量', NULL);
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('agent_division_model', '代理分成模式', '2', '线索获量+售卖模式', NULL);
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('agent_type', '代理模式类型', '4', '个人老师代理', NULL);
INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES ('agent_bind', '代理绑定有效时间', '2', '7776000', '年卡阶段有效期为90天');
-- !!!op项目结束!!!


