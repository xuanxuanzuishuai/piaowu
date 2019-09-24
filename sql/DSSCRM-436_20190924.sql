ALTER TABLE `org_license`
  ADD COLUMN `type` TINYINT NOT NULL DEFAULT 1 COMMENT '许可类型 1智能琴房 2钢琴教室' AFTER `org_id`;