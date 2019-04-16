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
use App\Libs\FTP;
use App\Libs\Qiniu;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppModel;
use App\Models\TeacherModel;
use App\Services\Product\CourseService;

class TeacherService
{
    /**
     * 插入或更新老师数据
     * @param $params
     * @param $operator
     * @return int|mixed|null|string
     */
    public static function insertOrUpdateTeacher($params, $operator)
    {
        $teacher_id = $params['id'] ?? '';
        $update['mobile'] = $params['mobile'] ?? '';
        $update['name'] = $params['name'] ?? '';

        //判断其他可选填参数是否有填写
        $update['gender'] = empty($params['gender']) ? TeacherModel::GENDER_UNKNOWN : $params['gender'];
        $update['birthday'] = $params['birthday'] ?? null;
        $update['thumb'] = $params['thumb'] ?? '';
        $update['country_code'] = $params['country_code'] ?? '';
        $update['province_code'] = $params['province_code'] ?? '';
        $update['city_code'] = $params['city_code'] ?? '';
        $update['district_code'] = $params['district_code'] ?? '';
        $update['address'] = $params['address'] ?? '';
        $update['channel_id'] = empty($params['channel_id']) ? null : $params['channel_id'];
        $update['id_card'] = $params['id_card'] ?? '';
        $update['bank_card_number'] = $params['bank_card_number'] ?? '';
        $update['opening_bank'] = $params['opening_bank'] ?? '';
        $update['bank_reserved_mobile'] = $params['bank_reserved_mobile'] ?? null;
        $update['type'] = empty($params['type']) ? null : $params['type'];
        $update['level'] = empty($params['level']) ? null : $params['level'];
        $update['start_year'] = empty($params['start_year']) ? null : $params['start_year'];
        $update['learn_start_year'] = empty($params['learn_start_year']) ? null : $params['learn_start_year'];
        $update['college_id'] = empty($params['college_id']) ? null : $params['college_id'];
        $update['major_id'] = empty($params['major_id']) ? null : $params['major_id'];
        $update['graduation_date'] = empty($params['graduation_date']) ? null : $params['graduation_date'];
        $update['education'] = empty($params['education']) ? null : $params['education'];
        $update['music_level'] = empty($params['music_level']) ? null : $params['music_level'];
        $update['teach_experience'] = $params['teach_experience'] ?? '';
        $update['prize'] = $params['prize'] ?? '';
        $update['teach_results'] = $params['teach_results'] ?? null;
        $update['teach_style'] = $params['teach_style'] ?? null;
        $update['status'] = empty($params['status']) ? 1 : $params['status'];

        // 用户中心处理
        $userCenter = new UserCenter();
        $auth = true;

        $zoom_name = $params['zoom_name'] ?? null;
        $zoom_pwd = $params['zoom_pwd'] ?? null;
        $zoom_id = $params['zoom_id'] ?? null;
        // 图片简介或视频简介
        $briefImage = !empty($params['brief_image']) ? $params['brief_image'] : null;
        $briefVideo = !empty($params['brief_video']) ? $params['brief_video'] : null;

        //如果 teacher_id 为空，验证手机号是否存在，如果存在，并且为注册状态，更新老师信息，并标记为待入职状态
        if (empty($teacher_id)) {
            $teacher_base_info = TeacherModel::getRecordByMobile($update['mobile']);
            if (!empty($teacher_base_info) && $teacher_base_info['status'] == TeacherModel::ENTRY_REGISTER) {
                $teacher_id = $teacher_base_info['id'];
                $update['status'] = TeacherModel::ENTRY_WAIT;
            }
        }

        if (empty($teacher_id)) {
            //验证手机号是否已存在
            if (TeacherModel::isMobileExist($update['mobile'])) {
                return Valid::addErrors([], 'mobile', 'teacher_mobile_is_exist');
            }
            $uuid = $params['uuid'] ?? '';
            $authResult = $userCenter->teacherAuthorization($update['mobile'], $update['name'], $uuid, $update['birthday'], $update['gender'], $update['thumb'], $auth);
            if (empty($authResult["uuid"])) {
                return Valid::addErrors([], "user_center", "uc_user_add_failed");
            }
            $update['uuid'] = $authResult['uuid'];
            $update['create_time'] = time();

            $teacher_id = TeacherModel::insertRecord($update);
            if ($teacher_id == false) {
                return Valid::addErrors([], 'teacher', 'teacher_add_error');
            }

//            // 图片简介和视频简介
//            if (!empty($briefImage) || !empty($briefVideo)) {
//                // 添加教师简介（图片+视频）
//                $briefResult = self::addOrUpdateTeacherBriefIntroduction($briefImage, $briefVideo, $teacher_id);
//                if ($briefResult['code'] != Valid::CODE_SUCCESS) {
//                    return $briefResult;
//                }
//            }

        } else {
            //验证数据是否存在
            $teacher_info = TeacherModel::getById($teacher_id);
            if (empty($teacher_info['id'])) {
                return Valid::addErrors([], 'teacher', 'teacher_is_not_exist');
            }
            //编辑时验证手机号是否存在
            $exist_teacher_info = TeacherModel::getRecordByMobile($update['mobile']);
//            if (!empty($exist_teacher_info) && $exist_teacher_info['id'] != $teacher_id) {
//                // 注册、待入职、不入职 状态询问是否删除
//                if (in_array($exist_teacher_info['status'],
//                    array(TeacherModel::ENTRY_REGISTER, TeacherModel::ENTRY_WAIT, TeacherModel::ENTRY_NO))){
//                    if ($params['dup_del_confirm'] == '1'){ // 确认删除目标手机号用户
//                        // 物理删除，记录日志
//                        SimpleLogger::warning('物理删除老师信息：', $exist_teacher_info);
//                        // 添加老师操作记录
//                        $remark = '变更手机号: ' . $teacher_info['mobile'] . '=>' . $update['mobile'] . ' 同时删除重复数据: [id:' .
//                            $exist_teacher_info['id'] . ' 姓名:' . $exist_teacher_info['name'] . ' 状态:' .
//                            DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_STATUS, $exist_teacher_info['status']) .
//                            ' UUID:' . $exist_teacher_info['uuid'] . ']';
//                        //TeacherOperateLogService::insertData($teacher_id, $operator['id'], $teacher_info['status'],$remark);
//                        // 用户中心解绑老师
//                        $uc = new UserCenter();
//                        $uc->teacherUnauthorization($exist_teacher_info['uuid']);
//                        // 删除未入职老师
//                        TeacherModel::deleteNotEntryTeacher($exist_teacher_info);
//                    }else{
//                        return [
//                            'code' => Valid::CODE_CONFIRM,
//                            'data' => [
//                                'teacher_id' => $exist_teacher_info['id'],
//                                'mobile' => $exist_teacher_info['mobile'],
//                                'name' => $exist_teacher_info['name'],
//                                'status' => $exist_teacher_info['status'],
//                                'status_name' => DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_STATUS, $exist_teacher_info['status'])
//                            ]
//                        ];
//                    }
//
//                }else{
//                    // 在职、冻结、离职、辞退状态不允许删除
//                    return Valid::addErrors([], 'teacher', 'teacher_mobile_is_exist');
//                }
//            }
//            $authResult = $userCenter->modifyTeacher($teacher_info['uuid'], $update['mobile'],
//                $update['name'], $update['birthday'], $update['gender'], $update['thumb']);
//            if (empty($authResult["uuid"])) {
//                return Valid::addErrors([], "user_center", "uc_user_add_failed");
//            }
//            if ($auth) {
//                $userCenter->teacherAuthorization("", "", $authResult['uuid']);
//            } else {
//                $userCenter->teacherUnauthorization($authResult['uuid']);
//            }

//            //入职编辑
//            if ($teacher_info['status'] == TeacherModel::ENTRY_WAIT && $update['status'] == TeacherModel::ENTRY_ON) {
//                $update['first_entry_time'] = time();
//            }

            $update['uuid'] = $teacher_info['uuid'];

            $result = TeacherModel::updateRecord($teacher_id, $update);
            if (!is_numeric($result)) {
                return Valid::addErrors([], 'teacher', 'teacher_update_error');
            }
//            // 图片简介和视频简介
//            if (!empty($briefImage) || !empty($briefVideo)) {
//                // 编辑教师简介（图片+视频）
//                $briefResult = self::addOrUpdateTeacherBriefIntroduction($briefImage, $briefVideo, $teacher_id, true);
//                if ($briefResult['code'] != Valid::CODE_SUCCESS) {
//                    return $briefResult;
//                }
//            }
//            // 先添加图片或视频，后编辑时全部删除的情况
//            $updateResult = self::updateImageOrVideoStatus($teacher_id, $params, $briefImage, $briefVideo);
//            if ($updateResult['code'] != Valid::CODE_SUCCESS) {
//                return $updateResult;
//            }
        }

//        //判断zoom三个值有一个不为空，其他均不能为空
//        if ($zoom_name !== null && $zoom_id !== null && $zoom_pwd !== null) {
//            $zoom_num = TeacherZoomService::insertOrUpdate($teacher_id, $zoom_name, $zoom_pwd, $zoom_id);
//            if (!is_numeric($zoom_num)) {
//                return Valid::addErrors([], 'teacher', 'teacher_zoom_add_error');
//            }
//        } elseif ($zoom_name === null && $zoom_id === null && $zoom_pwd === null) {
//            //nothing to do!!
//        } else {
//            return Valid::addErrors([], 'teacher', 'teacher_zoom_parameter_error');
//        }

//        //提交所属课程的数据
//        if (!empty($params['teacher_app_extend'])) {
//            $teacher_product_extend = $params['teacher_app_extend'];
//            $app_extend = TeacherAppExtendService::insertOrUpdate($teacher_id,$teacher_product_extend, $operator);
//            if (!$app_extend) {
//                return Valid::addErrors([], 'teacher', 'teacher_app_extend_add_error');
//            }
//        }

//        //提交标签组数据
//        if (!empty($params['tag_ids'])) {
//            $teacher_tags = $params['tag_ids'];
//            $result = TeacherTagRelationsService::insertOrUpdate($teacher_id, $teacher_tags);
//            if (!$result) {
//                return Valid::addErrors([], 'teacher', 'teacher_tag_add_error');
//            }
//        }

        return [
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'id' => $teacher_id
            ]
        ];
    }

    /**
     * 更新图片或视频的状态
     * 适用于先添加图片或视频，后编辑时全部删除的情况
     * @param $teacherId
     * @param $params
     * @param null $briefImage
     * @param null $briefVideo
     * @return array
     */
    public static function updateImageOrVideoStatus($teacherId, $params, $briefImage = null, $briefVideo = null) {
        // 添加图片后，全部删除的情况
        $isImageDelete = !empty($params['image_delete']) ? true : false;
        // 添加视频后，全部删除的情况
        $isVideoDelete = !empty($params['video_delete']) ? true : false;
        // 先添加图片，后全部删除的情况
        if (empty($briefImage) && $isImageDelete) {
            $imageUpdate = TeacherBriefIntroductionModel::updateStatusByTeacherId($teacherId, true);
            if ($imageUpdate === null){
                $result = Valid::addErrors([], 'teacher_brief_image_update_error', 'teacher_brief_image_update_error');
                return $result;
            }
        }
        // 先添加视频，后全部删除的情况
        if (empty($briefVideo) && $isVideoDelete) {
            $videoUpdate = TeacherBriefIntroductionModel::updateStatusByTeacherId($teacherId, false);
            if ($videoUpdate === null){
                $result = Valid::addErrors([], 'teacher_brief_video_update_error', 'teacher_brief_video_update_error');
                return $result;
            }
        }
        return [
            'code'=>Valid::CODE_SUCCESS,
            'data'=>[]
        ];
    }

    /**
     * 更新老师状态信息
     * @param $teacherId
     * @param $operator
     * @param $params
     * @param $teacher_info
     * @return array|bool|mixed
     */
    public static function updateStatus($teacherId, $operator, $params, $teacher_info){
        $status = $params['status'];
        $params['remark'] = $params['remark'] ?? '';
        //判断是否是首次入职
        if ($teacher_info['first_entry_time']) {
            $update = [
                "status" => $status
            ];
        } else {
            $update = [
                "status" => $status,
                "first_entry_time" => time()
            ];
        }
        //判断如果修改的状态是【5离职】、【6辞退】时，需将已释放或已锁定的时间片标记为删除
        if ($status == TeacherModel::ENTRY_LEAVE || $status == TeacherModel::ENTRY_DISMISS) {
            //查看是否存在已释放未预约的时间片
            $release_time = TeacherScheduleEndService::teacherDismiss($teacherId, $operator);
            if (isset($release_time['code']) && $release_time['code'] != Valid::CODE_SUCCESS) {
                return Valid::addErrors([], 'status', 'teacher_status_modify_error');
            }
        }elseif($status == TeacherModel::ENTRY_FROZEN){
            //将教师已释放时间片设置为冻结
            $release_time = TeacherScheduleEndService::teacherScheduleFrozen($teacherId, $operator);
            if (isset($release_time['code']) && $release_time['code'] != Valid::CODE_SUCCESS) {
                return Valid::addErrors([], 'status', 'teacher_status_modify_error');
            }
        }elseif($status == TeacherModel::ENTRY_ON){
            //将教师设置为入职状态，要检查是否原状态为冻结，如果状态为冻结，需要将冻结的时间片设置为正常
            $teacher = self::getTeacherById($teacherId);
            if($teacher['status'] == TeacherModel::ENTRY_FROZEN){
                //变更教师时间片
                $release_time = TeacherScheduleEndService::teacherScheduleUnFrozen($teacherId, $operator);
                if (isset($release_time['code']) && $release_time['code'] != Valid::CODE_SUCCESS) {
                    return Valid::addErrors([], 'status', 'teacher_status_modify_error');
                }
            }
        }

        //记录操作记录
        $result_log = TeacherOperateLogService::insertData($teacherId, $operator['id'], $status, $params['remark']);
        if ($result_log == false) {
            return Valid::addErrors([], 'status', 'teacher_status_modify_error');
        }

        //更新老师信息
        $result_update = TeacherModel::updateRecord($teacherId, $update);
        if ($result_update == false) {
            return Valid::addErrors([], 'status', 'teacher_status_modify_error');
        }
        return [
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'update_count' => $result_update
            ]
        ];
    }

    /**
     * 更新教师状态前检查
     * @param $teacher_id
     * @param $params
     * @return array
     */
    public static function checkBeforeUpdateStatus($teacher_id, $params)
    {
        $status = $params['status'];

        //待入职更改为不入职或待入职 备注可不填写，其他必填
        if ($params['status'] != TeacherModel::ENTRY_NO && $params['status'] != TeacherModel::ENTRY_WAIT) {
            if (empty($params['remark'])) {
                $result = Valid::addErrors([], 'status', 'teacher_remark_is_required');
                return $result;
            }
        }

        $teacher_info = TeacherModel::getById($teacher_id);
        //获取老师对应的产品扩展信息
        $teacher_info['teacher_app_extend'] = TeacherAppExtendService::getRecordByTeacherID($teacher_id);
//        $teacher_info['tags'] = TeacherTagRelationsService::getRecordByTeacherID($teacher_id);

        //当操作【2在职】时判断其他必填参数是否已填写
        if ($status == TeacherModel::ENTRY_ON) {
            if (!$teacher_info['name'] || !$teacher_info['mobile'] || $teacher_info['teacher_app_extend'][0]['ts_course_type'] == "00") {
                $result = Valid::addErrors([], 'status', 'teacher_info_is_not_perfect');
                return $result;
            }
        }
//        if ($status == TeacherModel::ENTRY_ON){
//            if (!$teacher_info['name'] || !$teacher_info['mobile'] || !$teacher_info['gender'] || !$teacher_info['birthday'] ||
//                !$teacher_info['thumb'] || !$teacher_info['country_code'] || !$teacher_info['address'] || !$teacher_info['id_card'] ||
//                !$teacher_info['bank_card_number'] || !$teacher_info['opening_bank'] || !$teacher_info['teacher_app_extend'][0]['app_id'] ||
//                !$teacher_info['teacher_app_extend'][0]['evaluate_level'] || !$teacher_info['teacher_app_extend'][0]['employee_id'] ||
//                $teacher_info['teacher_app_extend'][0]['course_type'] == "00" ||
//                !$teacher_info['type'] || !$teacher_info['level'] || !$teacher_info['college_id'] || !$teacher_info['graduation_date'] ||
//                !$teacher_info['teach_experience'] || !$teacher_info['teach_style'] || empty($teacher_info['tags'])){
//                $result = Valid::addErrors([], 'status', 'teacher_info_is_not_perfect');
//                return $result;
//            }
//        }

        //判断传入的状态值是否合法
        $now_status = $teacher_info['status'];

        /**
         * 老师状态信息描述：
         * 1.当前老师状态是【2待入职】或【7不入职】时，可操作状态只能是【2待入职】、【3在职】、【7不入职】
         * 2.当前老师状态是【3在职】、【4冻结】、【5离职】、【6辞退】时，可操作状态只能是【3在职】、【4冻结】、【5离职】、【6辞退】
         */
        if ($now_status == TeacherModel::ENTRY_WAIT || $now_status == TeacherModel::ENTRY_NO) {
            if ($status != TeacherModel::ENTRY_ON && $status != TeacherModel::ENTRY_NO && $status != TeacherModel::ENTRY_WAIT) {
                return Valid::addErrors([], 'status', 'teacher_status_value_error');
            }
        } elseif ($now_status == TeacherModel::ENTRY_ON || $now_status == TeacherModel::ENTRY_FROZEN ||
            $now_status == TeacherModel::ENTRY_LEAVE || $now_status == TeacherModel::ENTRY_DISMISS) {
            if ($status == TeacherModel::ENTRY_NO && $status == TeacherModel::ENTRY_WAIT) {
                return Valid::addErrors([], 'status', 'teacher_status_value_error');
            }
        }

        //如果更改的状态是【5离职】或【6辞退】时，需检查是否有未上的预约课程
        if ($status == TeacherModel::ENTRY_LEAVE || $status == TeacherModel::ENTRY_DISMISS) {
            //查看是否存在已预约时间片
            $dismiss_time = time();
            $reserved_time = TeacherScheduleModel::getBTScheduleReservedForDismiss($teacher_id, $dismiss_time);
            if (count($reserved_time) > 0) {
                return Valid::addErrors([], 'teacher_leave', 'teacher_have_reserved_time');
            }
        }

        return [
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'teacherInfo' => $teacher_info
            ]
        ];
    }

    /**
     * 获取待入职老师列表
     * @param $page
     * @param $count
     * @param $params
     * @param $status
     * @return array
     */
    public static function getTeacherByStatus($page, $count, $params, $status)
    {

        list($teachers, $totalCount) = TeacherModel::getTeacherByStatus($page, $count, $params, $status);

        foreach ($teachers as $key => $teacher) {
            $teachers[$key]['app_extend'] = TeacherAppExtendService::getAppAndEmployeeExtendInfoByTeacherID($teacher['id']);
            $teachers[$key]['tag_info'] = TeacherTagRelationsService::getTagInfoByTeacherID($teacher['id']);
            $teachers[$key]['type_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_TYPE, $teacher['type']);
            $teachers[$key]['level_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_LEVEL, $teacher['level']);
            $teachers[$key]['music_level_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL, $teacher['music_level']);
            $teachers[$key]['status_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_STATUS, $teacher['status']);
            $teachers[$key]['mobile'] = Util::hideUserMobile($teacher['mobile']);
            if ($teacher['channel_id']) {
                $channel_info = TeacherChannelModel::getById($teacher['channel_id']);
                $teachers[$key]['channel_name'] = $channel_info['name'];
            } else {
                $teachers[$key]['channel_name'] = '';
            }
            //计算是否导出
            $teachers[$key]['is_export'] = $teacher['is_export'] == 0 ? "否" : "是";
            //取出详细地址
            $country = !empty($teacher['country_code']) ? AreaService::getByCode($teacher['country_code'])['name'] : '';
            $province = !empty($teacher['province_code']) ? AreaService::getByCode($teacher['province_code'])['name'] : '';
            $city = !empty($teacher['city_code']) ? AreaService::getByCode($teacher['city_code'])['name'] : '';
            $district = !empty($teacher['district_code']) ? AreaService::getByCode($teacher['district_code'])['name'] : '';
            $teachers[$key]['address'] = $country . $province . $city . $district . $teacher['address'];
            //计算是否毕业
            if (!empty($teacher['graduation_date'])) {
                $teachers[$key]['is_graduate'] = (date('Ym') - $teacher['graduation_date']) > 0 ? "是" : "否";
            } else {
                $teachers[$key]['is_graduate'] = '';
            }
            //计算琴龄
            if (!empty($teacher['learn_start_year'])) {
                $teachers[$key]['learn_year'] = date("Y") - $teacher['learn_start_year'];
            } else {
                $teachers[$key]['learn_year'] = '';
            }
        }
        return [$teachers, $totalCount];
    }

    /**
     * 获取老师列表
     * @param $operator_id
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function getList($operator_id, $page, $count, $params)
    {
        $ta_role_id = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, 'TA_ROLE_ID');
        list($teachers, $totalCount) = TeacherModel::getTeacherList($operator_id, $page, $count, $params, $ta_role_id);

//        foreach ($teachers as $key => $teacher) {
//            $teachers[$key]['app_extend'] = TeacherAppExtendService::getAppAndEmployeeExtendInfoByTeacherID($teacher['id']);
//            $teachers[$key]['status_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_STATUS, $teacher['status']);
//            $teachers[$key]['teacher_leave'] = TeacherLeaveService::getEffectLeave($teacher['id']);
//            $teachers[$key]['tag_info'] = TeacherTagRelationsService::getTagInfoByTeacherID($teacher['id']);
//            $teachers[$key]['mobile'] = Util::hideUserMobile($teacher['mobile']);
//        }
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

    /**
     * 处理Excel导入老师信息
     * @param $data_csv
     * @return array|bool
     * @throws \Exception
     */
    public static function importFormat($data_csv)
    {
        //获取渠道来源
        $teacher_channel = TeacherChannelModel::getNormalRecords();
        //获取老师类型
        $teacher_type = DictService::getList(Constants::DICT_TYPE_TEACHER_TYPE);
        //获取老师学历
        $teacher_education = DictService::getList(Constants::DICT_TYPE_TEACHER_EDUCATION);
        //获取老师演奏水平
        $teacher_music_level = DictService::getList(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL);
        //获取老师应用评价星级
        $teacher_evaluate_app_id_panda = DictService::getList(Constants::DICT_TYPE_TEACHER_EVALUATE . AppModel::APP_PANDA);
        //获取老师教授级别
        $teacher_level = DictService::getList(Constants::DICT_TYPE_TEACHER_LEVEL);

        //初始化Redis
        $redis = RedisDB::getConn(intval($_ENV['REDIS_DB']));

        //循环组装插入数据
        $ret = [];//最后返回的，验证过的，纯净的csv数据
        $i = 0;
        $data_csv['error'] = 0;
        $error_number = 0;
        $mobiles = [];
        $queue_arr = [];
        foreach ($data_csv as $key => $value) {
            $error_message = [
                'code' => 1,
                'error_message' => [
                    'line_number' => $i + 1
                ]
            ];
            if (is_array($value)) {
                if ($key == 0) {
                    continue;
                }
                //验证老师姓名必须小于10个字符
                if (mb_strlen($value[0]) > 10) {
                    $data_csv = self::importError($data_csv, $key, 0, $value);
                }
                //验证手机号必须为1-14位数字
                if (empty($value[1]) || !Util::isMobile($value[1])) {
                    $data_csv = self::importError($data_csv, $key, 1, $value);
                }
                //查看导入文件内是否有重复手机号
                if (in_array($value[1], $mobiles)) {
                    $data_csv = self::importError($data_csv, $key, 1, $value);
                }
                $mobiles[] = $value[1];
                //验证手机号是否存在
                if (TeacherModel::isMobileExistImport($value[1])) {
                    $data_csv = self::importError($data_csv, $key, 1, $value);
                }

                //定义插入的数组
                $data = [
                    'name' => $value[0],
                    'mobile' => $value[1],
                    'birthday' => '',
                    'gender' => null,
                    'thumb' => '',
                    'line_number' => $i + 1,
                    'status' => TeacherModel::ENTRY_WAIT,
                ];
                //判断性别如果不为空则必须是男或女
                if (!empty($value[2])) {
                    if ($value[2] == "男") {
                        $data['gender'] = '1';
                    } elseif ($value[2] == "女") {
                        $data['gender'] = '2';
                    } else {
                        $data_csv = self::importError($data_csv, $key, 2, $value);
                    }
                }
                //判断出生日期格式
                if (!empty($value[3])) {
                    if (Util::isDateValid($value[3])) {
                        $birthday = strtotime($value[3]);
                        $data['birthday'] = date('Ymd', $birthday);
                    } else {
                        $data_csv = self::importError($data_csv, $key, 3, $value);
                    }
                }
                //判断ftp
                if (!empty($value[4])) {
                    $queue_arr[] = [
                        'mobile' => $value[1],
                        'thumb' => $value[4]
                    ];
                }
                //验证渠道来源
                if (!empty($value[6])) {
                    $result = TeacherChannelService::checkTeacherChannel($teacher_channel, $value[6]);
                    if (is_array($result)) {
                        $data["channel_id"] = $result['id'];
                    } else {
                        $data_csv = self::importError($data_csv, $key, 6, $value);
                    }
                }
                //验证所属课程，及其扩展信息；所属课程如果多个用逗号隔开
                if (!empty($value[10])) {
                    $result = self::importTeacherApp($value[10], $value[12], $teacher_evaluate_app_id_panda, $error_message);
                    if (!empty($result["code"]) && $result["code"] != Valid::CODE_SUCCESS) {
                        if ($result['error_message']['column_number'] = 10) {
                            $data_csv = self::importError($data_csv, $key, 10, $value);
                        } elseif ($result['error_message']['column_number'] = 12) {
                            $data_csv = self::importError($data_csv, $key, 12, $value);
                        }
                    } else {
                        $data['teacher_app'] = $result;
                    }
                }
                //验证老师类型
                if (!empty($value[13])) {
                    $result = self::checkDictType($teacher_type, $value[13]);
                    if (isset($result['key_code'])) {
                        $data["type"] = $result['key_code'];
                    } else {
                        $data_csv = self::importError($data_csv, $key, 13, $value);
                    }
                }
                //教授级别
                if (!empty($value[14])) {
                    //如果有待定，替换为空
                    $teacher_level_name = str_replace('待定', '', $value[14]);
                    $result = self::checkDictType($teacher_level, $teacher_level_name);
                    if (isset($result['key_code'])) {
                        $data["level"] = $result['key_code'];
                    } else {
                        $data_csv = self::importError($data_csv, $key, 14, $value);
                    }
                }
                //教学起始时间
                if (!empty($value[15])) {
                    $value[15] = str_replace("/", "-", $value[15]);
                    $arr = explode("-", $value[15]);
                    if (is_array($arr)) {
                        $year = Util::formatYear($arr[0]);
                        if (!empty($year)) {
                            $data["start_year"] = $year;
                        } else {
                            $data_csv = self::importError($data_csv, $key, 15, $value);
                        }
                    }
                }
                //学琴起始时间
                if (!empty($value[16])) {
                    $value[16] = str_replace("/", "-", $value[16]);
                    $arr = explode("-", $value[16]);
                    if (is_array($arr)) {
                        $year = Util::formatYear($arr[0]);
                        if (!empty($year)) {
                            $data["learn_start_year"] = $year;
                        } else {
                            $data_csv = self::importError($data_csv, $key, 16, $value);
                        }
                    }
                }
                //毕业院校
                if (!empty($value[17])) {
                    $teacher_college = TeacherCollegeModel::getRecordByName($value[17]);
                    if ($teacher_college) {
                        $data["college_id"] = $teacher_college['id'];
                    } else {
                        $data_csv = self::importError($data_csv, $key, 17, $value);
                    }
                }
                //所学专业
                if (!empty($value[18])) {
                    $teacher_major = TeacherMajorModel::getRecordByName($value[18]);
                    if ($teacher_major) {
                        $data["major_id"] = $teacher_major['id'];
                    } else {
                        $data_csv = self::importError($data_csv, $key, 18, $value);
                    }
                }
                //毕业年月
                if (!empty($value[19])) {
                    $value[19] = str_replace("/", "-", $value[19]);
                    $arr = explode("-", $value[19]);
                    if (is_array($arr)) {
                        $arr[1] = str_pad($arr[1], 2, "0", STR_PAD_LEFT);
                        $data["graduation_date"] = $arr[0] . $arr[1];
                    }
                }
                //验证老师学历
                if (!empty($value[20])) {
                    $result = self::checkDictType($teacher_education, $value[20]);
                    if (isset($result['key_code'])) {
                        $data["education"] = $result['key_code'];
                    } else {
                        $data_csv = self::importError($data_csv, $key, 20, $value);
                    }
                }
                //验证老师演奏水平
                if (!empty($value[21])) {
                    $result = self::checkDictType($teacher_music_level, $value[21]);
                    if (isset($result['key_code'])) {
                        $data["music_level"] = $result['key_code'];
                    } else {
                        $data_csv = self::importError($data_csv, $key, 21, $value);
                    }
                }
                //验证老师教学经历
                if (!empty($value[22])) {
                    if (mb_strlen($value[22]) <= 100) {
                        $data["teach_experience"] = $value[22];
                    } else {
                        $data_csv = self::importError($data_csv, $key, 22, $value);
                    }
                }
                //验证老师获奖情况
                if (!empty($value[23])) {
                    if (mb_strlen($value[23]) <= 100) {
                        $data["prize"] = $value[23];
                    } else {
                        $data_csv = self::importError($data_csv, $key, 23, $value);
                    }
                }
                //验证老师创建时间
                if (!empty($value[30])) {
                    $data["create_time"] = strtotime($value[30]);
                }
                $ret[] = $data;
                if (isset($data_csv[$key]['error']) && $data_csv[$key]['error'] == 1) {
                    $error_number++;
                }
            }
            $i++;
        }

        return [$ret, $data_csv, $error_number, $queue_arr];
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
     * 导出注册老师列表
     * @param $params
     * @return array
     */
    public static function exportRegisterTeacher($params)
    {

        $teachers = TeacherModel::exportRegisterTeacher($params);

        $data = [
            ['老师ID', '老师姓名', '老师电话', '性别', '出生日期', '头像', '所在地区', '渠道来源', '身份证号', '银行卡号', '开户行',
                '授课类型', '主课星级', '陪练星级', '老师类型', '教授级别', '教学起始时间', '学琴起始时间', '毕业院校', '所学专业',
                '毕业年月', '老师学历', '演奏水平', '教学经历', '获奖情况', '教学成果', '教学风格', '【教学经验类】', '【教学风格类】',
                '【教学技巧类】', '【附加类】', '创建时间', '推荐人', '推荐人手机号']
        ];
        $data[0] = array_map(function ($val) {
            return iconv("utf-8", "GB18030//IGNORE", $val);
        }, $data[0]);
        $i = 1;
        $ids = [];
        foreach ($teachers as $key => $teacher) {
            //收集导出老师的ID，为更新导出字段做准备
            if ($teacher['is_export'] == 0) {
                $ids[$i] = $teacher['id'];
            }

            //取出演奏水平
            if (!empty($teacher['music_level'])) {
                $teacher['music_level_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL, $teacher['music_level']);
            } else {
                $teacher['music_level_name'] = '';
            }

            //取出详细地址
            $country = !empty($teacher['country_code']) ? AreaService::getByCode($teacher['country_code'])['name'] : '';
            $province = !empty($teacher['province_code']) ? AreaService::getByCode($teacher['province_code'])['name'] : '';
            $city = !empty($teacher['city_code']) ? AreaService::getByCode($teacher['city_code'])['name'] : '';
            $district = !empty($teacher['district_code']) ? AreaService::getByCode($teacher['district_code'])['name'] : '';
            $teacher['address'] = $country . $province . $city . $district . $teacher['address'];

            foreach ($data[0] as $k => $v) {
                if (!empty($teacher['referee_id'])) {
                    $referee_info = TeacherModel::getById($teacher['referee_id']);
                } else {
                    $referee_info = '';
                }

                if ($k == 0) {
                    $data[$i][$k] = $teacher['id'];
                }elseif ($k == 1) {
                    $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['name']);//对中文编码进行处理
                }elseif ($k == 2){
                    $data[$i][$k] = $teacher['mobile'];
                } elseif ($k == 6) {
                    $data[$i][$k] = iconv("utf-8","GB18030//IGNORE",$teacher['address']);;
                } elseif ($k == 7){
                    if (!empty($teacher['channel_id'])) {
                        $channel_info = TeacherChannelModel::getById($teacher['channel_id']);
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $channel_info['name']);
                    } else {
                        $data[$i][$k] = '';
                    }
                } elseif ($k == 17) {
                    $data[$i][$k] = $teacher['learn_start_year'];
                } elseif ($k == 18) {
                    $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['college_name']);
                } elseif ($k == 19) {
                    $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['major_name']);
                } elseif ($k == 20) {
                    $data[$i][$k] = $teacher['graduation_date'];
                } elseif ($k == 22) {
                    $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['music_level_name']);
                } elseif ($k == 32) {
                    if (!empty($referee_info)) {
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $referee_info['name']);
                    } else {
                        $data[$i][$k] = '';
                    }
                } elseif ($k == 33) {
                    if (!empty($referee_info)) {
                        $data[$i][$k] = $referee_info['mobile'];
                    } else {
                        $data[$i][$k] = '';
                    }
                } else {
                    $data[$i][$k] = '';
                }
            }
            $i++;
        }
        //修改对应的ID为已导出，优化，每次最多更新100个ID
        $per = 0;
        $page = ceil(count($ids) / 100);
        for ($i = 0; $i < $page; $i++) {
            $sub_ids = array_slice($ids, $i * 100, 100);

            TeacherModel::batchUpdateRecord(['is_export' => 1], ['id' => $sub_ids]);
            $per++;
        }
        return $data;
    }

    /**
     * 预约课程，查询老师
     * @param $keyword string 姓名、电话、id
     * @param $gender int 性别
     * @param $collegeId int 毕业院校
     * @param $majorId int 专业
     * @param $tagIds array 标签数组
     * @param $courseId   int 课程ID
     * @param $courseNum  int  上课节数
     * @param $startTime  int  上课时间
     * @param $page
     * @param $count
     * @param $appId
     * @param $studentId
     * @return array
     * @throws \Exception
     */
    public static function searchTeacherList($keyword, $gender, $collegeId, $majorId, $tagIds,
                                             $courseId, $courseNum, $startTime, $page, $count, $appId, $studentId)
    {

        $course = CourseService::getCourseById($courseId);
        // 计算所需时间片数量
        $countTs = ceil($course['duration'] * $courseNum / TeacherScheduleModel::TS_UNIT);

        list($teachers, $totalCount) = TeacherModel::searchAvailableForSchedule(
            $keyword, $gender, $collegeId, $majorId, $tagIds, $startTime, $countTs, $page, $count, $appId, $studentId, $course['type']);
        //获取教师类型MAP
        $teacherTypeMap = DictService::getTypeMap(Constants::DICT_TYPE_TEACHER_TYPE);
        foreach ($teachers as &$teacher) {
            $teacher['level'] = Dict::getTeacherLevel($teacher['level']);
            $teacher['gender'] = Dict::getGender($teacher['gender']);
            $teacher['teacher_type'] = isset($teacherTypeMap[$teacher['type']]) ? $teacherTypeMap[$teacher['type']] : NULL;
        }

        return [$teachers, $totalCount];
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
     * app 老师注册
     * @param $params
     * @return array
     */
    public static function register($params)
    {
        $app_id = $params['app_id'];
        unset($params['app_id']);
        unset($params['referee_id']);

        $params['mobile'] = !empty($params['mobile']) ? trim($params['mobile']) : '';
        //验证手机号是否已存在，如果已存在，验证app_id是否存在
        $teacher_info = TeacherModel::getRecordByMobile($params['mobile']);
        if (!empty($teacher_info["id"])) {
            $teacherId = $teacher_info['id'];
            if (TeacherAppExtendService::isTeacherAppIDExist($teacherId, $app_id)) {
                $result = APIValid::addErrors([], 'teacher_mobile_is_exist');
                $result['teacher_id'] = $teacherId;
                return $result;
            } else {
                $data = [
                    'teacher_id' => $teacherId,
                    'app_id' => $app_id,
                    'status' => TeacherAppExtendModel::STATUS_NORMAL
                ];
                $app_extend = TeacherAppExtendModel::insertRecord($data);
                if (!$app_extend) {
                    $result = APIValid::addErrors([], 'teacher_app_extend_add_error');
                    return $result;
                }
            }
            return [
                'teacher_id' => $teacherId,
                'uuid' => $teacher_info['uuid']
            ];
        } else {
            // 用户中心处理
            $userCenter = new UserCenter();
            $params['birthday'] = $params['birthday'] ?? '';
            $params['gender'] = $params['gender'] ?? TeacherModel::GENDER_UNKNOWN;
            $params['thumb'] = $params['thumb'] ?? '';
            $authResult = $userCenter->teacherAuthorization($params['mobile'], $params['name'], "", $params['birthday'], $params['gender'], $params['thumb'], false);
            if (empty($authResult["uuid"])) {
                return Valid::addErrors([], "user_center", "uc_user_add_failed");
            }
            $params['uuid'] = $authResult['uuid'];
            $params['create_time'] = time();

            $teacher_id = TeacherModel::insertRecord($params);
            if ($teacher_id == false) {
                $result = APIValid::addErrors([], 'teacher_add_error');
                return $result;
            }
            //提交所属课程的数据
            if (!empty($app_id)) {
                $teacher_product_extend = [
                    [
                        'teacher_id' => $teacher_id,
                        'app_id' => $app_id
                    ]
                ];
                // 注册时不操作时间片，故operator设置为空
                $app_extend = TeacherAppExtendService::insertOrUpdate($teacher_id,$teacher_product_extend, []);
                if (!$app_extend) {
                    $result = APIValid::addErrors([], 'teacher_app_extend_add_error');
                    return $result;
                }
            }

            return [
                'teacher_id' => $teacher_id,
                'uuid' => $params['uuid']
            ];
        }
    }

    /**
     * 注册老师列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function teacherRegisterList($params, $page, $count)
    {
        list($teachers, $totalCount) = TeacherModel::getRegisterTeacher($params, $page, $count);
        foreach ($teachers as $key => $teacher) {
            $teachers[$key]['music_level_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL, $teacher['music_level']);
            $teachers[$key]['mobile'] = Util::hideUserMobile($teacher['mobile']);
            if ($teacher['channel_id']) {
                $channel_info = TeacherChannelModel::getById($teacher['channel_id']);
                $teachers[$key]['channel_name'] = $channel_info['name'];
            } else {
                $teachers[$key]['channel_name'] = '';
            }
            //计算是否导出
            $teachers[$key]['is_export'] = $teacher['is_export'] == 0 ? "否" : "是";
            //取出详细地址
            $country = !empty($teacher['country_code']) ? AreaService::getByCode($teacher['country_code'])['name'] : '';
            $province = !empty($teacher['province_code']) ? AreaService::getByCode($teacher['province_code'])['name'] : '';
            $city = !empty($teacher['city_code']) ? AreaService::getByCode($teacher['city_code'])['name'] : '';
            $district = !empty($teacher['district_code']) ? AreaService::getByCode($teacher['district_code'])['name'] : '';
            $teachers[$key]['address'] = $country . $province . $city . $district . $teacher['address'];
            //计算是否毕业
            if (!empty($teacher['graduation_date'])) {
                $teachers[$key]['is_graduate'] = (date('Ym') - $teacher['graduation_date']) > 0 ? "是" : "否";
            } else {
                $teachers[$key]['is_graduate'] = '';
            }
            //计算琴龄
            if (!empty($teacher['learn_start_year'])) {
                $teachers[$key]['learn_year'] = date("Y") - $teacher['learn_start_year'];
            } else {
                $teachers[$key]['learn_year'] = '';
            }
            //介绍人
            if (!empty($teacher['referee_id'])) {
                $referee_info = TeacherModel::getById($teacher['referee_id']);
                $teachers[$key]['referee_name'] = $referee_info['name'];
                $teachers[$key]['referee_mobile'] = $referee_info['mobile'];
            } else {
                $teachers[$key]['referee_name'] = '';
                $teachers[$key]['referee_mobile'] = '';
            }
        }
        return [$teachers, $totalCount];
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
     * CRM约课获取推荐老师列表
     * @param $tagIds
     * @param $startTime
     * @param $endTime
     * @param $appId
     * @param $studentLevel
     * @param $gender
     * @return array
     * @throws \Exception
     */
    public static function crmGetRecommendTeacherList($tagIds, $startTime, $endTime, $appId, $studentLevel, $gender)
    {
        $teachers = TeacherModel::crmGetRecommendTeachers($tagIds, $startTime, $endTime, $appId, $studentLevel, $gender);
        foreach ($teachers as &$teacher) {
            $teacher['level'] = Dict::getTeacherLevel($teacher['level']);
            $teacher['gender'] = Dict::getGender($teacher['gender']);
        }
        return $teachers;
    }

    /**
     * 处理Excel导入老师信息--项目首次导入已入职老师
     * @param $data
     * @return array|bool
     */
    public static function importTeacherInfo($data)
    {
        //获取渠道来源
        $teacher_channel = TeacherChannelModel::getNormalRecords();
        //获取老师类型
        $teacher_type = DictService::getList(Constants::DICT_TYPE_TEACHER_TYPE);
        //获取老师演奏水平
        $teacher_music_level = DictService::getList(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL);
        //获取老师教授级别
        $teacher_level = DictService::getList(Constants::DICT_TYPE_TEACHER_LEVEL);

        //循环组装更新数据
        $i = 1;
        $time = time();
        foreach ($data as $key => $value) {
            $error_message = [
                'code' => 1,
                'error_message' => [
                    'line_number' => $i
                ]
            ];
            if (is_array($value)) {
                //定义更新的数组
                $data = [
                    'name' => $value[1],
                    'mobile' => $value[2],
                    'status' => TeacherModel::ENTRY_ON,
                ];
                //教授级别
                if (!empty($value[3])) {
                    //如果有待定，替换为空
                    $result = self::checkDictType($teacher_level, $value[3]);
                    if (isset($result['key_code'])) {
                        $data["level"] = $result['key_code'];
                    } else {
                        $result = Valid::addErrors($error_message, 'teacher', 'teacher_level_error');
                        return $result;
                    }
                }
                //老师评星
                if (!empty($value[4])) {
                    $result = TeacherAppExtendModel::updateByTeacherAndAppID(['evaluate_level' => $value[4]], $value[0], AppModel::APP_PANDA);
                    if (!is_numeric($result)) {
                        $result = Valid::addErrors($error_message, 'teacher', 'teacher_evaluate_level_update_error');
                        return $result;
                    }
                }
                //验证老师类型
                if (!empty($value[5])) {
                    $result = self::checkDictType($teacher_type, $value[5]);
                    if (isset($result['key_code'])) {
                        $data["type"] = $result['key_code'];
                    } else {
                        $result = Valid::addErrors($error_message, 'teacher', 'teacher_type_error');
                        return $result;
                    }
                }
                //验证渠道来源
                if (!empty($value[8])) {
                    $result = TeacherChannelService::checkTeacherChannel($teacher_channel, $value[8]);
                    if (is_array($result)) {
                        $data["channel_id"] = $result['id'];
                    } else {
                        $channel_data = [
                            'name' => $value[8],
                            'create_time' => $time
                        ];
                        $channel_id = TeacherChannelModel::insertRecord($channel_data);
                        if ($channel_id == null) {
                            $result = Valid::addErrors($error_message, 'teacher', 'teacher_channel_add_error');
                            return $result;
                        }
                        $data["channel_id"] = $channel_id;
                        $teacher_channel[] = [
                            'id' => $channel_id,
                            'name' => $value[8]
                        ];
                    }
                }
                //毕业院校
                if (!empty($value[9])) {
                    $teacher_college = TeacherCollegeModel::getRecordByName($value[9]);
                    if ($teacher_college) {
                        $data["college_id"] = $teacher_college['id'];
                    } else {
                        $college_data = ['college_name' => $value[9]];
                        $college_id = TeacherCollegeModel::insertRecord($college_data);
                        if ($college_id == null) {
                            $result = Valid::addErrors($error_message, 'teacher', 'teacher_college_insert_error');
                            return $result;
                        }
                        $data["college_id"] = $college_id;
                    }
                }
                //所学专业
                if (!empty($value[10])) {
                    $teacher_major = TeacherMajorModel::getRecordByName($value[10]);
                    if ($teacher_major) {
                        $data["major_id"] = $teacher_major['id'];
                    } else {
                        $major_data = ['major_name' => $value[10]];
                        $major_id = TeacherMajorModel::insertRecord($major_data);
                        if ($major_id == null) {
                            $result = Valid::addErrors($error_message, 'teacher', 'teacher_major_insert_error');
                            return $result;
                        }
                        $data["major_id"] = $major_id;
                    }
                }
                //验证老师演奏水平
                if (!empty($value[12])) {
                    $value[12] = str_replace('及以上', '', $value[12]);
                    $result = self::checkDictType($teacher_music_level, $value[12]);
                    if (isset($result['key_code'])) {
                        $data["music_level"] = $result['key_code'];
                    } else {
                        $result = Valid::addErrors($error_message, 'teacher', 'teacher_music_level_error');
                        return $result;
                    }
                }
                $result = TeacherModel::updateRecord($value[0], $data);
                if (!is_numeric($result)) {
                    $result = Valid::addErrors($error_message, 'teacher', 'teacher_update_error');
                    return $result;
                }
            }
            $i++;
        }
        return true;
    }

    /**
     * 处理Excel导入老师标签信息--项目首次导入已入职老师标签
     * @param $data
     * @return array|bool|int|mixed|null|string
     */
    public static function importTeacherTags($data)
    {
        $i = 1;
        $teacher_tags = [];
        foreach ($data as $key => $value) {
            $error_message = [
                'code' => 1,
                'error_message' => [
                    'line_number' => $i
                ]
            ];
            if (is_array($value)) {
                //转化标签类型
                if ($value[2] == '主观') {
                    $type = TeacherTagsModel::TYPE_SUBJECTIVE;
                } elseif ($value[2] == '客观') {
                    $type = TeacherTagsModel::TYPE_OBJECTIVE;
                } else {
                    $result = Valid::addErrors($error_message, 'teacher_tags', 'tag_type_format_error');
                    return $result;
                }
                //查看父级标签是否存在
                $parent_tag_info = TeacherTagsModel::getTagsByNameAndType($value[3], $type);
                if (empty($parent_tag_info)) {
                    $result = Valid::addErrors($error_message, 'teacher_tags', 'teacher_parent_tag_is_not_exist');
                    return $result;
                }
                //处理二级标签
                $value[4] = str_replace('，', ',', $value[4]);
                $tags = explode(',', $value[4]);
                foreach ($tags as $v) {
                    $tag_info = TeacherTagsModel::getTagsByNameAndParentId($v, $parent_tag_info['id']);
                    if (empty($tag_info)) {
                        $result = Valid::addErrors($error_message, 'teacher_tags', 'teacher_tag_is_not_exist');
                        return $result;
                    }
                    //查看标签是否已存在
                    if (!TeacherTagRelationModel::isExist($value[0], $tag_info['id'])) {
                        $teacher_tags[] = [
                            'teacher_id' => $value[0],
                            'tag_id' => $tag_info['id']
                        ];
                    }
                }
            }
            $i++;
        }

        //优化，每次最多插入100条数据
        $per = 0;
        $page = ceil(count($teacher_tags) / 100);
        for ($i = 0; $i < $page; $i++) {
            $insert_data = array_slice($teacher_tags, $i * 100, 100);
            $result = TeacherTagRelationModel::insertRecord($insert_data);
            if ($result == null) {
                $result = Valid::addErrors([], 'teacher_tags', 'teacher_tag_relation_insert_error');
                return $result;
            }
            $per++;
        }
        return true;
    }

    /**
     * 更新老师头像处理
     * @param $mobile
     * @param $thumb
     * @return array|int|null
     * @throws \Exception
     */
    public static function teacherThumb($mobile, $thumb)
    {
        //ftp 下载
        $ftp = new FTP();
        $tmp_file = $ftp->download($thumb);
        $thumb_qiniu = Qiniu::qiNiuUpload($tmp_file);

        //如果上传成功，删除缓存文件
        if (!empty($thumb_qiniu)) {
            unlink($tmp_file);    // 删除临时文件
        }

        //更新老师头像信息
        $result = TeacherModel::batchUpdateRecord(['thumb' => $thumb_qiniu], ['mobile' => $mobile]);
        if ($result === null) {
            $result = Valid::addErrors([], 'teacher_thumb', 'teacher_thumb_update_error');
            return $result;
        }
        return $result;
    }

    /**
     * 添加或者更新教师简介（图片+视频）
     * @param $briefImageParams
     * @param $briefVideoParams
     * @param $teacherId
     * @param bool $isEdit
     * @return array
     */
    public static function addOrUpdateTeacherBriefIntroduction($briefImageParams, $briefVideoParams, $teacherId, $isEdit = false)
    {
        $briefImage = [];
        $briefVideo = [];
        // 教师图片
        if (!empty($briefImageParams)) {
            $briefImage = array_map(function ($v) use ($teacherId) {
                return [
                    'teacher_id' => $teacherId,
                    'create_time' => time(),
                    'url' => $v,
                    // 类型为图片
                    'type' => TeacherBriefIntroductionModel::BRIEF_INTRODUCTION_TYPE_IMAGE,
                    // 状态为正常
                    'status' => TeacherBriefIntroductionModel::BRIEF_INTRODUCTION_STATUS_NORMAL,
                ];
            }, $briefImageParams);
        }
        // 教师视频
        if (!empty($briefVideoParams)) {
            // 教师图片
            $briefVideo = array_map(function ($v) use ($teacherId) {
                return [
                    'teacher_id' => $teacherId,
                    'create_time' => time(),
                    'url' => $v,
                    // 类型为视频
                    'type' => TeacherBriefIntroductionModel::BRIEF_INTRODUCTION_TYPE_VIDEO,
                    // 状态为正常
                    'status' => TeacherBriefIntroductionModel::BRIEF_INTRODUCTION_STATUS_NORMAL,
                ];
            }, $briefVideoParams);

        }
        // 图片和视频合并
        $briefIntroduction = array_merge($briefImage, $briefVideo);
        $result = TeacherBriefIntroductionModel::addOrUpdateBriefIntroduction($briefIntroduction, $teacherId, $isEdit);

        // 返回结果为true或false
        if (!$result) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__ . ': add_brief_introduction_error', [
                'add_result' => $result,
                'teacher_id' => $teacherId,
                'is_edit' => $isEdit,
                'brief_image' => $briefImage,
                'brief_video' => $briefVideo,
                'brief_intro' => $briefIntroduction,
            ]);
            return Valid::addErrors([], 'add_or_update_brief_introduction_error', 'add_or_update_brief_introduction_error');
        }
        // 返回结果
        return [
            'code' => Valid::CODE_SUCCESS,
            'data' => $result,
        ];
    }

    /**
     * 根据老师ID获取老师的详细信息
     * @param $mobile
     * @return mixed
     */
    public static function getTeacherInfoByMobile($mobile)
    {
        //获取老师的基础信息
        $teacher_info = TeacherModel::getRecordByMobile($mobile);
        if (empty($teacher_info)) {
            return [];
        }

        if ($teacher_info['status'] != TeacherModel::ENTRY_REGISTER) {
            $status_name = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_STATUS, $teacher_info['status']);
            $result = [
                "code" => 1,
                "data" => [
                    "errors" => [
                        "teacher" => [
                            [
                                "err_no" => "teacher_thumb_update_error",
                                "err_msg" => "该老师的状态为：" . $status_name
                            ]
                        ]
                    ]
                ]
            ];
            return $result;
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
            $teacher_info['channel_name'] = TeacherChannelModel::getById($teacher_info['channel_id']);
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

        $teacher_info['college'] = !empty($teacher_info['college_id']) ? TeacherCollegeModel::getById($teacher_info['college_id']) : '';
        $teacher_info['major'] = !empty($teacher_info['major_id']) ? TeacherMajorModel::getById($teacher_info['major_id']) : '';
        $teacher_info['zoom'] = TeacherZoomService::getRecordByTeacherID($teacher_info['id']);
        $teacher_info['teacher_app_extend'] = TeacherAppExtendService::getAppAndEmployeeExtendInfoByTeacherID($teacher_info['id']);
        $teacher_info['tag_info'] = TeacherTagRelationsService::getTagInfoByTeacherID($teacher_info['id']);
        $teacher_info['type_name'] = !empty($teacher_info['type']) ? DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_TYPE, $teacher_info['type']) : '';
        $teacher_info['level_name'] = !empty($teacher_info['level']) ? DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_LEVEL, $teacher_info['level']) : '';
        $teacher_info['music_level_name'] = !empty($teacher_info['music_level']) ? DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL, $teacher_info['music_level']) : '';
        $teacher_info['status_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_STATUS, $teacher_info['status']);
        $teacher_info['birthday'] = !empty($teacher_info['birthday']) ? str_pad($teacher_info['birthday'], 8, "0", STR_PAD_RIGHT) : '';
        $teacher_info['education_name'] = !empty($teacher_info['education']) ? DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_EDUCATION, $teacher_info['education']) : '';
        if (!empty($teacher_info['graduation_date'])) {
            $teacher_info['is_graduate'] = (date('Ym') - $teacher_info['graduation_date']) >= 0 ? "是" : "否";
        } else {
            $teacher_info['is_graduate'] = "";
        }
        return $teacher_info;
    }

    /**
     * 获取注册老师的基础信息
     * @param $teacher_id
     * @return mixed
     */
    public static function getTeacherRegisterInfo($teacher_id)
    {
        //获取老师的基础信息
        $teacher_info = TeacherModel::getTeacherRegisterDetailById($teacher_id);
        if (empty($teacher_info)) {
            return [];
        }
        !empty($teacher_info['create_time']) && $teacher_info['create_time'] = date('Y-m-d H:i:s', $teacher_info['create_time']);
        //取出详细地址
        if (!empty($teacher_info['province_code']) && empty($teacher_info['country_code'])) {
            $teacher_info['country_code'] = '100000';
        }
        $country = !empty($teacher_info['country_code']) ? AreaService::getByCode($teacher_info['country_code'])['name'] : '';
        $province = !empty($teacher_info['province_code']) ? AreaService::getByCode($teacher_info['province_code'])['name'] : '';
        $city = !empty($teacher_info['city_code']) ? AreaService::getByCode($teacher_info['city_code'])['name'] : '';
        $district = !empty($teacher_info['district_code']) ? AreaService::getByCode($teacher_info['district_code'])['name'] : '';
        $teacher_info['area_full'] = $country . $province . $city . $district;
        if (!empty($teacher_info['graduation_date'])) {
            $teacher_info['is_graduate'] = (date('Ym') - $teacher_info['graduation_date']) >= 0 ? "是" : "否";
        } else {
            $teacher_info['is_graduate'] = "";
        }
        if (!empty($teacher_info['learn_start_year'])) {
            $teacher_info['learn_years'] = date('Y', time()) - $teacher_info['learn_start_year'];
        } else {
            $teacher_info['learn_years'] = '';
        }
        $teacher_info['music_level_name'] = !empty($teacher_info['music_level']) ? DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL, $teacher_info['music_level']) : '';
        $teacher_info['is_export'] = $teacher_info['is_export'] == 0 ? "否" : "是";
        //介绍人
        if (!empty($teacher_info['referee_id'])) {
            $referee_info = TeacherModel::getById($teacher_info['referee_id']);
            $teacher_info['referee_name'] = $referee_info['name'];
            $teacher_info['referee_mobile'] = $referee_info['mobile'];
        } else {
            $teacher_info['referee_name'] = '';
            $teacher_info['referee_mobile'] = '';
        }

        return $teacher_info;
    }

    /**
     * 验证teacher_app
     * @param $teacher_app
     * @return bool
     */
    public static function checkTeacherApp($teacher_app)
    {
        if (is_array($teacher_app)) {
            foreach ($teacher_app as $value) {
                if (empty($value['app_id'])) {
                    return false;
                }
                if (is_array($value['ts_course_type']) && count($value['ts_course_type']) == 2) {
                    if ($value['ts_course_type'][0] != '1' && $value['ts_course_type'][1] != '1') {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
        return true;
    }

    /**
     *
     * @param $operatorId
     * @return array
     */
    public static function formatEmployeeOperator($operatorId) {
        $employee = EmployeeService::getById($operatorId);
        $type = TeacherScheduleModel::TS_OPERATOR_TYPE_EMPLOYEE;
        return [
            'id' => $operatorId,
            'name' => $employee['name'],
            'type' => $type
        ];
    }
    /**
     * 导出入职老师
     * @param $params
     * @return array
     */
    public static function exportEntryTeacher($params)
    {

        $teachers = TeacherModel::exportEntryTeacher($params);

        $data = [
            ['老师ID', '老师姓名', '老师电话', '性别', '出生日期', '头像', '所在地区', '渠道来源', '身份证号', '银行卡号', '开户行', '预留手机号',
                '授课类型', '主课星级', '陪练星级', '老师类型', '教授级别', '教学起始时间', '学琴起始时间', '毕业院校', '所学专业',
                '毕业年月', '老师学历', '演奏水平', '教学经历', '获奖情况', '教学成果', '教学风格', '老师标签', '创建时间']
        ];
        $data[0] = array_map(function ($val) {
            return iconv("utf-8", "GB18030//IGNORE", $val);
        }, $data[0]);
        $i = 1;
        foreach ($teachers as $key => $teacher) {
            //取出演奏水平
            if (!empty($teacher['music_level'])) {
                $teacher['music_level_name'] = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL, $teacher['music_level']);
            } else {
                $teacher['music_level_name'] = '';
            }
            foreach ($data[0] as $k => $v) {
//                $teacherApp = TeacherAppExtendModel::getByTeacherIdAndAppId($teacher['id'], AppModel::APP_PANDA);
                $tsCourseTypeNames = [];
                $evaluateLevelNames = [];
                if (!empty($teacher['ta_info'])){
                    $infos = explode(",", $teacher['ta_info']);
                    foreach ($infos as $info){
                        $appInfo = explode("-", $info);
                        if (!empty($appInfo) && count($appInfo) >= 4){
                            $appId = $appInfo[0];
                            $appName = $appInfo[1];
                            $courseType = $appInfo[2];
                            $evaluateLevel = $appInfo[3];

                            $courseTypeName = "";
                            if ($courseType != TeacherModel::COURSE_TYPE_NONE) {
                                $courseTypeName = DictService::getKeyValue(Constants::DICT_TYPE_TS_COURSE_TYPE,$courseType);
                                $courseTypeName = $appName . "-" . $courseTypeName;
                            }
                            $tsCourseTypeNames[] = iconv("utf-8", "GB18030//IGNORE", $appName . $courseTypeName);

                            $evaluateLevelName = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_EVALUATE . $appId,$evaluateLevel);
                            $evaluateLevelNames[] = iconv("utf-8", "GB18030//IGNORE", $evaluateLevelName);
                        }
                    }
                }

                switch ($k){
                    case 0:
                        $data[$i][$k] = $teacher['id'];
                        break;
                    case 1:
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['name']);//对中文编码进行处理
                        break;
                    case 2:
                        $data[$i][$k] = Util::hideUserMobile($teacher['mobile']);
                        break;
                    case 3:
                        $genderName = '';
                        if (!empty($teacher['gender'])){
                            $genderName = DictService::getKeyValue(Constants::DICT_TYPE_GENDER, $teacher['gender']);
                            $genderName = iconv("utf-8", "GB18030//IGNORE", $genderName);
                        }
                        $data[$i][$k] = $genderName;
                        break;
                    case 4:
                        $data[$i][$k] = $teacher['birthday'];
                        break;
                    case 5:
                        $data[$i][$k] = $teacher['thumb'];
                        break;
                    case 6:
                        //取出详细地址
                        if (!empty($teacher['province_code']) && empty($teacher['country_code'])) {
                            $teacher['country_code'] = '100000';
                        }
                        $country = !empty($teacher['country_code']) ? AreaService::getByCode($teacher['country_code'])['name'] : '';
                        $province = !empty($teacher['province_code']) ? AreaService::getByCode($teacher['province_code'])['name'] : '';
                        $city = !empty($teacher['city_code']) ? AreaService::getByCode($teacher['city_code'])['name'] : '';
                        $district = !empty($teacher['district_code']) ? AreaService::getByCode($teacher['district_code'])['name'] : '';
                        $area_full = $country . $province . $city . $district;
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $area_full);
                        break;
                    case 7:
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['channel_name']);
                        break;
                    case 8:
                        $data[$i][$k] = $teacher['id_card'];
                        break;
                    case 9:
                        $data[$i][$k] = $teacher['bank_card_number'];
                        break;
                    case 10:
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['opening_bank']);
                        break;
                    case 11:
                        $data[$i][$k] = Util::hideUserMobile($teacher['bank_reserved_mobile']);
                        break;
                    case 12:
                        if (!empty($teacher['ta_info'])){
                            $data[$i][$k] = implode(",",$tsCourseTypeNames);
                        } else {
                            $data[$i][$k] = '';
                        }
                        break;
                    case 13:
                        $data[$i][$k] = '';
                        break;
                    case 14:
                        if (!empty($teacher['ta_info'])){
                            $data[$i][$k] = implode(",", $evaluateLevelNames);
                        } else {
                            $data[$i][$k] = '';
                        }
                        break;
                    case 15:
                        $data[$i][$k] = filter_var($teacher['type'], FILTER_CALLBACK, array('options'=>function($value){
                            if (!empty($value)) {
                                $typeName = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_TYPE, $value);
                                return iconv("utf-8", "GB18030//IGNORE", $typeName);
                            }
                            return '';
                        }));
                        break;
                    case 16:
                        $data[$i][$k] = filter_var($teacher['level'], FILTER_CALLBACK, array('options'=>function($value){
                            if (!empty($value)) {
                                $levelName = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_LEVEL, $value);
                                return iconv("utf-8", "GB18030//IGNORE", $levelName);
                            }
                            return '';
                        }));
                        break;
                    case 17:
                        $data[$i][$k] = $teacher['start_year'];
                        break;
                    case 18:
                        $data[$i][$k] = $teacher['learn_start_year'];
                        break;
                    case 19:
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['college_name']);
                        break;
                    case 20:
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['major_name']);
                        break;
                    case 21:
                        $data[$i][$k] = $teacher['graduation_date'];
                        break;
                    case 22:
                        $data[$i][$k] = filter_var($teacher['education'], FILTER_CALLBACK, array('options'=>function($value){
                            if (!empty($value)) {
                                $educationName = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_EDUCATION, $value);
                                return iconv("utf-8", "GB18030//IGNORE", $educationName);
                            }
                            return '';
                        }));
                        break;
                    case 23:
                        $data[$i][$k] = filter_var($teacher['music_level'], FILTER_CALLBACK, array('options'=>function($value){
                            if (!empty($value)) {
                                $musicLevelName = DictService::getKeyValue(Constants::DICT_TYPE_TEACHER_MUSIC_LEVEL, $value);
                                return iconv("utf-8", "GB18030//IGNORE", $musicLevelName);
                            }
                            return '';
                        }));
                        break;
                    case 24:
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['teach_experience']);
                        break;
                    case 25:
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['prize']);
                        break;
                    case 26:
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['teach_results']);
                        break;
                    case 27:
                        $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['teach_style']);
                        break;
                    case 28:
                        //老师标签
                        if( !empty($teacher['tags'])){
                            $data[$i][$k] = iconv("utf-8", "GB18030//IGNORE", $teacher['tags']);
                        } else {
                            $data[$i][$k] = '';
                        }
                        break;
                    case 29:
                        $data[$i][$k] = filter_var($teacher['create_time'], FILTER_CALLBACK, array('options'=>function($value){
                            if (!empty($value)) {
                                return date('Y-m-d H:i:s', $value);
                            }
                            return '';
                        }));
                        break;
                }
            }
            $i++;
        }
        return $data;
    }

    /**
     * 更新老师最后上课时间
     * @param $teacherId
     * @param $time
     * @return int|null
     */
    public static function updateTeacherLastClassTime($teacherId, $time)
    {
        return TeacherModel::updateLastClassTime($teacherId, $time);
    }
}