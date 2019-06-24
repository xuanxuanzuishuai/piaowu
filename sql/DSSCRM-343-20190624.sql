ALTER TABLE `student_account_log`
CHANGE COLUMN `type` `type` INT(1) NOT NULL COMMENT '账户变化类型 1 入账 2 消费 3 作废',
ADD COLUMN `bill_id` INT(11) NULL DEFAULT '0' COMMENT '添加、作废订单时，订单id' AFTER `schedule_id`;

INSERT INTO `privilege` (`name`, `uri`, `method`, `unique_en_name`, `parent_id`, `is_menu`, `menu_name`, `created_time`)
VALUES
('已审核订单导出', '/bill/bill/exportBill', 'get', 'exportBill', '57', '1', '已审核订单导出', 1561367395),
('课消数据导出', '/bill/bill/exportReduce', 'get', 'exportReduce', '57', '1', '课消数据导出', 1561367570)