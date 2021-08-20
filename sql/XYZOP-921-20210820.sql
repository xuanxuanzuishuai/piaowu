ALTER TABLE `template_poster`
ADD COLUMN `practise` tinyint(1) UNSIGNED NOT NULL DEFAULT 2 COMMENT '是否需要练琴数据 1是 2否' AFTER `type`;