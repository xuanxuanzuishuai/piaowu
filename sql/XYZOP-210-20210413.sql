--   QUEUE
-- Topic=pre_operation_wechat
-- Channel=op
-- Mode=0
-- Method=POST
-- Callback=https://op-pre.xiaoyezi.com/api/consumer/wechat

--PRE
-- const USER_TYPE_1_1 = '1_1';// 未绑定
-- const USER_TYPE_1_2 = '1_2';// 解除绑定
-- const USER_TYPE_2_1 = '2_1';// 绑定中-仅注册-7天内登录过app
-- const USER_TYPE_3_1 = '3_1';// 绑定中-仅注册-7天内未登录过app
-- const USER_TYPE_4_1 = '4_1';// 绑定中-有加入班级-开班前-购买年卡（付费正式课）
-- const USER_TYPE_4_2 = '4_2';// 绑定中-有加入班级-开班中-购买年卡（付费正式课）
-- const USER_TYPE_4_3 = '4_3';// 绑定中-有加入班级-结班后-14天内-购买年卡（付费正式课）
-- const USER_TYPE_5_1 = '5_1';// 绑定中-有加入班级-开班前-未购买年卡
-- const USER_TYPE_5_2 = '5_2';// 绑定中-有加入班级-开班中-未购买年卡
-- const USER_TYPE_5_3 = '5_3';// 绑定中-有加入班级-结班后-14天内-未购买年卡
-- const USER_TYPE_6_1 = '6_1';// 绑定中-有加入班级-结班后-14天外-购买年卡（付费正式课）-当前阶段为付费正式课有效期未过期-大于30天
-- const USER_TYPE_6_2 = '6_2';// 绑定中-未加入班级-年卡用户-当前阶段为付费正式课有效期未过期-大于30天
-- const USER_TYPE_7_1 = '7_1';// 绑定中-有加入班级-结班后-14天外-购买年卡（付费正式课）-当前阶段为付费正式课有效期未过期-小于等于30天
-- const USER_TYPE_7_2 = '7_2';// 绑定中-未加入班级-年卡用户-当前阶段为付费正式课有效期未过期-小于等于30天
-- const USER_TYPE_8_1 = '8_1';// 绑定中-有加入班级-结班后-14天外-购买年卡（付费正式课）-当前阶段为付费正式课有效期已过期
-- const USER_TYPE_8_2 = '8_2';// 绑定中-未加入班级-年卡用户-当前阶段为付费正式课有效期已过期
-- const USER_TYPE_9_1 = '9_1';// 绑定中-有加入班级-结班后-14天外-未购买年卡


INSERT INTO `dict` (`type`, `key_name`, `key_code`, `key_value`, `desc`)
VALUES
-- tag
/*
PRE:
{
  "1_1": "106",
  "1_2": "106", //"108",
  "2_1": "109",
  "3_1": "109", //"110",
  "4_1": "113",
  "4_2": "113", //"115",
  "4_3": "113", //"117",
  "5_1": "118",
  "5_2": "118", //"119",
  "5_3": "118", //"120",
  "6_1": "115", //"121",
  "6_2": "115", //"122",
  "7_1": "115", //"123",
  "7_2": "115", //"124",
  "8_1": "125",
  "8_2": "125", //"126",
  "9_1": "127"
}

106: "个性化菜单_1_1",
108: "个性化菜单_1_2",
109: "个性化菜单_2_1",
110: "个性化菜单_3_1",
113: "个性化菜单_4_1",
115: "个性化菜单_4_2",
117: "个性化菜单_4_3",
118: "个性化菜单_5_1",
119: "个性化菜单_5_2",
120: "个性化菜单_5_3",
121: "个性化菜单_6_1",
122: "个性化菜单_6_2",
123: "个性化菜单_7_1",
124: "个性化菜单_7_2",
125: "个性化菜单_8_1",
126: "个性化菜单_8_2",
127: "个性化菜单_9_1",
*/
  ('WECHAT_CONFIG', '微信配置', 'user_type_tag_dict', '{"1_1":"106","1_2":"106","2_1":"109","3_1":"109","4_1":"113","4_2":"113","4_3":"113","5_1":"118","5_2":"118","5_3":"118","6_1":"115","6_2":"115","7_1":"115","7_2":"115","8_1":"125","8_2":"125","9_1":"127"}', '用户类型对应标签配置'),
  ('WECHAT_CONFIG', '微信配置', 'all_menu_tag', '["106","109","113","115","118","125","127"]', '全部菜单对应标签'),


-- menu_redirect

