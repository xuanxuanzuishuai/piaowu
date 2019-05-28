-- about bill_extend
create table bill_extend (
  id              int                   auto_increment,
  bill_id         int          not null
  comment '订单ID',
  credentials_url varchar(256) not null
  comment '付款凭条图片链接',
  status          tinyint      not null default '1'
  comment '0废除 1正常',
  create_time     int(10),
  primary key (id)
);
create index index_bill_id
  on bill_extend (bill_id);