ALTER TABLE `bill`
ADD COLUMN `object_id` INT(11) NOT NULL DEFAULT '0' COMMENT 'course_id' AFTER `r_bill_id`;
