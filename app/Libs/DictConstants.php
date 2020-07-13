<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/5
 * Time: 12:53 PM
 */

namespace App\Libs;

use App\Services\DictService;

class DictConstants {

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
        ]
    ];

    // 用户中心
    const USER_CENTER = [
        'type' => 'USER_CENTER_CONFIG',
        'keys' => [
            'host',
            'app_id_dss',
            'app_secret_dss',
            'app_id_student',
            'app_secret_student',
            'app_id_teacher',
            'app_secret_teacher',
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
            'voice_templateId'
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
    const VOICE_CALL_CONFIG = [
        'type' => 'VOICE_CALL_CONFIG',
        'keys' => [
            'tianrun_voice_call_appid',
            'tianrun_voice_call_host',
            'tianrun_voice_call_token',

        ]
    ];

    // 后台配置
    const ORG_WEB_CONFIG = [
        'type' => 'ORG_WEB_CONFIG',
        'keys' => [
            'assistant_role',
            'course_manage_role',
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

    const APP_CONFIG_STUDENT = [
        'type' => 'APP_CONFIG_STUDENT',
        'keys' => [
            'guide_url',
            'review_guide_url',
            'policy_url',
            'sub_info_count',
            'tmall_2680',
            'tmall_599',
            'free_package',
            'pay_test_students',
            'success_url',
            'cancel_url',
            'result_url',
            'pay_url',
            'share_url',
            'share',
            'trial_duration',
            'ai_adjust_db',
            'device_check',
            'exam_enable',
            'exam_url',
            'tts_url',
            'exam_category_ids',
            'play_share_assess_url',
            'self_test_of_piano_sound'
        ]
    ];

    const WEB_STUDENT_CONFIG = [
        'type' => 'WEB_STUDENT_CONFIG',
        'keys' => [
            'success_url',
            'cancel_url',
            'result_url',
            'package_id',
            'plus_package_id',
            'mini_package_id',
        ]
    ];

    const WEIXIN_STUDENT_CONFIG = [
        'type' => 'WEIXIN_STUDENT_CONFIG',
        'keys' => [
            'success_url',
            'cancel_url',
            'result_url',
            'assess_result_share_channel_id'
        ]
    ];

    //课包配置
    const PACKAGE_CONFIG = [
        'type' => 'PACKAGE_CONFIG',
        'keys' => [
            'package_id',
            'plus_package_id',
        ]
    ];

    const APP_CONFIG_TEACHER = [
        'type' => 'APP_CONFIG_TEACHER',
        'keys' => [
            'default_collections',
            'trial_lessons',
            'policy_url',
            'course_id',
        ]
    ];

    // ip白名单
    const IP_WHITE_LIST = [
        'type' => 'IP_WHITE_LIST',
        'keys' => [
            'erp',
            'org_web',
        ]
    ];

    // 特殊机构对应id
    const SPECIAL_ORG_ID = [
        'type' => 'SPECIAL_ORG_ID',
        'keys' => [
            'internal', // 内部 0
            'direct', // 直营 1
            'panda', // 线上熊猫
            'ec', // 电商
            'classroom', // 老教室
        ]
    ];

    // 用户标签
    const FLAG_ID = [
        'type' => 'FLAG_ID',
        'keys' => [
            'new_score', // 新曲谱测试
            'res_free', // 资源免费
            'app_review', // AppStore审核
        ]
    ];

    //题目状态
    const QUESTION_STATUS = [
        'type' => 'question_status',
        'keys' => [1, 2, 3]
    ];

    //题目类型
    const QUESTION_TEMPLATE = [
        'type' => 'question_template',
        'keys' => [1, 2, 3, 4]
    ];

    const EXAM_BANNER = [
        'type' => 'exam_banner',
        'keys' => [1, 2],
    ];

    const EXAM_POP = [
        'type' => 'exam_pop',
        'keys' => [1],
    ];

    const ACCOUNT_TYPE = [
        'type' => 'account_type',
        'keys' => [1, 2],
    ];

    const LICENSE_TYPE = [
        'type' => 'license_type',
        'keys' => [1, 2, 3],
    ];

    const CLASSROOM_APP_CONFIG = [
        'type' => 'classroom_app_config',
        'keys' => ['used_offline']
    ];

    const LANDING_CONFIG = [
        'type' => 'landing_config',
        'keys' => ['channel_weixin', 'user_action_set_id'],
    ];

    const REVIEW_COURSE_CONFIG = [
        'type' => 'REVIEW_COURSE_CONFIG',
        'keys' => ['reviewer_ids'],
    ];

    const ACTIVITY_CONFIG = [
        'type' => 'ACTIVITY_CONFIG',
        'keys' => ['gift_course_id'],
    ];

    const WE_CHAT_RED_PACK_CONFIG = [
        'type' => 'WE_CHAT_RED_PACK',
        'keys' => ['ACT_NAME', 'SEND_NAME', 'WISHING', 'NORMAL_PIC_WORD', 'COMMUNITY_PIC_WORD'],
    ];

    const REVIEW_TEACHER_THUMB = [
        'type' => 'REVIEW_TEACHER_THUMB',
        'keys' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
    ];

    const PACKAGE_CHANNEL = [
        'type' => 'PACKAGE_CHANNEL',
        'keys' => [1, 2, 3]
    ];

    const PACKAGE_STATUS = [
        'type' => 'PACKAGE_STATUS',
        'keys' => [-1, 0, 1]
    ];

    const PACKAGE_TYPE = [
        'type' => 'PACKAGE_TYPE',
        'keys' => [1, 2]
    ];

    const APPLY_TYPE = [
        'type' => 'APPLY_TYPE',
        'keys' => [1, 2]
    ];

    const TRIAL_TYPE = [
        'type' => 'TRIAL_TYPE',
        'keys' => [1, 2]
    ];

    const COMMUNITY_CONFIG = [
        'type' => 'COMMUNITY_CONFIG',
        'keys' => ['COMMUNITY_UPLOAD_POSTER_URL']
    ];

    const STUDENT_INVITE_CHANNEL = [
        'type' => 'STUDENT_INVITE',
        'keys' => [
            'NORMAL_STUDENT_INVITE_STUDENT',
            'POSTER_LANDING_49_STUDENT_INVITE_STUDENT',
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

    const DEPT_DATA_TYPE_NAME = [
        'type' => 'DEPT_DATA_TYPE_NAME',
        'keys' => [1]
    ];

    const DEPT_PRIVILEGE_TYPE_NAME = [
        'type' => 'DEPT_PRIVILEGE_TYPE_NAME',
        'keys' => [0, 1, 2, 3, 4]
    ];

    //积分活动
    const CREDIT_ACTIVITY_CONFIG = [
        'type' => 'credit_activity_config',
        'keys' => [
            'every_day_sign_in_event_id', //每日签到
            'every_day_sign_in_one_day_task_id', //连续签到一天
            'every_day_sign_in_two_day_task_id', //连续签到两天
            'every_day_sign_in_three_day_task_id', //连续签到三天
            'every_day_sign_in_four_day_task_id', //连续签到四天
            'every_day_sign_in_five_day_task_id', //连续签到五天
            'every_day_sign_in_six_day_task_id',//连续签到六天
            'every_day_sign_in_seven_day_task_id', //连续签到七天
            'every_day_sign_in_than_seven_day_task_id', //连续签到大于七天
            'every_day_task_event_id', //每日任务
            'play_piano_thirty_m_task_id', //每日练琴三十分钟
            'play_piano_forty_m_task_id', //每日练琴四十分钟,
            'play_piano_sixty_m_task_id', //每日练琴六十分钟
            'both_hands_evaluate_task_id', //每日完成1次双手全曲评测
            'share_evaluate_grade_task_id' //每日分享评测成绩奖
        ]
    ];

    const THIRD_PART_BILL_STATUS = [
        'type' => 'third_part_bill_status',
        'keys' => [1, 2]
    ];

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

    public static function getSet($type)
    {
        return DictService::getTypeMap($type['type']);
    }
}