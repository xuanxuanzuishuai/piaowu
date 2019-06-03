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


INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `parent_id`, `unique_en_name`) VALUES ('查看跟进记录', '/student/student/get_follow_remark', '1557731682', 'get', '0', '0', 'get_follow_remark');
INSERT INTO `privilege` (`name`, `uri`, `created_time`, `method`, `is_menu`, `parent_id`, `unique_en_name`) VALUES ('添加跟进记录', '/student/student/add_follow_remark', '1557731682', 'post', '0', '0', 'add_follow_remark');