<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: duhaiqiang
 * Date: 2018/8/20
 * Time: 下午6:05
 */

namespace App\Libs;


class Constants
{
    // 系统设置
    const DICT_TYPE_SYSTEM_ENV = 'system_env';

    //时间单位
    const DICT_TIME_UNIT_MIN = 'TIME_UNIT_MIN'; //分钟

    // 班课状态
    const DICT_TYPE_CLASS_STATUS = 'class_status';
    // 班课用户状态
    const DICT_TYPE_CLASS_USER_STATUS = 'class_user_status';
    // 班课教程状态
    const DICT_TYPE_CLASS_TASK_STATUS = 'class_task_status';
    // 班课用户角色
    const DICT_TYPE_CLASS_USER_ROLE = 'class_user_role';
    // 课程状态
    const DICT_TYPE_SCHEDULE_STATUS = 'schedule_status';
    // 课程用户状态
    const DICT_TYPE_SCHEDULE_USER_STATUS = 'schedule_user_status';
    // 课程类型
    const DICT_TYPE_COURSE_TYPE = 'course_type';
    // 学生子状态
    const DICT_TYPE_SCHEDULE_STUDENT_STATUS = 'schedule_student_status';
    // 老师子状态
    const DICT_TYPE_SCHEDULE_TEACHER_STATUS = 'schedule_teacher_status';
    // 课程操作类型
    const DICT_TYPE_SCHEDULE_LOG_TYPE = 'schedule_log_type';
    // 课包操作类型
    const DICT_TYPE_STUDENT_COURSE_LOG_TYPE = 'student_course_log_type';
    // 老师时间操作类型
    const DICT_TYPE_TEACHER_SCHEDULE_LOG_TYPE = 'teacher_schedule_log_type';
    const DICT_TYPE_TEACHER_SCHEDULE_OPERATOR_TYPE = 'teacher_schedule_operator_type';

    // JWT设置
    const DICT_KEY_JWT_ISSUER = 'JWT_ISSUER';
    const DICT_KEY_JWT_AUDIENCE = 'JWT_AUDIENCE';
    const DICT_KEY_JWT_EXPIRE = 'JWT_EXPIRE';
    const DICT_KEY_JWT_SIGNER_KEY = 'JWT_SIGNER_KEY';

    // JWT Token Type
    const DICT_KEY_TOKEN_TYPE_USER = 'TOKEN_TYPE_USER';

    // 角色设置
    const PWD_NEVER_EXPIRES_ROLE_ID = 'PWD_NEVER_EXPIRES_ROLE_ID';

    // DICT 分页设置  key_code
    const DEFAULT_PAGE_LIMIT = 'DEFAULT_PAGE_LIMIT';

    // 正常废除
    const DICT_TYPE_NORMAL_OR_INVALID = 'normal_or_invalid';
    // 是否
    const DICT_TYPE_YES_OR_NO = 'yes_or_no_status';
    // 课程状态
    const DICT_TYPE_SCHEDULE_TYPE = 'schedule_type';
    // 老师类型
    const DICT_TYPE_TEACHER_TYPE = 'teacher_type';
    //老师教授级别
    const DICT_TYPE_TEACHER_LEVEL = 'teacher_level';
    //老师演奏水平
    const DICT_TYPE_TEACHER_MUSIC_LEVEL = 'teacher_music_level';
    //老师状态
    const DICT_TYPE_TEACHER_STATUS = "teacher_status";
    //老师学历
    const DICT_TYPE_TEACHER_EDUCATION = "teacher_education";
    //老师应用评级
    const DICT_TYPE_TEACHER_EVALUATE = "teacher_evaluate_app_id_";
    //老师高峰上课时间段限制
    const DICT_TYPE_TEACHER_SCHEDULE_LIMIT = "teacher_submit_schedule_limit";
    // 高峰时段最少小时限制
    const DICT_KEY_OFFICIAL_TEACHER_MIN_HIGH_TIME_COUNT_HOUR = "official_teacher_min_high_time_count_hour";
    //老师课程类型
    const DICT_TYPE_TS_COURSE_TYPE = "ts_course_type";
    //对比时间段类型
    const DICT_TYPE_CONTRAST_TYPE = "contrast_type";
    //对比时段组状态
    const DICT_TYPE_CONTRAST_STATUS = "contrast_status";
    //应用类型
    const DICT_TYPE_APP_TYPE = 'app_type';

    //老师导入模板下载
    const IMPORT_TEACHER_TEMPLATE = "/csv/import_teacher_template.csv";

