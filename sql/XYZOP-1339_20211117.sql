INSERT INTO `dict` (
	`type`,
	`key_name`,
	`key_code`,
	`key_value`,
	`desc`
)
VALUES
	(
		'WEB_STUDENT_CONFIG',
		'AI练琴WEB设置',
		'package_id_4_9',
		'34954',
	'4.9体验营产品包'
	);


	INSERT INTO `poster` ( `id`, `name`, `path`, `status`, `create_time` )
VALUES
	(1700, '', 'pre/img//activity/ba8b50fb0045584a5a685ddd74c94e00.png', 1, 1613980934 ),
	(1701, '', 'pre/img//activity/b9a8dfdea9bbcc137e4ae7a169cb063e.png', 1, 1613980934 ),
	(1702, '', 'pre/img//activity/be51bffc697e06c1adc4eae502b59e98.png', 1, 1613980934 ),
	(1703, '', 'pre/img//activity/833dde75d00db6a53946f47f35402acb.png', 1, 1613980934 ),
	(1704, '', 'pre/img//activity/9412928cec0ca08a253f1ff6a7f1248b.png', 1, 1613980934 ),
	(1705, '', 'pre/img//activity/649063e1bb0e2e045c589d6cd2fe9c3a.png', 1, 1613980934 );


	UPDATE `dict`
SET `key_value` = '{\"1\":1700,\"2\":1701,\"3\":1702,\"4\":1703,\"5\":1704,\"0\":1705}',
`desc` = '每一天对应海报ID'
WHERE
	`type` = 'CHECKIN_PUSH_CONFIG'
	AND `key_name` = '打卡签到设置'
	AND `key_code` = 'day_poster_config';

	UPDATE `dict`
SET
`key_value` = '{\"content1\":\"\\\\ud83d\\\\udd14琴童宝贝们注意啦~\\n\\n⏰小叶子智能陪练5天强化训练营，今天就要开始啦~\\n从现在起，小叶子智能陪练将陪伴宝贝度过快乐练琴的每一天~\\n\\n\\\\ud83d\\\\udd25恭喜你获得“分享赚19.8练琴红包”活动资格\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\\\ud83d\\\\udc47\\\\ud83c\\\\udffb\\n\\n训练营5天内，每天练琴，每天打卡，最高可得19.8练琴红包！[红包]\\n[爱心]活动详情【<a href=\\\"{page_url}\\\">戳我进入活动</a>】\\n[爱心]攻略解说【<a href=\\\"https://t.1yb.co/aRyg\\\">戳我了解规则</a>】\",\"content2\":\"\"}',
`desc` = '第0天推送配置'
WHERE
	`type` = 'CHECKIN_PUSH_CONFIG' and
`key_name` = '打卡签到设置' and
`key_code` = 'day_0';

	UPDATE `dict`
SET
`key_value` = '{\"content1\":\"\\\\ud83c\\\\udf89恭喜宝贝已完成训练营第一天练琴，好棒哦～小叶子希望每天都能陪你[愉快]\\n\\n\\\\ud83d\\\\udd25“朋友圈返19.8练琴红包”活动已开始\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【分享语➕海报】分享到朋友圈，即可领取打卡红包[红包]\\n[爱心]活动详情【<a href=\\\"{page_url}\\\">戳我进入活动</a>】\\n[爱心]攻略解说【<a href=\\\"https://t.1yb.co/aRyg\\\">戳我了解规则</a>】\",\"content2\": \"为了鼓励宝贝坚持练琴，刚刚报名了“小叶子智能陪练”只要4.9元！\\\\ud83c\\\\udf89\\\\ud83c\\\\udf89\\n5天不限次练琴，有小叶子陪着练琴，宝贝第一天就爱上了~\"}',
`desc` = '第1天推送配置'
WHERE
`type` = 'CHECKIN_PUSH_CONFIG' and
`key_name` = '打卡签到设置' and
`key_code` = 'day_1';

UPDATE `dict`
SET
`key_value` = '{\"content1\":\"\\\\ud83d\\\\udcaa已经坚持练琴2天啦，音准和节奏一定进步了不少，好棒哦！\\n\\n\\\\ud83d\\\\udd25分享返学费活动第2⃣️天！宝贝已完成昨日练琴，海报已解锁，分享朋友圈帮孩子赚19.8练琴红包[红包]\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【分享语➕海报】分享至朋友圈\\n[爱心]活动详情【<a href=\\\"{page_url}\\\">戳我进入活动</a>】\\n[爱心]攻略解说【<a href=\\\"https://t.1yb.co/aRyg\\\">戳我了解规则</a>】\",\"content2\":  \"宝贝能爱上钢琴，真是一件非常幸运的事情[哇]～ 宝贝今天继续用“小叶子智能陪练”练琴，智能纠错，及时反馈，宝贝的错音越来越少！[加油！][加油！]\"}',
`desc` = '第2天推送配置'
WHERE
`type` = 'CHECKIN_PUSH_CONFIG' and
`key_name` = '打卡签到设置' and
`key_code` = 'day_2';

