<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/5
 * Time: 12:53 PM
 */

namespace App\Libs;

use App\Models\Erp\ErpDictModel;
use App\Services\DictService;

class DictConstants {

    //邀请学生渠道配置
    const STUDENT_INVITE_CHANNEL = [
        'type' => 'STUDENT_INVITE',
        'keys' => [
            'NORMAL_STUDENT_INVITE_STUDENT',
            'BUY_NORMAL_STUDENT_INVITE_STUDENT',
            'BUY_TRAIL_STUDENT_INVITE_STUDENT',
            'REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT',
            'POSTER_LANDING_49_STUDENT_INVITE_STUDENT',
            'BUY_TRAIL_REFERRAL_MINIAPP_STUDENT_INVITE_STUDENT',//智能陪练一级智能体验营小程序已购买转介绍
            'APP_CAPSULE_INVITE_CHANNEL',
            'CHANNEL_STANDARD_POSTER', // 标准海报渠道
        ]
    ];

    //消息队列相关配置
    const QUEUE_CONFIG = [
        'type' => 'queue_config',
        'keys' => [
            'NSQ_LOOKUPS', 'NSQ_TOPIC_PREFIX'
        ]
    ];

    //默认头像
    const STUDENT_DEFAULT_INFO = [
        'type' => 'student_info',
        'keys' => [
            'default_thumb',
            'default_wx_nickname'
        ]
    ];

    // 员工专项活动设置
    const EMPLOYEE_ACTIVITY_ENV = [
        'type' => 'EMPLOYEE_ACTIVITY_ENV',
        'keys' => [
            'invite_channel',
            'employee_activity_landing_url'
        ],
    ];

    // 活动启用状态
    const ACTIVITY_ENABLE_STATUS = [
        'type' => 'ACTIVITY_ENABLE_STATUS',
        'keys' => [
            '1',    // 1,待启用
            '2',    // 2,启用
            '3',    // 3,已禁用
        ]
    ];

    // 活动类型名字
    const ACTIVITY_RULE_TYPE_ZH = [
        'type' => 'ACTIVITY_RULE_TYPE_ZH',
        'keys' => [
            '1',    // 社群活动
            '2',    // 课管活动
        ]
    ];
    
    // RT活动优惠券状态
    const RT_ACTIVITY_COUPON_STATUS = [
        'type' => 'RT_ACTIVITY_COUPON_STATUS',
        'keys' => [
            '1',    // 未领取
            '2',    // 已领取(未使用)
            '3',    // 已使用
            '4',    // 已过期
            '5',    // 已作废
        ]
    ];
    
    // RT活动优惠券状态
    const RT_ACTIVITY_CONFIG = [
        'type' => 'RT_ACTIVITY_CONFIG',
        'keys' => [
            'award_event_task_id',    // 推荐人奖励任务id
        ]
    ];

    //正式时长配置
    const OFFICIAL_DURATION_CONFIG = [
        'type' => 'OFFICIAL_DURATION_CONFIG',
        'keys' => [
            'year_days',    //年卡对应天数
        ]
    ];
    
    // 阿里云config
    const ALI_OSS_CONFIG = [
        'type' => 'ALI_OSS_CONFIG',
        'keys' => [
            'access_key_id',
            'access_key_secret',
            'bucket',
            'endpoint',
            'host',
            'callback_url',
            'expire',
            'max_file_size',
            'region_id',
            'record_file_arn',
            'img_dir',
            'cdn_domain',
            'img_size_h',
            'dss_cdn_domain'
        ]
    ];
    // 用户中心
    const USER_CENTER = [
        'type' => 'USER_CENTER_CONFIG',
        'keys' => [
            'host',
            'app_id_op',
            'app_secret_op'
        ]
    ];

    const SERVICE = [
        'type' => 'SERVICE_CONFIG',
        'keys' => [
            'sms_host',
            'opern_host',
            'new_opern_host',
            'ai_backend_host',
            'uc_app_host',
            'panda_crm_host',
            'erp_host',
            'ai_class_host',
            'voice_appId',
            'voice_appKey',
            'voice_url',
            'voice_templateId',
            'ai_class_http_host',
            'dss_host',
            'tpns_host',
            'access_id_android',
            'secret_key_android',
            'access_id_ios',
            'secret_key_ios',
        ]
    ];

