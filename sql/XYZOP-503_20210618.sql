-- op
INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES
('mini_app_qr', '生成微信小程序码配置', 'current_max_id', '0000G8', '当前生成的最大标识'),
('mini_app_qr', '生成微信小程序码配置', 'create_id_num', '3000', '生成标识数量'),
('mini_app_qr', '生成微信小程序码配置', 'start_generation_threshold_num', '2500', '启用生成小程序码标识队列阀值，使用数量%阀值=0启动'),
('mini_app_qr', '生成微信小程序码配置', 'get_mini_app_qr_second_num', '50', '获取小程序码每秒请求数量'),
('mini_app_qr', '生成微信小程序码配置', 'wait_mini_qr_max_num', '100000', '待使用的小程序码最大数量,超过数量不生成');

ALTER TABLE `bill_map` MODIFY COLUMN `param_map_id` varchar(32) NOT NULL DEFAULT '' COMMENT '小程序码标识、param_map数据表id' AFTER `user_id`;

-- clickhouse -
create database op_prod;
create table qr_info (
     qr_id String Default '' comment '二维码唯一标识',
     qr_path String Default '' comment '二维码图片路径',
     qr_sign String default '' comment '关键字段md5',
     qr_ticket String default '' comment '用户id加密串',
     user_id UInt64 default 0 comment '用户id',
     user_type Int8 default 0 comment '用户身份 1:学生',
     channel_id UInt64 default 0 comment '渠道id',
     landing_type UInt64  default 0 comment 'landing页类型(1:普通Landing页, 2:小程序)',
     activity_id UInt64 default 0 comment '活动id',
     employee_id UInt64 default 0 comment '员工id',
     poster_id UInt64 default 0 comment '海报id',
     app_id UInt64 default 0 comment '应用id',
     busies_type UInt64 default 0 comment '业务线id',
     user_status Int8 default 0 comment '用户生成二维码时的状态',
     qr_data String  default '' comment '所有数据，json串',
     create_time DateTime comment '创建时间',
     INDEX index_qr_id qr_id TYPE minmax GRANULARITY 3,
     INDEX index_qr_ticket qr_ticket TYPE minmax GRANULARITY 3,
     INDEX index_qr_sign qr_sign TYPE minmax GRANULARITY 3,
     INDEX index_user_id user_id TYPE minmax GRANULARITY 3
)ENGINE=ReplicatedMergeTree('/clickhouse/tables/qr_info/{shard}', '{replica}') order by qr_id;