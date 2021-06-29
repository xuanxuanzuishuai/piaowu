-- 1.agent数据表的service_employee_id，organization字段删除

ALTER TABLE `agent`
  DROP COLUMN `service_employee_id`,
  DROP COLUMN `organization`;

-- 2.agent_info数据表的quantity，amount字段删除
ALTER TABLE `agent_info`
  DROP INDEX `idx_quantity`,
  DROP COLUMN `quantity`,
  DROP COLUMN `amount`;