    const VOICE_SMS_CONFIG = [
        'type' => 'VOICE_SMS_CONFIG',
        'keys' => [
            'voice_app_id',
            'voice_app_key',
            'voice_host',
            'voice_purchase_experience_class_template_id'
        ]
    ];

    // 后台配置
    const ORG_WEB_CONFIG = [
        'type' => 'ORG_WEB_CONFIG',
        'keys' => [
            'assistant_role',
            'course_manage_role',
            'maker_role',
            'config_role',
            'push_user_template',
            'third_part_import_bill_template',
            'batch_import_reward_points_template',
            'export_amount',
            'export_total',
        ]
    ];

    const APP_CONFIG_COMMON = [
        'type' => 'APP_CONFIG_COMMON',
        'keys' => [
            'ai_host',
            'new_ai_host',
            'review_mobile',
            'review_validate_code',
            'super_validate_code',
            'res_test_mobiles',
        ]
    ];

    // ip白名单
    const IP_WHITE_LIST = [
        'type' => 'IP_WHITE_LIST',
        'keys' => [
            'erp',
            'org_web',
            'crm',
            'sales_master',
        ]
    ];

    //个性化海报/标准海报配置
    const TEMPLATE_POSTER_CONFIG = [
        'type' => 'template_poster_config',
        'keys' => [
            1,
            2,
            'QR_X',
            'QR_Y',
            'QR_WIDTH',
            'QR_HEIGHT',
            'POSTER_WIDTH',
            'POSTER_HEIGHT',
        ]
    ];


    //代理特有海报配置
    const AGENT_POSTER_CONFIG = [
        'type' => 'agent_poster_config',
        'keys' => [
            'ORGANIZATION_WORD_X',
            'ORGANIZATION_WORD_Y',
            'ORGANIZATION_WORD_COLOR',
            'ORGANIZATION_WORD_SIZE',
            'RECOMMEND_WORD_X',
            'RECOMMEND_WORD_Y',
            'RECOMMEND_WORD_COLOR',
            'RECOMMEND_WORD_SIZE',
        ]
    ];

    const DEPT_DATA_TYPE_NAME = [
        'type' => 'DEPT_DATA_TYPE_NAME',
        'keys' => [1]
    ];

    const DEPT_PRIVILEGE_TYPE_NAME = [
        'type' => 'DEPT_PRIVILEGE_TYPE_NAME',
        'keys' => [0, 1, 2, 3, 4]
    ];

    //go的钉钉接口
    const DING_DING_CONFIG = [
        'type' => 'ding_ding',
        'keys' => [
            'host',
            'tem_no',
            'flow_url'
        ]
    ];
    //钉钉审批状态
    const DING_DING_STATUS = [
        'type' => 'ding_apply_status',
        'keys' => [1,2,3,4,5]
    ];

    const HAS_REVIEW_COURSE = [
        'type' => 'HAS_REVIEW_COURSE',
        'keys' => [0, 1, 2, 3]
    ];

    const SHARE_POSTER_CHECK_STATUS = [
        'type' => 'share_poster_check_status',
        'keys' => [1, 2, 3]
    ];

    //通用红包搜索节点
    const COMMON_CASH_NODE = [
        'type' => 'operation_common_node',
        'keys' => [
            '20', '21', '22'
        ]
    ];
    //补发红包搜索节点
    const REISSUE_CASH_NODE = [
        'type' => 'operation_reissue_node',
        'keys' => [
            '6','7','8','9','10','11','12','13'
        ]
    ];
    //转介绍红包搜索节点
    const REFEREE_CASH_NODE = [
        'type' => 'operation_referee_node',
        'keys' => [
            '1','2','3'
        ]
    ];
    //节点特殊配置
    const NODE_SETTING = [
        'type' => 'node_setting',
        'keys' => [
            'not_display_wait',
            'not_verify_refund',
            'points_exchange_red_pack_id',  // 积分兑换红包节点id
        ]
    ];
    //节点与task的对应关系
    const NODE_RELATE_TASK = [
        'type' => 'node_relate_task',
        'keys' => [
            '1','2','3','4','5','6','7','8','9','10','11','12','13',
            '18','19','20','21','22'
        ]
    ];
    //不同应用的微信app_id
    const WECHAT_APPID = [
        'type' => 'wechat_app_id',
        'keys' => [
            '8_1', '8_8', '21_9','8_10', '8_12', '1_13'
        ]
    ];
    //不同应用的微信app_secret
    const WECHAT_APP_SECRET = [
        'type' => 'wechat_app_secret',
        'keys' => [
            '8_1', '8_8', '21_9','8_10', '8_12', '1_13'
        ]
    ];