UPDATE `dict`
SET
`key_value` = '{\"content1\":\"\\\\ud83d\\\\udcaa宝贝真棒！你已经坚持练琴第3天啦[哇] \\n 练琴成绩开始提升了，小叶子希望宝贝坚持下去，成为“小小贝多芬”指日可期！\\\\ud83c\\\\udf89\\\\ud83c\\\\udf89 \\n\\n\\\\ud83d\\\\udd25分享返学费活动第3⃣️天！宝贝已完成昨日练琴，海报已解锁，分享朋友圈帮孩子赚19.8练琴红包[红包]\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【分享语➕海报】分享至朋友圈\\n\\n[爱心]活动详情【<a href=\\\"{page_url}\\\">戳我进入活动</a>】\\n[爱心]攻略解说【<a href=\\\"https://t.1yb.co/aRyg\\\">戳我了解规则</a>】\",\"content2\": \"老师说孩子的音准和节奏都比以前有了明显进步[哇]每天都收到宝贝的练琴日报！    \\n孩子哪里弹错，练琴成绩如何都能在里面看到，真是太好了！\\\\ud83c\\\\udf89[加油！]\"}',
`desc` = '第3天推送配置'
WHERE
	`type` = 'CHECKIN_PUSH_CONFIG' and
`key_name` = '打卡签到设置' and
`key_code` = 'day_3';

UPDATE `dict`
SET
`key_value` = '{\"content1\":\"\\\\ud83d\\\\udcaa坚持就是胜利，宝贝好棒！第4天就已经可以完整的弹奏一首曲目啦！\\n\\n\\\\ud83d\\\\udd25分享返学费活动第4⃣️天！宝贝已完成昨日练琴，海报已解锁，分享朋友圈帮孩子赚19.8练琴红包[红包]\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【分享语➕海报】分享至朋友圈\\n\\n[爱心]活动详情【<a href=\\\"{page_url}\\\">戳我进入活动</a>】\\n[爱心]攻略解说【<a href=\\\"https://t.1yb.co/aRyg\\\">戳我了解规则</a>】\",\"content2\":\"孩子通过“小叶子智能陪练”，练了几个小时就能完整的弹奏一首曲子了\\\\ud83c\\\\udfb9  \\n效果这么棒真的让我意想不到，希望宝贝可以坚持下去！✊\\\\ud83c\\\\udf89\"}',
`desc` = '第4天推送配置'
WHERE
	`type` = 'CHECKIN_PUSH_CONFIG' and
`key_name` = '打卡签到设置' and
`key_code` = 'day_4';

UPDATE `dict`
SET
`key_value` = '{\"content1\":\"[爱心]这是宝贝和小叶子在一起的第5天，宝贝已经超过了全网93％的琴童哦，成为了“小叶子明日之星”\\n\\\\ud83c\\\\udf38未来的练琴路上小叶子希望跟孩子一起每天练琴，快乐成长！[加油]\\n\\n\\\\ud83d\\\\udd25分享返学费活动第5⃣️天！宝贝已完成昨日练琴，海报已解锁，分享朋友圈帮孩子赚19.8练琴红包[红包]\\n\\n\\\\ud83c\\\\udfaf打卡方式：复制以下【分享语➕海报】分享至朋友圈\\n\\n[爱心]活动详情【<a href=\\\"{page_url}\\\">戳我进入活动</a>】\\n[爱心]攻略解说【<a href=\\\"https://t.1yb.co/aRyg\\\">戳我了解规则</a>】\",\"content2\": \"今天宝贝被授予了“小叶子明日之星”，练琴成绩已超全网93%的小琴童啦[哇][加油！]\\n音准和节奏也已经变得越来越好了，真为宝贝感到骄傲！\\\\ud83c\\\\udf89\\\\ud83c\\\\udf89\"}',
`desc` = '第5天推送配置'
WHERE
	`type` = 'CHECKIN_PUSH_CONFIG' and
`key_name` = '打卡签到设置' and
`key_code` = 'day_5';

	-- del dict_list_CHECKIN_PUSH_CONFIG