-- {
--   "https://dss-weixin.xiongmaopeilian.com/student/myAccount": "6a6a943dc8ca4ff68d43778843c26991",
--   "https://dss-weixin.xiongmaopeilian.com/student/poster": "3b830f35bce06ae8490a9ec36d57131f",
--   "https://dss-weixin.xiongmaopeilian.com/student/returnMoney?tag=1": "192813729560b20abbaf75654227c5a4",
--   "https://dss-weixin.xiongmaopeilian.com/student/referral?tag=1": "f9a67938627f2516ce32afc1f306f8c2",
--   "https://dss-weixin.xiongmaopeilian.com/student/calendar": "e636e18958b0d7177a7fa0c7e5253eec",
--   "https://jinshuju.net/f/rzyaJv": "d69cc3d09731d3c802286f52f52fad88",
--   "https://dss-weixin.xiongmaopeilian.com/student/invitedRecords": "925fe66fadb5963301da3bad8bcff7b0"
-- }
-- {
--   "6a6a943dc8ca4ff68d43778843c26991": "https://dss-weixin.xiongmaopeilian.com/student/myAccount",
--   "3b830f35bce06ae8490a9ec36d57131f": "https://dss-weixin.xiongmaopeilian.com/student/poster",
--   "192813729560b20abbaf75654227c5a4": "https://dss-weixin.xiongmaopeilian.com/student/returnMoney?tag=1",
--   "f9a67938627f2516ce32afc1f306f8c2": "https://dss-weixin.xiongmaopeilian.com/student/referral?tag=1",
--   "e636e18958b0d7177a7fa0c7e5253eec": "https://dss-weixin.xiongmaopeilian.com/student/calendar",
--   "d69cc3d09731d3c802286f52f52fad88": "https://jinshuju.net/f/rzyaJv",
--   "925fe66fadb5963301da3bad8bcff7b0": "https://dss-weixin.xiongmaopeilian.com/student/invitedRecords"
-- }
--
  ('WECHAT_CONFIG', '微信配置', 'menu_redirect', '{"6a6a943dc8ca4ff68d43778843c26991":"https://dss-weixin.xiongmaopeilian.com/student/myAccount","3b830f35bce06ae8490a9ec36d57131f":"https://dss-weixin.xiongmaopeilian.com/student/poster","192813729560b20abbaf75654227c5a4":"https://dss-weixin.xiongmaopeilian.com/student/returnMoney?tag=1","f9a67938627f2516ce32afc1f306f8c2":"https://dss-weixin.xiongmaopeilian.com/student/referral?tag=1","e636e18958b0d7177a7fa0c7e5253eec":"https://dss-weixin.xiongmaopeilian.com/student/calendar","d69cc3d09731d3c802286f52f52fad88":"https://jinshuju.net/f/rzyaJv","925fe66fadb5963301da3bad8bcff7b0":"https://dss-weixin.xiongmaopeilian.com/student/invitedRecords"}', '菜单按钮重定向配置'),
  ('WECHAT_CONFIG', '微信配置', 'tag_update_amount', '20', '批量更新数量'),

-- MENU:
-- tag_id:106
-- {"button":[{"type":"view","name":"绑定有礼","url":"https://dss-weixin.xiongmaopeilian.com/student/myAccount"},{"type":"view","name":"练琴神器","url":"https://referral-pre.xiaoyezi.com/operation/landing/recall?m=&a=99&p=17&c=3782"},{"name":"我的账户","sub_button":[{"type":"view","name":"我的账户","url":"https://mp.weixin.qq.com/s/JB3wqas7Q7sOhKYSQUJmZg"},{"type":"view","name":"使用指南","url":"https://mp.weixin.qq.com/s/P8WaVao_lEav8MozSZFfww"},{"type":"view","name":"联系客服","url":"https://ceshi10.sobot.com/chat/h5/v2/index.html?sysnum=4a24039fb3cd4bce89f738a341a3e93a&channelid=10&uname=用户"},{"type":"view","name":"郎朗测评","url":"https://mp.weixin.qq.com/s/JB3wqas7Q7sOhKYSQUJmZg"}]}],"matchrule":{"tag_id":"106"}}

-- tag_id:109
-- {"button":[{"type":"click","name":"推荐有奖","key":"STUDENT_PUSH_MSG_SHARE_AWARD","sub_button":[]},{"type":"view","name":"练琴神器","url":"https://referral.xiaoyezi.com/operation/landing/recall?m=&a=99&p=17&c=3782"},{"name":"我的账户","sub_button":[{"type":"view","name":"我的账户","url":"https://dss-weixin.xiongmaopeilian.com/student/myAccount"},{"type":"view","name":"使用指南","url":"https://mp.weixin.qq.com/s/P8WaVao_lEav8MozSZFfww"},{"type":"view","name":"联系客服","url":"https://ceshi10.sobot.com/chat/h5/v2/index.html?sysnum=4a24039fb3cd4bce89f738a341a3e93a&channelid=10&uname=%E7%94%A8%E6%88%B7"},{"type":"view","name":"郎朗测评","url":"https://mp.weixin.qq.com/s/JB3wqas7Q7sOhKYSQUJmZg"}]}],"matchrule":{"tag_id":"109"}}