    //不同应用的微信消息推动配置
    const WECHAT_APP_PUSH_CONFIG = [
        'type' => 'wechat_app_push_config',
        'keys' => [
            '8_10_token',
            '8_10_encoding_aes_key',
        ]
    ];

    //微信白名单转账配置
    const WECHAT_TRANSFER_TO_USER = [
        'type' => 'wechat_transfer_mchid',
        'keys' => [
            '8_1',
        ],
    ];

    //微信白名单转账cert配置
    const WECHAT_TRANSFER_CERT_PEM = [
        'type' => 'wechat_transfer_cert_pem',
        'keys' => [
            '8_1',
        ],
    ];

    //微信白名单转账key配置
    const WECHAT_TRANSFER_KEY_PEM = [
        'type' => 'wechat_transfer_key_pem',
        'keys' => [
            '8_1',
        ],
    ];

    //周周领奖白名单有效期
    const WEEK_WHITE_TERM_OF_VALIDITY = [
        'type' => 'week_white_config',
        'keys' => [
            'term_of_validity',
        ],
    ];

    //获取白名单红包密钥
    const WECHAT_TRANSFER_KEY = [
        'type' => 'wechat_transfer_key',
        'keys' => [
            '8_1',
        ],
    ];

    //不同应用的微信商户号
    const WECHAT_MCHID = [
        'type' => 'wechat_mchid',
        'keys' => [
            '8_1'
        ]
    ];
    //不同应用的cert的pem路径
    const WECHAT_API_CERT_PEM = [
        'type' => 'wechat_api_cert_pem',
        'keys' => [
            '8_1'
        ]
    ];
    //不同应用的key的pem路径
    const WECHAT_API_KEY_PEM = [
        'type' => 'wechat_api_key_pem',
        'keys' => [
            '8_1'
        ]
    ];
    //红包祝福语设置
    const WE_CHAT_RED_PACK_CONFIG = [
        'type' => 'WE_CHAT_RED_PACK',
        'keys' => ['NORMAL_PIC_WORD', 'COMMUNITY_PIC_WORD', 'REFERRER_PIC_WORD', 'REISSUE_PIC_WORD', 'POINTS_EXCHANGE_RED_PACK_SEND_NAME'],
    ];

    //消息推送规则
    const MESSAGE_RULE = [
        'type' => 'message_rule_config',
        'keys' => [
            'receive_red_pack_rule_id',
            'trail_user_c_rule_id',
            'assign_template_id',
            'year_pay_rule_id',
            'subscribe_rule_id',
            'only_trail_rule_id',
            'only_year_rule_id',
            'year_user_c_rule_id',
            'register_user_c_rule_id',
            'how_long_not_invite',
            'how_long_not_result',
            'start_class_day_rule_id',
            'start_class_seven_day_rule_id',
            'before_class_one_day_rule_id',
            'before_class_two_day_rule_id',
            'after_class_one_day_rule_id',
            'monthly_event_rule_id',
            'no_play_day_rule_config', // 未练琴推送规则ID配置
            'week_reward_mon_rule_id',
            'week_reward_tue_rule_id',
            'week_reward_wed_rule_id',
            'week_reward_thur_rule_id',
            'week_reward_fri_rule_id',
            'week_reward_sat_rule_id',
            'week_reward_sun_rule_id',
            'month_reward_mon_rule_id',
            'month_reward_wed_rule_id',
            'month_reward_fri_rule_id',
            'month_reward_sun_rule_id',
            'life_subscribe_rule_id',
            'invite_friend_rule_id',
            'invite_friend_pay_rule_id',
            'invite_friend_not_pay_rule_id',
        ]
    ];

