INSERT INTO `dict`(`type`, `key_name`, `key_code`, `key_value`, `desc`) VALUES ('share_poster_check_reason', '分享截图审核原因', '6', '朋友圈保留时长不足', NULL);

delete from dict where type = 'share_poster_check_reason' and key_code = 5;