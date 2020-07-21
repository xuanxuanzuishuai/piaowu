ALTER TABLE `collection`
ADD COLUMN `collection_url` VARCHAR(200) NULL COMMENT '班级url' AFTER `task_id`;
