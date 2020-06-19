ALTER TABLE ai_play_record ADD score_rank FLOAT DEFAULT 0  COMMENT '击败用户' ;

INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc` )
VALUES
('APP_CONFIG_STUDENT', 'AI练琴后端设置', 'play_share_assess_url', 'https://referral-pre.xiaoyezi.com/market/index', '测评结果分享' );
