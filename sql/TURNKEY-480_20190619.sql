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
       ('bill_disabled_status', '废除订单状态', 4, '撤销'),
       ('approval_op_type', '审批操作类型', '1', '通过'),
       ('approval_op_type', '审批操作类型', '2', '拒绝'),
       ('approval_type', '审批类型', 1, '添加订单'),
       ('approval_type', '审批类型', 2, '废除订单'),
       ('approval_status', '审批状态', 1, '待审批'),
       ('approval_status', '审批状态', 2, '通过'),
       ('approval_status', '审批状态', 3, '拒绝'),
       ('approval_status', '审批状态', 4, '撤销'),
       ('normal_status', '通用状态', 0, '废弃'),
       ('normal_status', '通用状态', 1, '正常');