    // 体验营打卡签到设置
    const CHECKIN_PUSH_CONFIG = [
        'type' => 'CHECKIN_PUSH_CONFIG',
        'keys' => [
            'day_0',
            'day_1',
            'day_2',
            'day_3',
            'day_4',
            'day_5',
            'text_position',
            'poster_config',
            'verify_message_config_id',
            'url',
            'page_url',
            'collection_event_id',
            'max_name_length',
            'day_channel',
            'day_poster_config',
            'new_old_rule_dividing_line_time',
            'task_ids',
            'day_channel_app',
            'day_channel_wx',
            'day_channel_push',
        ],
    ];
    // 转介绍相关配置
    const REFERRAL_CONFIG = [
        'type' => 'REFERRAL_CONFIG',
        'keys' => [
            'new_rule_start_time', // 新规则启用时间
            'normal_task_config', // 买年卡，根据人数决定奖励
            'task_stop_change_number', // 买年卡，超过多少人后奖励不再变化。ex:第6个及以后都是相同奖励
            'dsscrm_1841_start_time', // 年卡推荐人数计算起始时间点
            'xyzop_178_start_point',
            'trial_task_stop_change_number_xyzop_178',
            'trial_task_config_xyzop_178',
            'extra_task_id_normal_xyzop_178',
            'student_invite_send_points_start_time', // 我的邀请人发放积分开始时间
            'buy_trail_re_mini_sis_p_id', //智能体验营小程序已购买转介绍海报底图id
            'cumulative_invite_buy_year_card',    // 累计邀请购买年卡
            'refused_poster_url', // 海报审核未通过跳转地址
            'week_activity_url', // 周周有奖活动地址
            'allowed_0_channel', // 指定0元的渠道
            'real_refused_poster_url', // 真人 - 海报审核未通过跳转地址
        ]
    ];

    //推送用户类型
    const PUSH_USER_TYPE = [
        'type' => 'push_user_type',
        'keys' => [
            '1',    //全量用户推动
            '2',    //指定用户推送
        ]
    ];

    //推送类型
    const PUSH_TYPE = [
        'type' => 'push_type',
        'keys' => [
            '1',    //首页
            '2',    //webview链接
            '3',    //浏览器链接
            '4',    //小程序
            '5',    //音符商城-商品详情页
            '6',    //练琴日历
            '7',    //套课详情页
        ]
    ];
    //代理
    const AGENT = [
        'type' => 'agent',
        'keys' => ['1', '2']
    ];
    //代理模式类型
    const AGENT_TYPE = [
        'type' => 'agent_type',
        'keys' => ['1', '2', '3', '4']
    ];
    //业务线
    const PACKAGE_APP_NAME = [
        'type' => 'PACKAGE_APP_NAME',
        'keys' => ['8']
    ];
    //代理绑定有效时间
    const AGENT_BIND = [
        'type' => 'agent_bind',
        'keys' => ['1', '2']
    ];
    //订单状态
    const CODE_STATUS = [
        'type' => 'code_status',
        'keys' => ['0', '1', '2']
    ];
    //代理转介绍学生的当前进度
    const AGENT_USER_STAGE = [
        'type' => 'agent_user_stage',
        'keys' => ['0', '1', '2']
    ];
    //代理和学生绑定状态
    const AGENT_BIND_STATUS = [
        'type' => 'agent_bind_status',
        'keys' => ['0', '1', '2']
    ];
    //第三方订单导入状态
    const THIRD_PART_BILL_STATUS = [
        'type' => 'third_part_bill_status',
        'keys' => [1, 2]
    ];

    // 代理配置
    const AGENT_CONFIG = [
        'type' => 'AGENT_CONFIG',
        'keys' => [
            'channel_distribution',
            'channel_individual', // 个人家长代理
            'channel_individual_teacher', // 个人老师代理
            'channel_offline',
            'channel_dict',
            'default_thumb',
            'package_buy_page_url', // 产品购买页面
            'share_card_logo', // 分享卡片logo
            'ai_wx_official_account_qr_code', // 智能陪练公众号二维码
        ]
    ];
    // 是否
    const YSE_OR_NO_STATUS = [
        'type' => 'yes_or_no_status',
        'keys' => ['0', '1']
    ];

