ALTER TABLE `agent_award_bill_ext`
ADD COLUMN `package_type` tinyint(1) NOT NULL COMMENT '产品包类型 1 体验 2年卡' AFTER `parent_bill_id`;