    // 服务器地址
    /** https://erp.xiongmaopeilian.com 末尾没有/,拼url时需要加!!! */
    const DICT_KEY_STATIC_FILE_URL = 'STATIC_FILE_URL';
    const DICT_KEY_QINIU_DOMAIN_1 = 'QINIU_DOMAIN_10';
    const DICT_KEY_QINIU_FOLDER_1 = 'QINIU_FOLDER_10';
    // 阿里云曲谱库曲谱地址
    const DICT_KEY_MEGATRON_RESOLUTION = 'MEGATRON_RESOLUTION';
    // 网络监控地址
    const DICT_KEY_SCHEDULE_STAT_URL = 'SCHEDULE_STAT_URL';
    // 课后报告地址
    const DICT_KEY_TEST_SCHEDULE_REPORT_URL = 'TEST_SCHEDULE_REPORT_URL';
    const DICT_KEY_NORMAL_SCHEDULE_REPORT_URL = 'NORMAL_SCHEDULE_REPORT_URL';

    // DICT  性别 type
    const DICT_TYPE_GENDER = 'gender';
    /**
     * 订单支付状态
     */
    const DICT_TYPE_BILL_PAY_STATUS = "bill_pay_status";

    /**
     * 订单状态
     */
    const DICT_TYPE_BILL_STATUS = "bill_status";

    /**
     * 订单付费渠道
     */
    const DICT_TYPE_BILL_PAY_CHANNEL = "bill_pay_channel";

    /**
     * 订单来源
     */
    const DICT_TYPE_BILL_SOURCE = "bill_source";

    //订单状态
    const DICT_TYPE_BILL_DISABLED = "bill_disabled";

    //DICT 乐器 type
    const DICT_TYPE_INSTRUMENT = "instrument";
    //DICT 学生等级 type
    const DICT_TYPE_STUDENT_LEVEL = "student_level";

    /**
     * 课程相关
     */
    // 课程级别
    const DICT_COURSE_LEVEL = "course_level";
    // 课程状态
    const DICT_COURSE_STATUS = "course_status";
    // 课程类型
    const DICT_COURSE_TYPE = "course_type";

    /**
     * IP白名单
     */
    const DICT_KEY_IP_WHITE_LIST = 'IP_WHITE_LIST';

     /**
     * 商品状态
     */
    const DICT_GOODS_STATUS = "goods_status";
    // 用户中心主机地址
    const DICT_KEY_UC_HOST_URL = 'UC_HOST_URL';
    const DICT_KEY_UC_APP_ID = 'UC_APP_ID';
    const DICT_KEY_UC_APP_SECRET = 'UC_APP_SECRET';

    /**
     * 消息队列设置
     */
    const DICT_KEY_NSQ_TOPIC_PREFIX = "NSQ_TOPIC_PREFIX";
    const DICT_KEY_NSQD_HOST = "NSQD_HOST";
    const DICT_KEY_NSQ_LOOKUPS = "NSQ_LOOKUPS";

    const MOBILE_REGEX = "/^[0-9]{1,14}$/";

    /**
     * 商品包
     */
    const DICT_GOODS_PACKAGE_STATUS = "goods_package_status";
    const DICT_GOODS_PACKAGE_IS_SHOW = "package_is_show";

    /**
     * 商品包中包含的商品个数
     */
    const DICT_TYPE_PACKAGE_INCLUDE_GOODS_NUM = "package_include_goods";
    const DICT_KEY_CODE_PACKAGE_INCLUDE_GOODS_NUM = 'GOODS_NUM';

    // 课程取消限制
    const DICT_KEY_CANCEL_SCHEDULE_LIMIT = 'cancel_schedule_limit';
    const DICT_KEY_SCHEDULE_CAN_NOT_LEAVE_LIMIT = 'SCHEDULE_CAN_NOT_LEAVE_LIMIT';
    const DICT_KEY_SCHEDULE_LEAVE_FREE_LIMIT = 'SCHEDULE_LEAVE_FREE_LIMIT';
    const DICT_KEY_SCHEDULE_LEAVE_FREE_COUNT_ONE_MONTH = 'SCHEDULE_LEAVE_FREE_COUNT_ONE_MONTH';

    // 商品包渠道
    const PACKAGE_CHANNEL_FROM = "package_channel_from";

    // 课程时长
    const COURSE_DURATION = "course_duration";

    /**
     * 订单中心（pay center）
     */
    const DICT_KEY_CODE_PAY_CENTER_HOST = 'PAY_CENTER_HOST';//订单中心地址前缀，如http://pc.xiaoyezi.com/pay/v1

    //曲谱书级别type
    const DICT_TYPE_BOOK_LEVEL = "book_level";

