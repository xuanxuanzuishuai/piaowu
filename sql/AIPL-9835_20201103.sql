CREATE TABLE `student_favorite` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `student_id` int(11) NOT NULL COMMENT '学员ID',
  `type` tinyint(4) NOT NULL COMMENT '收藏类型 1：曲谱  2：教材',
  `object_id` int(11) NOT NULL COMMENT '曲谱或教材ID',
  `status` tinyint(4) NOT NULL COMMENT '1：收藏 2：取消收藏',
  `update_time` int(10) NOT NULL COMMENT '更新时间',
  `create_time` int(10) NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) COMMENT='曲谱/教材收藏表';