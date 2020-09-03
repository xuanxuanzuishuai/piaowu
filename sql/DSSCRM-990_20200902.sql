CREATE TABLE `leads_pool_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pid` varchar(45) NOT NULL DEFAULT '' COMMENT '单次分配流程标识',
  `type` int NOT NULL COMMENT '类型日志',
  `pool_id` int NOT NULL COMMENT '线索池id',
  `pool_type` tinyint NOT NULL,
  `create_time` int NOT NULL COMMENT '创建时间',
  `date` int NOT NULL COMMENT '日期',
  `leads_student_id` int DEFAULT NULL,
  `detail` json DEFAULT NULL COMMENT '详情json',
  PRIMARY KEY (`id`),
  KEY `pool_id_date` (`pool_id`,`date`),
  KEY `leads_student_id` (`leads_student_id`),
  KEY `pid` (`pid`)
) COMMENT='线索池日志';

INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','1001','TYPE_POOL_RULE_CACHE_UPDATE','规则缓存初始化');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','1002','TYPE_COUNTER_CACHE_UPDATE','计数器缓存初始化');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','2001','TYPE_ADDED','pool进入leads');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','2002','TYPE_DISPATCHED','pool分配成功');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','2003','TYPE_STASHED','pool添加成功');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','2004','TYPE_ASSIGN','leads分配到人');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','2005','TYPE_MOVE','leads入池');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','2006','TYPE_PREPARE','规则预处理');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','2101','TYPE_REF_ASSIGN','leads转介绍分配到人');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','9001','TYPE_ERROR_NO_RULES','未配置规则');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','9002','TYPE_NO_COLLECTION','无可分配班级');
INSERT INTO `dict` (`type`,`key_name`,`key_code`,`key_value`,`desc`) VALUES ('leads_pool_event','线索池事件','9003','TYPE_SET_COLLECTION_ERROR','分班失败');