    //代理分成模式类型
    const AGENT_DIVISION_MODEL = [
        'type' => 'agent_division_model',
        'keys' => ['1', '2']
    ];

    //代理线索分配类型：1自动分配 2不分配 3分配助教
    const AGENT_LEADS_ALLOT_TYPE = [
        'type' => 'agent_leads_allot_type',
        'keys' => ['1', '2', '3']
    ];

    // 发送邮件设置
    const SEND_MAIL_CONFIG = [
        'type' => 'send_mail_config',
        'keys' => [
            'from_mail',
            'from_mail_pasd',
            'from_name',
            'smtp_server',
            'smtp_port',
        ]
    ];
    // 批量发放积分邮件通知配置
    const AWARD_POINTS_SEND_MAIL_CONFIG = [
        'type' => 'award_points_send_mail_config',
        'keys' => [
            'to_mail',
            'title',
            'err_title'
        ]
    ];

    const DSS_PERSONAL_LINK_PACKAGE_ID = [
        'type' => 'personal_link_package_id',
        'keys' => [
            'package_id',
            'discount_package_id'
        ]
    ];

    //检测未激活正式课激活码的过期时间
    const DSS_CHECK_NO_ACTIVE_CODE_EXPIRE_TIME = [
        'type' => 'check_no_active_code_expire_time',
        'keys' => [
            'is_check_no_active_code_expire',    //是否检测未激活正式课激活码的过期时间
            'no_active_code_expire_day',    //未激活正式课激活码的过期天数
        ]
    ];

    const DSS_WEIXIN_STUDENT_CONFIG = [
        'type' => 'WEIXIN_STUDENT_CONFIG',
        'keys' => [
            'success_url',
            'cancel_url',
            'result_url',
            'success_url_v1',
            'result_url_v1',
        ]
    ];
    const WEIXIN_ALIPAY_CONFIG = [
        'type' => 'WEIXIN_ALIPAY_CONFIG',
        'keys' => [
            'success_url',
            'cancel_url',
            'result_url',
        ]
    ];

    const DSS_APP_CONFIG_STUDENT = [
        'type' => 'APP_CONFIG_STUDENT',
        'keys' => [
            'success_url',
            'cancel_url',
            'result_url',
        ]
    ];

    const AGENT_WEB_STUDENT_CONFIG = [
        'type' => 'AGENT_WEB_STUDENT_CONFIG',
        'keys' => [
            'success_url',
            'cancel_url',
            'result_url',
            'broadcast_config',
            'success_url_v1',
            'cancel_url_v1',
            'result_url_v1',
        ]
    ];

    const DSS_WEB_STUDENT_CONFIG = [
        'type' => 'WEB_STUDENT_CONFIG',
        'keys' => [
            'package_id',
            'plus_package_id',
            'mini_package_id',
            'mini_package_id_v1',
            'mini_001_package_id',
            'mini_1_package_id',
            'package_id_v2'
        ]
    ];
    const WEB_STUDENT_CONFIG = [
        'type' => 'WEB_STUDENT_CONFIG',
        'keys' => [
            'package_id',
            'package_id_v2',
            'mini_1_package_id',
            'mini_0_package_id',
            'mini_package_id_v1',
            'mini_001_package_id',
            'zero_order_remark',
            'zero_package_enable',
            'broadcast_config_users',
        ]
    ];

    const RECALL_CONFIG = [
        'type' => 'RECALL_CONFIG',
        'keys' => [
            'event_deadline',
            'send_sms_flag', // 短信发送开关
        ]
    ];

    const WEB_PROMOTION_CONFIG = [
        'type' => 'WEB_PROMOTION_CONFIG',
        'keys' => [
            'allowed_channel',
        ]
    ];

    // 小叶子AI智能陪练小程序
    const AI_PLAY_MINI_APP_CONFIG = [
        'type' => 'AI_PLAY_MINI_APP_CONFIG',
        'keys' => [
            'verify_switch', // 审核开关
        ]
    ];

