<?php

namespace App\Libs;

use App\Models\CourseModel;
use App\Services\DictService;

/**
 * 字典
 */
class Dict
{

    /**
     * 返回是否
     * @param $status
     * @return string
     */
    public static function isOrNotStr($status)
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_YES_OR_NO, $status);
    }

    /**
     * 课程计划的状态
     * @param $status
     * @return mixed
     */
    public static function scheduleStatusStr($status)
    {
        if (empty($status)) {
            return '';
        }
        return DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STATUS, $status);
    }

    /**
     * 课程计划的类型
     * @param $type
     * @return mixed
     */
    public static function courseTypeStr($type)
    {
        if (empty($type)) {
            return '';
        }
        return DictService::getKeyValue(Constants::DICT_TYPE_COURSE_TYPE, $type);
    }

    /**
     * 返回正常、废除
     * @param $status
     * @return string
     */
    public static function normalOrInvalidStr($status)
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_NORMAL_OR_INVALID, $status);
    }

    /**
     * 返回系统默认分页条数
     * @return mixed
     */
    public static function getDefaultPageLimit()
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_SYSTEM_ENV, Constants::DEFAULT_PAGE_LIMIT);
    }

    /**
     * 获取性别
     * @param $id
     * @return mixed
     */
    public static function getGender($id)
    {
        if (empty($id)) {
            return '';
        }
        return DictService::getKeyValue(Constants::DICT_TYPE_GENDER, $id);
    }

    /**
     * 学生子状态
     * @param $status
     * @return mixed
     */
    public static function scheduleStudentStatusStr($status)
    {
        if (empty($status)) {
            return '';
        }
        return DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STUDENT_STATUS, $status);
    }

    /**
     * 老师子状态
     * @param $status
     * @return mixed
     */
    public static function scheduleTeacherStatusStr($status)
    {
        if (empty($status)) {
            return '';
        }
        return DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_TEACHER_STATUS, $status);
    }

    /**
     * 获取体验课、设备课id
     * @param $keyCode
     * @return mixed
     */
    public static function getCourseId($keyCode)
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_TEST_OR_DEVICE_COURSE_ID, $keyCode);
    }

    /**
     * 课程操作类型
     * @param $type
     * @return mixed
     */
    public static function scheduleOperationType($type)
    {
        if (empty($type)) {
            return '';
        }
        return DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_LOG_TYPE, $type);
    }

    /**
     * 获取课程网络监控地址
     * @param $scheduleId
     * @return string
     */
    public static function getNetstatUrl($scheduleId)
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_SYSTEM_ENV, Constants::DICT_KEY_SCHEDULE_STAT_URL) . $scheduleId;
    }

    /**
     * 获取学生等级
     * @param $level
     * @return mixed
     */
    public static function getStudentLevel($level)
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_LEVEL, $level);
    }

    /**
     * 获取老师等级
     * @param $level
     * @return mixed
     */
    public static function getTeacherLevel($level)
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_LEVEL, $level);
    }

    /**
     * 课包操作日志
     * @param $type
     * @return mixed
     */
    public static function studentCourseLogType($type)
    {
        if (empty($type)) {
            return '';
        }
        return DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_COURSE_LOG_TYPE, $type);
    }

    /**
     * 老师时间操作类型
     * @param $type
     * @return mixed
     */
    public static function getTSLogType($type)
    {
        if (empty($type)) {
            return '';
        }
        return DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_SCHEDULE_LOG_TYPE, $type);
    }

    /**
     * 老师时间操作人
     * @param $type
     * @return mixed
     */
    public static function getTSOperator($type)
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_SCHEDULE_OPERATOR_TYPE, $type);
    }

    /**
     * 获取老师授课类型
     * @param $type
     * @return mixed
     */
    public static function getTSCourseType($type)
    {
        if (empty($type)) {
            return '';
        }
        return DictService::getKeyValue(Constants::DICT_TYPE_TS_COURSE_TYPE, $type);
    }

  /**
   * 获取激活码生成渠道
   * @param $channel
   * @return mixed
   */
  public static function getCodeChannel($channel)
  {
    if (empty($channel)) {
      return '';
    }
    return DictService::getKeyValue(Constants::DICT_CODE_GENERATE_CHANNEL, $channel);
  }

  /**
   * 获取激活码生成方式
   * @param $way
   * @return mixed
   */
  public static function getCodeWay($way)
  {
    if (empty($way)) {
      return '';
    }
    return DictService::getKeyValue(Constants::DICT_CODE_GENERATE_WAY, $way);
  }

  /**
   * 获取激活码状态
   * @param $status
   * @return mixed
   */
  public static function getCodeStatus($status)
  {
    return DictService::getKeyValue(Constants::DICT_CODE_STATUS, $status);
  }

  /**
   * 获取激活码其他状态的值
   * @param $channel
   * @return mixed
   */
  public static function getCodeOtherChannelBuyer($channel)
  {
    return DictService::getKeyValue(Constants::DICT_CODE_OTHER_CHANNEL_BUYER, $channel);
  }

  /**
   * 获取激活码的时间单位
   * @param $unit
   * @return mixed
   */
  public static function getCodeTimeUnit($unit)
  {
    return DictService::getKeyValue(Constants::DICT_CODE_TIME_UNITS, $unit);
  }

  public static function getOrgCCRoleId()
  {
      return DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_CC_ROLE_ID_CODE_ORG);
  }

  public static function getPrincipalRoleId()
  {
      return DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_PRINCIPAL_ROLE_ID_CODE);
  }
}
