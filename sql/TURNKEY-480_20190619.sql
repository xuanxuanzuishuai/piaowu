alter table bill
  add column (add_status tinyint not null default 2
comment '添加订单审核状态 1待审核 2审核通过 3通过 4拒绝', disabled_status tinyint
comment '废除订单审核状态 1待审核 2审核通过 3通过 4拒绝');

insert into dict (type, key_name, key_code, key_value)
values ('bill_add_status', '添加订单状态', 1, '审核中'),
       ('bill_add_status', '添加订单状态', 2, '审核通过'),
       ('bill_add_status', '添加订单状态', 3, '拒绝'),
       ('bill_add_status', '添加订单状态', 4, '撤销'),
       ('bill_disabled_status', '废除订单状态', 1, '审核中'),
       ('bill_disabled_status', '废除订单状态', 2, '审核通过'),
       ('bill_disabled_status', '废除订单状态', 3, '拒绝'),
       ('bill_disabled_status', '废除订单状态', 4, '撤销');