    // 签名秘钥
    const SERVICE_SIGN_KEY = [
        'type' => 'SERVICE_SIGN_KEY',
        'keys' => [
            'erp_service',
            'crm_service',
        ],
    ];

    //一级渠道与支付方式映射关系
    const CHANNEL_PAY_TYPE_MAP = [
        'type' => 'channel_pay_type_map',
        'keys' => [0]
    ];

    //代理订单撞单数据发送邮件地址列表
    const AGENT_HIT_EMAILS = [
        'type' => 'agent_hit_emails',
        'keys' => [1, 2, 3, 4, 5, 6, 7, 8, 9,]
    ];

    //机构代理批量导入学生据发送邮件地址列表
    const AGENT_ORG_EMAILS = [
        'type' => 'agent_org_emails',
        'keys' => [
            'import_student', //导入学生
        ]
    ];

    //测试使用代理商ID
    const COMPANY_TEST_AGENT_IDS = [
        'type' => 'company_test_agent_ids',
        'keys' => [0]
    ];

    const WECHAT_CONFIG = [
        'type' => 'WECHAT_CONFIG',
        'keys' => [
            'menu_redirect', // 菜单重定向配置
            'user_type_tag_dict', // 用户对应菜单的标签
            'tag_update_amount', // 请求更新标签数量
            'all_menu_tag',
            'menu_tag_none',
        ],
    ];
    //上传截图奖励规则分割时间点
    const NORMAL_UPLOAD_POSTER_DIVISION_TIME = [
        'type' => 'normal_poster_change_award',
        'keys' => [
            'division_time'
        ]
    ];
    //上传截图对应的task_id
    const NORMAL_UPLOAD_POSTER_TASK = [
        'type' => 'normal_upload_poster_task',
        'keys' => [
            '0','1','2','-1'
        ]
    ];
    //付款方式
    const PAYMENT_MODE = [
        'type' => 'payment_mode',
        'keys' => [1, 2, 3]
    ];
    //审核状态
    const CHECK_STATUS = [
        'type' => 'check_status',
        'keys' => [1, 2, 3]
    ];

    //预存订单审核操作行为
    const AGENT_STORAGE_APPROVED_ACTION = [
        'type' => 'agent_storage_approved_action',
        'keys' => [1, 2, 3, 4]
    ];
    //预存年卡数据产生和消费过程日志类型
    const AGENT_STORAGE_PROCESS_LOG_TYPE = [
        'type' => 'agent_storage_process_log_type',
        'keys' => [1, 2]
    ];
    //预存年卡配置数据
    const AGENT_STORAGE_CONFIG = [
        'type' => 'agent_storage_config',
        'keys' => ['interval_time_days']
    ];


    //端午节活动配置
    const ACTIVITY_DUANWU_CONFIG = [
        'type' => 'activity_duanwu_config',
        'keys' => [
            'wx_url','app_url','app_poster_path','activity_id','activity_start_time','activity_end_time',
        ]
    ];
    //数据权限类型
    const DATA_PERMISSION_TYPE = [
        'type' => 'data_permission_type',
        'keys' => ['1', '2']
    ];
    //角色ID
    const ROLE_ID=[
        'type' => 'role_id',
        'keys' => [
            'SUPER_MANAGE_ROLE_ID',//超级管理员角色ID
            'AGENT_MANAGE_ROLE_ID',//代理商运营角色ID
            'AGENT_STORAGE_FINANCE_ROLE_ID',//预存订单财务审核角色ID
        ]
    ];

    // 学生任务默认配置
    const STUDENT_TASK_DEFAULT = [
        'type' => 'student_task_default',
        'keys' => [
            'banner',
            'message_config_id',
        ],
    ];

