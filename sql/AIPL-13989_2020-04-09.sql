INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('SERVICE_SIGN_KEY', '各个服务调取op接口签名秘钥', 'erp_service_key', 'AAAEbm9uZQAAAAAAAAABAAABFwAAAAdzc2gtcn', NULL);

CREATE TABLE `user_exchange_points_order` (
      `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
      `uuid` varchar(32) NOT NULL DEFAULT '' COMMENT '用户uuid',
      `user_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT 'dss.student表用户id',
      `order_id` bigint(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单id',
      `order_type` varchar(20) NOT NULL DEFAULT '' COMMENT '订单类型 red_pack:兑换红包',
      `order_from` varchar(20) NOT NULL DEFAULT '' COMMENT '订单来源 erp:erp服务',
      `order_amounts` int(10) NOT NULL DEFAULT '0' COMMENT '订单金额 单位:分',
      `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
      PRIMARY KEY (`id`),
      UNIQUE KEY `order_id` (`order_type`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户积分兑换订单';