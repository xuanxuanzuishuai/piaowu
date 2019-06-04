ALTER TABLE `student_org`
ADD COLUMN `is_first_pay` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0代表未付费1代表已经首次付费' AFTER `create_time`,
ADD COLUMN `first_pay_time` int(11) NULL AFTER `is_first_pay`;

insert into dict(`type`, `key_name`, `key_code`, `key_value`, `desc`) values ('first_pay_status', '首次付费状态', '0', '未付费', '未首次付费');
insert into dict(`type`, `key_name`, `key_code`, `key_value`, `desc`) values ('first_pay_status', '首次付费状态', '1', '已付费', '已首次付费');