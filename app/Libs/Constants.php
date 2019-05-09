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

    // 学生子状态
    const DICT_TYPE_SCHEDULE_STUDENT_STATUS = 'schedule_student_status';
    // 老师子状态
    const DICT_TYPE_SCHEDULE_TEACHER_STATUS = 'schedule_teacher_status';

    // JWT设置
    const DICT_KEY_JWT_ISSUER = 'JWT_ISSUER';
    const DICT_KEY_JWT_AUDIENCE = 'JWT_AUDIENCE';
    const DICT_KEY_JWT_EXPIRE = 'JWT_EXPIRE';
    const DICT_KEY_JWT_SIGNER_KEY = 'JWT_SIGNER_KEY';

    // JWT Token Type
    const DICT_KEY_TOKEN_TYPE_USER = 'TOKEN_TYPE_USER';

    // DICT 分页设置  key_code
    const DEFAULT_PAGE_LIMIT = 'DEFAULT_PAGE_LIMIT';

    // 正常废除
    const DICT_TYPE_NORMAL_OR_INVALID = 'normal_or_invalid';
    // 是否
    const DICT_TYPE_YES_OR_NO = 'yes_or_no_status';


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

    //应用类型
    const DICT_TYPE_APP_TYPE = 'app_type';

    // DICT  性别 type
    const DICT_TYPE_GENDER = 'gender';
    /**
     * 订单支付状态
     */
    const DICT_TYPE_BILL_PAY_STATUS = "bill_pay_status";

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

    const MOBILE_REGEX = "/^[0-9]{1,14}$/";

    // 课程时长
    const COURSE_DURATION = "course_duration";

    //role id 设置
    const DICT_TYPE_ROLE_ID = 'ROLE_ID';
    const DICT_KEY_CODE_CC_ROLE_ID_CODE_ORG = 'CC_ROLE_ID_ORG'; //机构cc使用
    const DICT_KEY_CODE_PRINCIPAL_ROLE_ID_CODE = 'PRINCIPAL_ROLE_ID'; //机构校长角色
    const DICT_KEY_CODE_DIRECT_PRINCIPAL_ROLE_ID_CODE = 'DIRECT_PRINCIPAL_ROLE_ID'; //直营校长角色

    // 声网录制地址
    const AGORA_RECORDER_URL = 'AGORA_RECORDER_URL';

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

    //机构状态
    const DICT_TYPE_ORG_STATUS = 'org_status';
    //机构账号状态
    const DICT_TYPE_ORG_ACCOUNT_STATUS = 'org_account_status';
    //练习记录曲谱类型
    const DICT_TYPE_PLAY_RECORD_LESSON_TYPE = 'play_record_lesson_type';
    //学生状态(table student column status)
    const DICT_TYPE_STUDENT_STATUS = 'student_status';
    //直营角色包含的org_id
    const DICT_TYPE_DIRECT_ORG_IDS = 'direct_org_ids';
    //学生订阅服务状态
    const DICT_TYPE_STUDENT_SUB_STATUS = 'student_sub_status';
    //订单是否进入学生账户
    const DICT_TYPE_BILL_IS_ENTER_ACCOUNT = 'bill_is_enter_account';
};