-- tag_id:113
-- {"button":[{"name":"推荐有奖","sub_button":[{"type":"view","name":"月月有奖","url":"http://referral.xiaoyezi.com/operation/activity/christmas?activity_id=12"},{"type":"view","name":"专属海报","url":"https://dss-weixin.xiongmaopeilian.com/student/poster"},{"type":"click","name":"一键分享","key":"STUDENT_PUSH_MSG_USER_SHARE"},{"type":"view","name":"分享领学费","url":"https://dss-weixin.xiongmaopeilian.com/student/returnMoney?tag=1"}]},{"type":"view","name":"周周领奖","url":"https://dss-weixin.xiongmaopeilian.com/student/referral?tag=1"},{"name":"我的账户","sub_button":[{"type":"view","name":"我的账户","url":"https://dss-weixin.xiongmaopeilian.com/student/myAccount"},{"type":"view","name":"郎朗测评","url":"https://mp.weixin.qq.com/s/JB3wqas7Q7sOhKYSQUJmZg"},{"type":"view","name":"练琴日报","url":"https://dss-weixin.xiongmaopeilian.com/student/calendar"},{"type":"view","name":"联系客服","url":"https://ceshi10.sobot.com/chat/h5/v2/index.html?sysnum=4a24039fb3cd4bce89f738a341a3e93a&channelid=10&uname=%E7%94%A8%E6%88%B7"},{"type":"view","name":"查看邀请记录","url":"https://dss-weixin.xiongmaopeilian.com/student/invitedRecords"}]}],"matchrule":{"tag_id":"113"}}

-- tag_id:118
-- {"button":[{"name":"精彩福利","sub_button":[{"type":"click","name":"一键分享","key":"STUDENT_PUSH_MSG_USER_SHARE"},{"type":"view","name":"分享领学费","url":"https://op-pre.xiaoyezi.com/student_wx/menu/redirect?code=192813729560b20abbaf75654227c5a4"}]},{"type":"view","name":"练琴日报","url":"https://op-pre.xiaoyezi.com/student_wx/menu/redirect?code=e636e18958b0d7177a7fa0c7e5253eec"},{"name":"我的账户","sub_button":[{"type":"view","name":"我的账户","url":"https://op-pre.xiaoyezi.com/student_wx/menu/redirect?code=6a6a943dc8ca4ff68d43778843c26991"},{"type":"view","name":"使用指南","url":"https://mp.weixin.qq.com/s/P8WaVao_lEav8MozSZFfww"},{"type":"view","name":"联系客服","url":"https://ceshi10.sobot.com/chat/h5/v2/index.html?sysnum=4a24039fb3cd4bce89f738a341a3e93a&channelid=10&uname=%E7%94%A8%E6%88%B7"},{"type":"view","name":"朗朗测评","url":"https://mp.weixin.qq.com/s/JB3wqas7Q7sOhKYSQUJmZg"}]}],"matchrule":{"tag_id":"118"}}

-- tag_id:125
-- {"button":[{"name":"推荐有奖","sub_button":[{"type":"view","name":"月月有奖","url":"http://referral.xiaoyezi.com/operation/activity/christmas?activity_id=12"},{"type":"view","name":"专属海报","url":"https://dss-weixin.xiongmaopeilian.com/student/poster"},{"type":"click","name":"一键分享","key":"STUDENT_PUSH_MSG_USER_SHARE"},{"type":"view","name":"分享领学费","url":"https://dss-weixin.xiongmaopeilian.com/student/returnMoney?tag=1"}]},{"name":"我的账户","sub_button":[{"type":"view","name":"我的账户","url":"https://dss-weixin.xiongmaopeilian.com/student/myAccount"},{"type":"view","name":"郎朗测评","url":"https://mp.weixin.qq.com/s/JB3wqas7Q7sOhKYSQUJmZg"},{"type":"view","name":"练琴日报","url":"https://dss-weixin.xiongmaopeilian.com/student/calendar"},{"type":"view","name":"联系客服","url":"https://ceshi10.sobot.com/chat/h5/v2/index.html?sysnum=4a24039fb3cd4bce89f738a341a3e93a&channelid=10&uname=%E7%94%A8%E6%88%B7"},{"type":"view","name":"查看邀请记录","url":"https://dss-weixin.xiongmaopeilian.com/student/invitedRecords"}]}],"matchrule":{"tag_id":"125"}}

-- tag_id:127
-- {"button":[{"type":"click","name":"一键分享","key":"STUDENT_PUSH_MSG_USER_SHARE"},{"type":"view","name":"课程福利","url":"https://jinshuju.net/f/rzyaJv"},{"name":"我的账户","sub_button":[{"type":"view","name":"我的账户","url":"https://dss-weixin.xiongmaopeilian.com/student/myAccount"},{"type":"view","name":"使用指南","url":"https://mp.weixin.qq.com/s/P8WaVao_lEav8MozSZFfww"},{"type":"view","name":"联系客服","url":"https://ceshi10.sobot.com/chat/h5/v2/index.html?sysnum=4a24039fb3cd4bce89f738a341a3e93a&channelid=10&uname=%E7%94%A8%E6%88%B7"},{"type":"view","name":"郎朗测评","url":"https://mp.weixin.qq.com/s/JB3wqas7Q7sOhKYSQUJmZg"}]}],"matchrule":{"tag_id":"127"}}





