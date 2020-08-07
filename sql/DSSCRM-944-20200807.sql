ALTER TABLE `employee`
ADD COLUMN `wx_nick` VARCHAR(64) NULL COMMENT '微信昵称' AFTER `last_update_pwd_time`,
ADD COLUMN `wx_thumb` VARCHAR(256) NULL COMMENT '微信头像' AFTER `wx_nick`,
ADD COLUMN `wx_qr` VARCHAR(256) NULL COMMENT '微信二维码' AFTER `wx_thumb`;