CREATE TABLE `follow_remark`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `operator_id` int(11) NOT NULL,
  `remark` text NOT NULL,
  `create_time` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `user_id`(`user_id`) USING BTREE
);