ALTER TABLE `gift_code`
ADD COLUMN `package_v1` TINYINT(2) NOT NULL DEFAULT '0' COMMENT '是否新产品包';

ALTER TABLE `third_part_bill`
ADD COLUMN `package_v1` TINYINT(2) NULL DEFAULT '0' COMMENT '1 新产品包 0 旧产品包' AFTER `parent_channel_id`;

alter table erp_goods_v1 add column (is_custom tinyint not null default '0' comment '0非自定义产品 1自定义产品');
alter table erp_package_v1 add column (is_custom tinyint not null default '0' comment '0非自定义产品包 1自定义产品包');


insert into privilege (name, uri, method, is_menu, menu_name, parent_id, unique_en_name, created_time)
values ('新产品包列表', '/org_web/package/package_list', 'get', 0, '', 0, 'package_list_v1', 1603773173);