    // 生成微信小程序码配置
    const MINI_APP_QR = [
        'type' => 'mini_app_qr',
        'keys' => [
            'current_max_id',                   // 当前生成的最大标识
            'create_id_num',                    // 生成标识数量
            'wait_create_mini_qr_set_key',      // 等待生成小程序码集合key
            'get_mini_app_qr_second_num',       // 获取小程序码每秒请求数量 - 这个要考虑到微信每秒接受请求的数量调整
            'start_generation_threshold_num',   // 启动生成小程序标识任务数量阀值
            'wait_mini_qr_max_num',             // 待使用的小程序码最大数量
            'wait_use_qr_max_num',              // 待使用的二维码最大数量
            'create_wait_use_qr_num',           // 每次生成待使用的二维码数量
            'qr_type_mini',                     // qr_path类型-小程序码
            'qr_type_html',                     // qr_path类型-html码
            'qr_type_none',                     // qr_path类型-无二维码
        ],
    ];

    /**前缀为ERP的配置，数据均配置在erp数据库中erp_dict数据表，不再op系统重复配置，保持数据的唯一性,配置写在此区域start**/
    //新产品包状态
    const ERP_PACKAGE_V1_STATUS = [
        'type' => 'package_v1_status_new',
        'keys' => ['-1', '0', '1', '2']
    ];

    /**
     * erp阿里云config
     */
    const ERP_ALI_OSS_CONFIG = [
        'type' => 'ALI_OSS_CONFIG',
        'keys' => [
            'shop_cdn_domain'
        ]
    ];

    /**
     * erp系统配置
     */
    const ERP_SYSTEM_ENV = [
        'type' => 'system_env',
        'keys' => [
            'QINIU_DOMAIN_1',//七牛目录(ERP)
            'QINIU_FOLDER_1',//七牛Domain(ERP)
            'student_default_thumb' //默认学生头像
        ]
    ];
    /**前缀为ERP的配置，数据均配置在erp数据库中erp_dict数据表，不再op系统重复配置，保持数据的唯一性,配置写在此区域end**/

    /**
     * rt渠道config
     */
    const RT_CHANNEL_CONFIG = [
        'type' => 'RT_CHANNEL_CONFIG',
        'keys' => [
            'rt_channel_v1'
        ]
    ];

    /**
     * rt活动页url
     */
    const RT_ACTIVITY_INDEX = [
        'type' => 'RT_ACTIVITY_INDEX',
        'keys' => [
            'rt_invite',
            'rt_invited',
            'rt_activity_remark'
        ]
    ];

    /**
     * 周周领奖任务之计数任务活动，禁止参与的账户黑名单
     */
    const COUNTING_TASK_BLACKLIST = [
        'type' => 'counting_task_blacklist',
        'keys' => [1]
    ];

    /**
     * 实物奖品物流状态
     */
    const MATERIAL_LOGISTICS_STATUS = [
        'type' => 'material_logistics_status',
        'keys' => [1, 2, 3, 4]
    ];

    /**
     * 课管主动关联转介绍关系配置
     */
    const TRANSFER_RELATION_CONFIG = [
        'type' => 'TRANSFER_RELATION_CONFIG',
        'keys' => [
            'erp_channel_id',
            'user_relation_status',
            'erp_backstage_status',
            'erp_invited_index',
            'free_order_remark',
            'erp_routine_activity_name',
            'erp_rt_activity_name',
        ]
    ];
    
    /**
     * landing页召回目标人群
     */
    const LANDING_RECALL_TARGET = [
        'type' => 'LANDING_RECALL_TARGET',
        'keys' => [1, 2]
    ];
    
    /**
     * landing页召回时机
     */
    const LANDING_RECALL_SEND_TIME = [
        'type' => 'LANDING_RECALL_SEND_TIME',
        'keys' => [5, 30, 60]
    ];
    
    /**
     * landing页召回页面url
     */
    const LANDING_RECALL_URL = [
        'type' => 'LANDING_RECALL_URL',
        'keys' => ['landing_recall_url']
    ];
    
    /**
     * landing页召回注册和购买渠道号
     */
    const LANDING_RECALL_CHANNEL = [
        'type' => 'LANDING_RECALL_CHANNEL',
        'keys' => ['landing_recall_channel']
    ];

    /**
     * 金叶子商城配置
     */
    const SALE_SHOP_CONFIG = [
        'type' => 'SALE_SHOP_CONFIG',
        'keys' => ['home_index','dafault_banner','channel_ids']
    ];

