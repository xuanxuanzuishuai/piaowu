<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/10/25
 * Time: 12:30 PM
 */

namespace App\Services;

use App\Libs\APIValid;
use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\DictConstants;
use App\Libs\FTP;
use App\Libs\Qiniu;
use App\Libs\RedisDB;
use App\Libs\ResponseError;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppModel;
use App\Models\TeacherModel;
use App\Models\TeacherOrgModel;
use App\Services\Product\CourseService;

class TeacherService
{
    public static function saveAndUpdateTeacher($params)
    {
        // 必填参数
        $update['mobile'] = $params['mobile'] ?? '';
        $update['name']   = $params['name'] ?? '';

        // 可选参数
        $update['gender']               = empty($params['gender']) ? TeacherModel::GENDER_UNKNOWN : $params['gender'];
        $update['birthday']             = $params['birthday'] ?? null;
        $update['thumb']                = $params['thumb'] ?? '';
        $update['country_code']         = $params['country_code'] ?? '';
        $update['province_code']        = $params['province_code'] ?? '';
        $update['city_code']            = $params['city_code'] ?? '';
        $update['district_code']        = $params['district_code'] ?? '';
        $update['address']              = $params['address'] ?? '';
        $update['channel_id']           = empty($params['channel_id']) ? null : $params['channel_id'];
        $update['id_card']              = $params['id_card'] ?? '';
        $update['bank_card_number']     = $params['bank_card_number'] ?? '';
        $update['opening_bank']         = $params['opening_bank'] ?? '';
        $update['bank_reserved_mobile'] = $params['bank_reserved_mobile'] ?? null;
        $update['type']                 = empty($params['type']) ? null : $params['type'];
        $update['level']                = empty($params['level']) ? null : $params['level'];
        $update['start_year']           = empty($params['start_year']) ? null : $params['start_year'];
        $update['learn_start_year']     = empty($params['learn_start_year']) ? null : $params['learn_start_year'];
        $update['college_id']           = empty($params['college_id']) ? null : $params['college_id'];
        $update['major_id']             = empty($params['major_id']) ? null : $params['major_id'];
        $update['graduation_date']      = empty($params['graduation_date']) ? null : $params['graduation_date'];
        $update['education']            = empty($params['education']) ? null : $params['education'];
        $update['music_level']          = empty($params['music_level']) ? null : $params['music_level'];
        $update['teach_experience']     = $params['teach_experience'] ?? '';
        $update['prize']                = $params['prize'] ?? '';
        $update['teach_results']        = $params['teach_results'] ?? null;
        $update['teach_style']          = $params['teach_style'] ?? null;
        $update['status']               = empty($params['status']) ? TeacherModel::ENTRY_REGISTER : $params['status'];

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $userCenter = new UserCenter($appId, $appSecret);
        $auth = true;

        $authResult = $userCenter->teacherAuthorization($update['mobile'], $update['name'], '',
            $update['birthday'], strval($update['gender']), $update['thumb'], $auth);
        if (empty($authResult["uuid"])) {
            return Valid::addErrors([], "user_center", "uc_user_add_failed");
        }

        $uuid = $authResult['uuid'];
        $teacher = TeacherModel::getRecord([
            'uuid' => $uuid
        ],'*',false);

        if(empty($teacher)) {
            $update['uuid'] = $uuid;
            $update['update_time'] = time();
            $update['create_time'] = time();
            $teacherId = TeacherModel::insertRecord($update, false);
            if (empty($teacherId)) {
                return Valid::addErrors([], 'teacher', 'save_teacher_fail');
            }
        } else {
            $teacherId = $teacher['id'];
            $update['update_time'] = time();
            $affectRows = TeacherModel::updateRecord($teacherId, $update, false);
            if($affectRows == 0) {
                return Valid::addErrors([], 'teacher', 'update_teacher_fail');
            }
        }

        $modifyResult = $userCenter->modifyTeacher($uuid, $update['mobile'], $update['name'],
            $update['birthday'],strval($update['gender']));
        if(isset($modifyResult['code'])) {
            return $modifyResult; //已经用Valid::addErrors包装过
        }

        return [
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'id' => $teacherId
            ]
        ];
    }
    /**
     * 插入或更新老师数据
     * @param $teacherId
     * @param $params
     * @return int|mixed|null|string
     */
    public static function updateTeacher($teacherId, $params)
    {
        $update['mobile'] = $params['mobile'] ?? '';
        $update['name']   = $params['name'] ?? '';

        // 可选参数
        $update['gender']               = empty($params['gender']) ? TeacherModel::GENDER_UNKNOWN : $params['gender'];
        $update['birthday']             = $params['birthday'] ?? null;
        $update['thumb']                = $params['thumb'] ?? '';
        $update['country_code']         = $params['country_code'] ?? '';
        $update['province_code']        = $params['province_code'] ?? '';
        $update['city_code']            = $params['city_code'] ?? '';
        $update['district_code']        = $params['district_code'] ?? '';
        $update['address']              = $params['address'] ?? '';
        $update['channel_id']           = empty($params['channel_id']) ? null : $params['channel_id'];
        $update['id_card']              = $params['id_card'] ?? '';
        $update['bank_card_number']     = $params['bank_card_number'] ?? '';
        $update['opening_bank']         = $params['opening_bank'] ?? '';
        $update['bank_reserved_mobile'] = $params['bank_reserved_mobile'] ?? null;
        $update['type']                 = empty($params['type']) ? null : $params['type'];
        $update['level']                = empty($params['level']) ? null : $params['level'];
        $update['start_year']           = empty($params['start_year']) ? null : $params['start_year'];
        $update['learn_start_year']     = empty($params['learn_start_year']) ? null : $params['learn_start_year'];
        $update['college_id']           = empty($params['college_id']) ? null : $params['college_id'];
        $update['major_id']             = empty($params['major_id']) ? null : $params['major_id'];
        $update['graduation_date']      = empty($params['graduation_date']) ? null : $params['graduation_date'];
        $update['education']            = empty($params['education']) ? null : $params['education'];
        $update['music_level']          = empty($params['music_level']) ? null : $params['music_level'];
        $update['teach_experience']     = $params['teach_experience'] ?? '';
        $update['prize']                = $params['prize'] ?? '';
        $update['teach_results']        = $params['teach_results'] ?? null;
        $update['teach_style']          = $params['teach_style'] ?? null;

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $userCenter = new UserCenter($appId, $appSecret);

        $teacher = TeacherModel::getById($teacherId);
        if (empty($teacher)) {
            return Valid::addErrors([], 'teacher', 'teacher_is_not_exist');
        }

        $update['uuid'] = $teacher['uuid'];
        $update['update_time'] = time();

        $affectRows = TeacherModel::updateRecord($teacherId, $update, false);
        if ($affectRows == 0) {
            return Valid::addErrors([], 'teacher', 'update_teacher_fail');
        }

        $update['gender'] = empty($update['gender']) ? TeacherModel::GENDER_UNKNOWN : strval($update['gender']);

        $modifyResult = $userCenter->modifyTeacher($update['uuid'], $update['mobile'], $update['name'],
            $update['birthday'],strval($update['gender']));

        if(isset($modifyResult['code'])) {
            return $modifyResult; //已经用Valid::addErrors包装过
        }

        return [
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'id' => $teacherId
            ]
        ];
    }

    /**
     * 获取老师列表
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function getList($orgId, $page, $count, $params)
    {
        $ta_role_id = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, 'TA_ROLE_ID');
        list($teachers, $totalCount) = TeacherModel::getTeacherList($orgId, $page, $count, $params, $ta_role_id);

        foreach($teachers as &$t) {
            $t['status']    = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_STATUS, $t['status']);
            $t['gender']    = DictService::getKeyValue(Constants::DICT_TYPE_GENDER, $t['gender']);
            $t['type']      = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_TYPE, $t['type']);
            $t['level']     = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_LEVEL, $t['level']);
            $t['education'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_EDUCATION, $t['education']);
        }

        return [$teachers, $totalCount];
    }

    /**
     * 根据老师ID获取老师的详细信息
     * @param $teacher_id
     * @return mixed
     */
    public static function getTeacherInfoByID($teacher_id)
    {
        //获取老师的基础信息
        $teacher_info = TeacherModel::getById($teacher_id);
        if (empty($teacher_info)) {
            return Valid::addErrors([], 'teacher_info', 'teacher_is_not_exist');
        }

        $time = time();
        //获取老师对应的扩展信息
        if (!empty($teacher_info['start_year'])) {
            $teacher_info['teacher_years'] = date('Y', $time) - $teacher_info['start_year'];
        } else {
            $teacher_info['teacher_years'] = '';
        }
        if (!empty($teacher_info['learn_start_year'])) {
            $teacher_info['learn_years'] = date('Y', $time) - $teacher_info['learn_start_year'];
        } else {
            $teacher_info['learn_years'] = '';
        }
        if (!empty($teacher_info['channel_id'])) {
            //$teacher_info['channel_name'] = TeacherChannelModel::getById($teacher_info['channel_id']);
        } else {
            $teacher_info['channel_name'] = '';
        }
        //取出详细地址
        if (!empty($teacher_info['province_code']) && empty($teacher_info['country_code'])) {
            $teacher_info['country_code'] = '100000';
        }
        $country = !empty($teacher_info['country_code']) ? AreaService::getByCode($teacher_info['country_code'])['name'] : '';
        $province = !empty($teacher_info['province_code']) ? AreaService::getByCode($teacher_info['province_code'])['name'] : '';
        $city = !empty($teacher_info['city_code']) ? AreaService::getByCode($teacher_info['city_code'])['name'] : '';
        $district = !empty($teacher_info['district_code']) ? AreaService::getByCode($teacher_info['district_code'])['name'] : '';
        $teacher_info['area_full'] = $country . $province . $city . $district;

//        $teacher_info['college'] = !empty($teacher_info['college_id']) ? TeacherCollegeModel::getById($teacher_info['college_id']) : '';
//        $teacher_info['major'] = !empty($teacher_info['major_id']) ? TeacherMajorModel::getById($teacher_info['major_id']) : '';
//        $teacher_info['zoom'] = TeacherZoomService::getRecordByTeacherID($teacher_info['id']);
//        $teacher_info['teacher_app_extend'] = TeacherAppExtendService::getAppAndEmployeeExtendInfoByTeacherID($teacher_info['id']);
//        $teacher_info['tag_info'] = TeacherTagRelationsService::getTagInfoByTeacherID($teacher_info['id']);
//        $teacher_info['type_name'] = !empty($teacher_info['type']) ? DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_TYPE, $teacher_info['type']) : '';
        $teacher_info['level_name'] = !empty($teacher_info['level']) ? DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_LEVEL, $teacher_info['level']) : '';
        $teacher_info['music_level_name'] = !empty($teacher_info['music_level']) ? DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL, $teacher_info['music_level']) : '';
        $teacher_info['status_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_STATUS, $teacher_info['status']);
        $teacher_info['birthday'] = !empty($teacher_info['birthday']) ? str_pad($teacher_info['birthday'], 8, "0", STR_PAD_RIGHT) : '';
//        $teacher_operate_log = TeacherOperateLogModel::getRecordByTeacher($teacher_id);
//        if (!empty($teacher_operate_log)) {
//            $teacher_info['status_remark'] = $teacher_operate_log['remark'];
//        } else {
//            $teacher_info['status_remark'] = '';
//        }
        $teacher_info['education_name'] = !empty($teacher_info['education']) ? DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_EDUCATION, $teacher_info['education']) : '';
        //$teacher_info['teacher_leave'] = TeacherLeaveService::getEffectLeave($teacher_id);
        if (!empty($teacher_info['graduation_date'])) {
            $teacher_info['is_graduate'] = (date('Ym') - $teacher_info['graduation_date']) >= 0 ? "是" : "否";
        } else {
            $teacher_info['is_graduate'] = "";
        }
        // 老师头像
        $teacher_info['thumb'] = Util::getQiNiuFullImgUrl($teacher_info['thumb']);
        // 教师图片和视频
        //list($teacher_info['brief_image'], $teacher_info['brief_video']) = TeacherBriefIntroductionModel::getTeacherImageVideoById($teacher_id);
        return $teacher_info;
    }

    protected static function importError($data_csv, $key, $number, $value)
    {
        $data_csv['error'] = 1;
        $data_csv[$key]['error'] = 1;
        unset($data_csv[$key][$number]);
        $data_csv[$key][$number]['error'] = 1;
        $data_csv[$key][$number]['message'] = $value[$number];
        return $data_csv;
    }

    /**
     * 老师导入app_extend 处理
     * @param $app_extend
     * @param $evaluate_level
     * @param $teacher_evaluate_app_id_panda
     * @param $error_message
     * @return array|bool
     */
    public static function importTeacherApp($app_extend, $evaluate_level, $teacher_evaluate_app_id_panda, $error_message)
    {
        $teacher_app = [];
        //将中文逗号替换陈英文逗号
        $app_str = str_replace('，', ',', $app_extend);
        $app_arr = explode(',', $app_str);
        foreach ($app_arr as $k => $v) {
            $app_extend = explode('-', $v);
            $app_info = AppModel::getRecordByName($app_extend[0]);
            if (empty($app_info['id'])) {
                $error_message['column_number'] = 10;
                $result = Valid::addErrors(["error_message" => $error_message], 'teacher', 'teacher_app_format_is_error');
                return $result;
            }
            $teacher_app[$k]['app_id'] = $app_info['id'];
            //体验课还是正式课的处理（钢琴陪练-体验课-正式课||钢琴陪练-体验课-||钢琴陪练--正式课）
            $ts_course_type = [0, 0];
            if (!empty($app_extend[1])) {
                if ($app_extend[1] == TeacherModel::COURSE_EXPERIENCE_NAME) {
                    $ts_course_type[0] = 1;
                } else {
                    $error_message['column_number'] = 10;
                    $result = Valid::addErrors(["error_message" => $error_message], 'teacher', 'teacher_app_format_is_error');
                    return $result;
                }
            }
            if (!empty($app_extend[2])) {
                if ($app_extend[2] == TeacherModel::COURSE_FORMAL_NAME) {
                    $ts_course_type[1] = 1;
                } else {
                    $error_message['column_number'] = 10;
                    $result = Valid::addErrors(["error_message" => $error_message], 'teacher', 'teacher_app_format_is_error');
                    return $result;
                }
            }
            $teacher_app[$k]['ts_course_type'] = implode("", $ts_course_type);
            //评价级别
            if (!empty($evaluate_level)) {
                if ($app_info['id'] == AppModel::APP_PANDA) {
                    $result = self::checkDictType($teacher_evaluate_app_id_panda, $evaluate_level);
                    if (isset($result['key_code'])) {
                        $teacher_app[$k]["evaluate_level"] = $result['key_code'];
                    } else {
                        $error_message['column_number'] = 12;
                        $result = Valid::addErrors(["error_message" => $error_message], 'teacher', 'teacher_evaluate_format_is_error');
                        return $result;
                    }
                } else {
                    $error_message['column_number'] = 12;
                    $result = Valid::addErrors(["error_message" => $error_message], 'teacher', 'teacher_evaluate_format_is_error');
                    return $result;
                }
            }
        }
        return $teacher_app;
    }

    /**
     * 验证dict类型并返回键值对
     * @param $dict_arr
     * @param $name
     * @return bool
     */
    public static function checkDictType($dict_arr, $name)
    {
        foreach ($dict_arr as $value) {
            if ($value['key_value'] == $name) {
                return $value;
            }
        }
        return false;
    }

    /**
     * 获取teacher基础信息
     * @param $teacherId
     * @return mixed|null
     */
    public static function getTeacherById($teacherId)
    {
        return TeacherModel::getById($teacherId);
    }

    /**
     * 模糊搜索老师信息
     * @param $keyword
     * @return array
     */
    public static function fuzzySearch($keyword)
    {
        return TeacherModel::searchByKeyword($keyword);
    }

    /**
     * 获取在职教师
     * @return array
     */
    public static function getOnJobTeachers()
    {
        return TeacherModel::getOnJobTeachers();
    }

    /**
     * 绑定老师和机构，数据库操作失败时才返回错误，已经绑定不会返回错误，正确时候返回操作行的id
     * @param $orgId
     * @param $teacherId
     * @return ResponseError|int|mixed|null|string
     */
    public static function bindOrg($orgId, $teacherId)
    {
        $record = TeacherOrgModel::getRecord([
            'org_id'     => $orgId,
            'teacher_id' => $teacherId,
        ]);

        if(empty($record)) {
            $lastId = TeacherOrgModel::insertRecord([
                'teacher_id'  => $teacherId,
                'org_id'      => $orgId,
                'status'      => TeacherOrgModel::STATUS_NORMAL,
                'update_time' => time(),
                'create_time' => time(),
            ], false);
            if(empty($lastId)) {
                return new ResponseError('save_teacher_org_fail');
            }
            return $lastId;
        } else {
            if($record['status'] != TeacherOrgModel::STATUS_NORMAL) {
                $affectRows = TeacherOrgModel::updateRecord($record['id'],[
                    'status' => TeacherOrgModel::STATUS_NORMAL
                ]);
                if($affectRows == 0) {
                    return new ResponseError('update_teacher_org_status_fail');
                }
                return $record['id'];
            }
            return $record['id'];
        }
    }

    /**
     * 查询指定机构下老师，不区分状态
     * @param $orgId
     * @param $teacherId
     * @return array|null
     */
    public static function getOrgTeacherById($orgId, $teacherId)
    {
        return TeacherModel::getOrgTeacherById($orgId, $teacherId);
    }

    /**
     * 更新老师和机构的绑定状态
     * @param $orgId
     * @param $teacherId
     * @param $status
     * @return int|null
     */
    public static function updateStatusWithOrg($orgId, $teacherId, $status)
    {
        return TeacherOrgModel::updateStatus($orgId, $teacherId, $status);
    }

    public static function getTeacherByIds($tIds) {
        return TeacherOrgModel::getRecords(['teacher_id'=>$tIds,'status'=>TeacherOrgModel::STATUS_NORMAL]);
    }
}