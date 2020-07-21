create table third_part_bill (
  id          int(11) unsigned          auto_increment,
  student_id  int(11) unsigned not null,
  mobile      varchar(16)      not null,
  trade_no    varchar(256)     not null,
  is_new      tinyint          not null
  comment '1新注册用户 2老用户',
  package_id  int(11) unsigned not null
  comment '购买课包ID',
  parent_channel_id int(11) unsigned not null
  comment '一级渠道ID',
  channel_id  int(11) unsigned not null
  comment '二级渠道ID',
  operator_id int(11) unsigned not null
  comment '操作人ID',
  status      tinyint          not null
  comment '1成功 2失败',
  reason      varchar(256)     not null default ''
  comment '请求失败原因',
  pay_time    int(11)          not null default '0',
  create_time int(11)          not null,
  primary key (id)
);


insert into dict (type, key_name, key_code, key_value) values ('third_part_bill_status', '导入状态', '1', '成功'),
                                                              ('third_part_bill_status', '导入状态', '2', '失败');

insert into privilege (name, uri, created_time, method, is_menu, menu_name, parent_id, unique_en_name) VALUES
('导入第三方订单', '/bill/third_part_bill/import', unix_timestamp(now()), 'post', 0, '导入第三方订单', 464, 'third_part_bill_import'),
('第三方订单列表', '/bill/third_part_bill/list', unix_timestamp(now()), 'get', 1, '', 464, 'third_part_bill_list'),
('下载导入订单模板', '/bill/third_part_bill/download_template', unix_timestamp(now()), 'get', 0, '', 464, 'third_part_bill_download_template');