    /**
     * wechat小程序原始id
     */
    const WECHAT_INITIAL_ID = [
        'type' => 'wechat_initial_id',
        'keys' => ['8_8']
    ];

    /**
     * 通用活动配置
     */
    const ACTIVITY_CONFIG = [
         'type' => 'ACTIVITY_CONFIG',
         'keys' => [
             'channel_week_wx',
             'channel_week_app',
             'channel_month_wx',
             'channel_month_app',
             'channel_week_real_student_wx',
             'channel_week_real_student_app',
             'channel_month_real_student_wx',
             'channel_month_real_student_app',
         ]
    ];

    //活动中心显示规则配置
    const ACTIVITY_CENTER_SHOW_RULE = [
        'type' => 'activity_center_show_rule',
        'keys' => [1, 2, 3, 4, 5]
    ];


    //真人转介绍小程序配置
    const REAL_REFERRAL_CONFIG = [
        'type' => 'real_referral_config',
        'keys' => [
            'register_default_channel'
        ]
    ];


    /**
     * 单个获取op系统dict配置数据
     * @param $type
     * @param $key
     * @return array|mixed|null
     */
    public static function get($type, $key)
    {
        if (empty($type) || empty($key)) {
            return null;
        }

        if (is_array($key)) {
            return self::getValues($type, $key);
        }

        if (!in_array($key, $type['keys'])) {
            SimpleLogger::error(__FILE__ . __LINE__ . ' DictConstants::get [invalid key]', [
                'type' => $type,
                'key' => $key
            ]);
            return null;
        }

        return DictService::getKeyValue($type['type'], $key);
    }

    /**
     * 批量获取op系统dict配置数据
     * @param $type
     * @param $keys
     * @return array
     */
    public static function getValues($type, $keys)
    {
        if (empty($type)) {
            return [];
        }

        if (empty($keys)) {
            return [];
        }

        // 如果给的$keys中有不在$type['keys']里的直接返回空
        if (!empty(array_diff($keys, $type['keys']))) {
            return [];
        }

        return DictService::getKeyValuesByArray($type['type'], $keys);
    }

    /**
     * 获取op系统dict配置数据
     * @param $type
     * @return array
     */
    public static function getSet($type)
    {
        return DictService::getTypeMap($type['type']);
    }

    /**
     * 获取指定多个类型数据map
     * @param $types
     * @return array
     */
    public static function getTypesMap($types)
    {
        return DictService::getTypesMap($types);
    }

    /**
     * 获取erp系统dict配置数据
     * @param $type
     * @param $key
     * @return array
     */
    public static function getErpDict($type, $key = [])
    {
        if (empty($type)) {
            return null;
        }
        if (empty($key)) {
            $key = $type['keys'] ?? [];
        }

        if (is_array($key)) {
            if (!empty(array_diff($key, $type['keys']))) {
                return [];
            }
            return ErpDictModel::getKeyValuesByArray($type['type'], $key);
        }

        if (!in_array($key, $type['keys'])) {
            SimpleLogger::error(__FILE__ . __LINE__ . ' DictConstants::getErpDict [invalid key]', [
                'type' => $type,
                'key'  => $key
            ]);
            return null;
        }

        return ErpDictModel::getKeyValue($type['type'], $key);
    }

    /**
     * 获取erp系统dict配置数据, 二维数组 [{code,value}]
     * @param $types
     * @param array $keys
     * @param array $filterCode
     * @return array
     */
    public static function getErpDictArr($types, $keys = [], $filterCode = [])
    {
        $where = [
            'type' => $types,
        ];
        $dictList = [];
        if (!empty($keys)) {
            $where['key_code'] = $keys;
        }
        $data = ErpDictModel::getRecords($where, ['type', 'key_code', 'key_value']);
        if (empty($data)) {
            return $dictList;
        }
        foreach ($data as $k => $v) {
            // 过滤敏感key_code
            if (in_array($v['key_code'], $filterCode)) {
                continue;
            }
            $dictList[$v['type']][] = [
                'code' => $v['key_code'],
                'value' =>$v['key_value']
            ];
        }
        return $dictList;
    }
}
