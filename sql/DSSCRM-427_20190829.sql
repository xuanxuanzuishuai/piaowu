ALTER TABLE `feedback`
  ADD COLUMN `content_type` TINYINT NULL COMMENT '内容类型 null 纯文字 1 曲谱错误(json)' AFTER `content`;