    // 设备课、体验课id
    const DICT_TYPE_TEST_OR_DEVICE_COURSE_ID = 'test_device_course_id';
    const DICT_KEY_PANDA_DEVICE_COURSE_ID = 'PANDA_DEVICE_COURSE_ID';
    const DICT_KEY_SQUIRREL_DEVICE_COURSE_ID = 'SQUIRREL_DEVICE_COURSE_ID';
    const DICT_KEY_PANDA_NORMAL_REGISTER = 'PANDA_NORMAL_REGISTER';
    const DICT_KEY_SQUIRREL_NORMAL_REGISTER = 'SQUIRREL_NORMAL_REGISTER';

    //role id 设置
    const DICT_TYPE_ROLE_ID = 'ROLE_ID';
    const DICT_KEY_CODE_CC_ROLE_ID_CODE = 'CC_ROLE_ID';
    const DICT_KEY_CODE_CA_ROLE_ID_CODE = 'CA_ROLE_ID';
    const DICT_KEY_CODE_TA_ROLE_ID_CODE = 'TA_ROLE_ID';
    const DICT_KEY_CODE_CC_ROLE_ID_CODE_ORG = 'CC_ROLE_ID_ORG'; //机构cc使用
    const DICT_KEY_CODE_PRINCIPAL_ROLE_ID_CODE = 'PRINCIPAL_ROLE_ID'; //机构校长角色

    // 声网录制地址
    const AGORA_RECORDER_URL = 'AGORA_RECORDER_URL';

    //教师标签类别
    const DICT_TYPE_TEACHER_TAG_TYPE = 'teacher_tags_type';
    const DICT_KEY_TEACHER_TAG_SUBJECTIVE = 'SUBJECTIVE';
    const DICT_KEY_TEACHER_TAG_OBJECTIVE = 'OBJECTIVE';
    // 课程类型转换时间片课程类型数组
    const DICT_TYPE_COURSE_TYPE_CONV = 'course_type_conv';
    // 排课开始时间
    const DICT_KEY_BASIC_SCHEDULE_START_TIME = 'basic_schedule_start_time';
    // 排课结束时间
    const DICT_KEY_BASIC_SCHEDULE_END_TIME = 'basic_schedule_end_time';

    const DICT_KEY_TEACHER_CAN_MODIFY_START_WEEK = 'teacher_can_modify_start_week';
    const DICT_KEY_TEACHER_CAN_MODIFY_START_HI = 'teacher_can_modify_start_hi';
    const DICT_KEY_TEACHER_CAN_MODIFY_END_WEEK = 'teacher_can_modify_end_week';
    const DICT_KEY_TEACHER_CAN_MODIFY_END_HI ='teacher_can_modify_end_hi';
    const DICT_KEY_TEACHER_CAN_REPEAT_WEEKS = 'teacher_can_repeat_weeks';

    const DICT_REFUND_STATUS = 'refund_status';
    const DICT_TR_ERROR = 'tr_error';
    //修改学生等级URI
    const DICT_MODIFY_STUDENT_LEVEL_URI = '/student/student/modify_student_level';

    //激活码相关
      //生成渠道
    const DICT_CODE_GENERATE_CHANNEL = 'generate_channel';
      //生成方式
    const DICT_CODE_GENERATE_WAY = 'generate_way';
      //状态
    const DICT_CODE_STATUS = 'code_status';
      //激活码其他购买人
    const DICT_CODE_OTHER_CHANNEL_BUYER = 'other_channel_buyer';
      //激活码时间单位
    const DICT_CODE_TIME_UNITS = 'valid_deadline';
    // 书籍权限
    const DICT_TYPE_BOOK_APP = 'book_app';

    // 阿里OSS配置
    const DICT_TYPE_ALIOSS_CONFIG = 'ALI_OSS_CONFIG';
    const DICT_KEY_ALIOSS_ACCESS_KEY_ID = "access_key_id";
    const DICT_KEY_ALIOSS_ACCESS_KEY_SECRET = "access_key_secret";
    const DICT_KEY_ALIOSS_BUCKET = "bucket";
    const DICT_KEY_ALIOSS_ENDPOINT = "endpoint";
    const DICT_KEY_ALIOSS_HOST = "host";
    const DICT_KEY_ALIOSS_CALLBACK_URL = "callback_url";
    const DICT_KEY_ALIOSS_EXPIRE = "expire";
    const DICT_KEY_ALIOSS_MAX_FILE_SIZE = "max_file_size";
    const DICT_KEY_ALIOSS_REGION_ID = "region_id";
    const DICT_KEY_ALIOSS_RECORD_FILE_ARN = "record_file_arn";

    //机构状态
    const DICT_TYPE_ORG_STATUS = 'org_status';
    //机构账号状态
    const DICT_TYPE_ORG_ACCOUNT_STATUS = 'org_account_status';
    //练习记录曲谱类型
    const DICT_TYPE_PLAY_RECORD_LESSON_TYPE = 'play_record_lesson_type';
};