<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/4
 * Time: 19:58
 *
 * 客户相关数据service
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\ResponseError;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppModel;
use App\Models\StudentModel;
use App\Models\StudentOrgModel;

class StudentService
{
    //默认分页条数
    const DEFAULT_COUNT = 20;

    /**
     * 获取客户列表数据
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function fetchStudentListData($page, $count, $params)
    {
        $data = [];
        $time_now = strtotime('today +1 days');
        $time_thirty_days_ago = strtotime('today -29 days');
        //获取count数据
        $studentCount = StudentModel::fetchStudentCount($params, $time_now, $time_thirty_days_ago);
        //判断 $studentCount > 0, 则获取student数据，否则返回空数组
        if($studentCount > 0){
            //获取student 数据
            $students = StudentModel::fetchStudentData($page, $count, $params ,$time_now, $time_thirty_days_ago);
            //处理student 数据
            if (!empty($students)) {
                //收集需要关联的用户数据
                $studentIdArray = [];
                $studentEmployeeIdArray = [];
                $studentChannelIdArray = [];
                foreach ($students as $student) {
                    $studentIdArray[] = $student['id'];
                    $studentEmployeeIdArray[] = $student['ca_id'];
                    $studentEmployeeIdArray[] = $student['cc_id'];
                    $studentChannelIdArray[] = $student['channel_id'];
                }
                $studentRelationsMap = StudentRelationService::getStudentRelationMap($studentIdArray);
                $studentEmployeeMap = EmployeeService::getStudentCaMap($studentEmployeeIdArray);
                $studentChannelMap = ChannelService::getChannelMap($studentChannelIdArray);
                $studentWeiXinMap = [];//UserWeiXinService::getWeiXinMapByStudent($studentIdArray, $params['app_id']);
                $app = AppModel::getById($params['app_id']);
                $levels = DictService::getTypeMap(Constants::DICT_TYPE_STUDENT_LEVEL);
                //遍历student数据，格式化数据内容
                foreach ($students as &$student) {
                    $student['app_name'] = $app['name'];
                    $student['level'] = $levels[$student['level']];
                    // 隐藏学生手机号
                    $student['mobile'] = Util::hideUserMobile($student['mobile']);
                    // 名称如果是手机号，则进行隐藏
                    if(Util::isMobile($student['name'])){
                        $student['name'] = Util::hideUserMobile($student['name']);
                    }
                    // 添加学生客服数据
                    if (isset($studentEmployeeMap[$student['ca_id']])) {
                        $student['ca_name'] = $studentEmployeeMap[$student['ca_id']];
                    } else {
                        $student['ca_name'] = '';
                    }
                    // 添加学生CC数据
                    if (isset($studentEmployeeMap[$student['cc_id']])) {
                        $student['cc_name'] = $studentEmployeeMap[$student['cc_id']];
                    } else {
                        $student['cc_name'] = '';
                    }
                    $student['channel'] = empty($studentChannelMap[$student['channel_id']]) ? '未知' : $studentChannelMap[$student['channel_id']];
                    unset($student['channel_id']);
                    // 添加学生关联人数据
                    if (isset($studentRelationsMap[$student['id']])) {
                        $student['relations'] = $studentRelationsMap[$student['id']];
                    } else {
                        $student['relations'] = '';
                    }
                    //学生微信绑定数据
                    $student['is_bind_weixin'] = empty($studentWeiXinMap[$student['id']]['open_id']) ? 0 : 1;
                }
                unset($student);
            }
        }else{
            $students = [];
        }

        $data['total_count'] = $studentCount;
        $data['students'] = $students;
        return $data;
    }

    /**
     * 查看机构下学生
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function selectStudentByOrg($orgId, $page, $count, $params)
    {
        list($records, $total) = StudentModel::selectStudentByOrg($orgId, $page, $count, $params);
        foreach($records as &$r) {
            $r['student_level'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_LEVEL, $r['student_level']);
        }

        return [$records, $total];
    }

    /**
     * 获取学生详情数据
     * @param $studentId
     * @param $hideMobile
     * @return array
     */
    //* 缺少上课设备的信息
    public static function fetchStudentDetail($studentId, $hideMobile = true)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            return [];
        }
        $channel = ChannelService::getChannelById($student['channel_id']);
        $parentChannel = ChannelService::getChannelById($channel['parent_id']);

        $data = [];
        $data['student_id'] = $student['id'];
        $data['uuid'] = $student['uuid'];
        $data['name'] = $student['name'];
        if($hideMobile){
            $data['mobile'] = Util::hideUserMobile($student['mobile']);
        }else{
            $data['mobile'] = $student['mobile'];
        }
        //--下一步权限做完后，需要根据权限判断是否显示手机号
        $data['relations'] = StudentRelationService::getRelationList($studentId, $hideMobile);
        $data['instruments'] = StudentAppService::getStudentAppList($studentId);
        //性别转换
        $data['gender'] = $student['gender'];
        //学员年龄计算
        $data['birthday'] = empty($student['birthday']) ? "" : $student['birthday'];
        $data['channel'] = $channel['name'];
        $data['channel_id'] = $student['channel_id'];
        $data['parent_channel'] = $parentChannel['name'];
        $data['parent_channel_id'] = $parentChannel['id'];
        //添加学生灯条数据
        $data['ai'] = self::fetchStudentAiRecord($studentId);
        // 学员地址
        $data['address'] = StudentAddressService::getStudentAddress($studentId);
        return $data;
    }

    //获取学生AI灯条信息
    public static function fetchStudentAiRecord($studentId)
    {
        $res = [];
//        $data = AiUserModel::getRecord(['student_id' => $studentId]);
//        if(!empty($data)){
//            $res['id'] = $data['id'];
//            $res['sub_start_date'] = empty($data['sub_start_date'])?'-':date('Y-m-d', strtotime($data['sub_start_date']));
//            $res['sub_end_date'] = empty($data['sub_end_date'])?'-':date('Y-m-d', strtotime($data['sub_end_date']));
//        }
        return $res;
    }

    /**
     * 更新学生详细信息
     * @param $params
     * @return array|int
     */
    public static function updateStudentDetail($params)
    {
        $student = StudentModel::getById($params['student_id']);
        if (empty($student)){
            return Valid::addErrors([], 'student_id', 'student_not_exist');
        }

        $gender = empty($params['gender']) ? '1' : strval($params['gender']);

        $userCenter = new UserCenter();
        $updResult = $userCenter->modifyStudent($student['uuid'], $params['name'], $params['birthday'], $gender);
        if (!empty($updResult) && $updResult['code'] != 0){
            return $updResult;
        }

        $affectRow = StudentModel::updateStudent($params['student_id'], $params);

        return $affectRow;
    }

    /**
     * 获取学生通讯列表
     * @param $studentId
     * @param $hideMobile
     * @return array
     */
    public static function getStudentMobiles($studentId, $hideMobile = true)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            return [];
        }
        $row = [];
        //用户注册手机号，relation_id = 0
        $row['relation_id'] = 0;
        $row['title'] = $student['name'];
        $row['mobile'] = ($hideMobile ? Util::hideUserMobile($student['mobile']) : $student['mobile']);
        $data[] = $row;
        $relations = StudentRelationService::getRelationList($studentId);
        $data = array_merge($data, $relations);
        return $data;
    }

    /**
     * 检查是否为学生手机号
     * @param $studentId
     * @param $relation_id
     * @return bool
     */
    public static function getStudentRelationMobile($studentId, $relation_id)
    {
        $relations = self::getStudentMobiles($studentId, false);
        foreach ($relations as $relation) {
            if ($relation['relation_id'] == $relation_id) {
                return $relation['mobile'];
            }
        }
        return false;
    }

    /**
     * 通过手机号查询学生数据
     * @param $mobile
     * @return mixed
     */
    public static function getStudentByMobile($mobile)
    {
        return StudentModel::getByMobile($mobile);
    }

    /**
     * 添加学生
     * @param int $authAppId 鉴权的App
     * @param $name
     * @param $mobile
     * @param $channelId
     * @param $uuid
     * @param $countryCode
     * @param $birthday
     * @param $gender
     * @return int|mixed|null|string
     */
    public static function addStudent($authAppId, $name, $mobile, $channelId, $uuid = '', $countryCode = null, $birthday = '', $gender = 0)
    {
        $channelLevel = null;
        if (!empty($channelId)) {
            $channel = ChannelService::getChannelById($channelId);
            $channelLevel = $channel['level'];
        }
        // 用户中心授权
        $userCenter = new UserCenter();
        $authResult = $userCenter->studentAuthorization($authAppId, $mobile, $name, $uuid, $birthday, $gender);
        if (empty($authResult["uuid"])) {
            return Valid::addErrors([], "user_center", "uc_user_add_failed");
        }
        $studentId = StudentModel::insertStudent($name, $mobile, $authResult["uuid"], $channelId, $channelLevel, $countryCode, $birthday, $gender);
        if (empty($studentId)) {
            return Valid::addErrors([], 'student_id', 'add_student_failed');
        }
        return ['code' => Valid::CODE_SUCCESS, 'data' => ['studentId'=>$studentId]];
    }

    /**
     * 查找学生
     * @param $keyword
     * @return array
     */
    public static function fetchStudents($keyword)
    {
        $students = StudentModel::getStudents($keyword);
        $data = [];
        foreach ($students as $student) {
            $row = [];
            $row['student_id'] = $student['id'];
            $row['name'] = $student['name'];
            $row['mobile'] = Util::hideUserMobile($student['mobile']);
            $data[] = $row;
        }
        return $data;
    }

    /**
     * crm更新学生数据
     * @param $studentId
     * @param $params
     */
    public static function crmUpdateStudent($studentId, $params)
    {
        $data = [];
        if (isset($params['name'])) {
            $data['name'] = $params['name'];
        }
        if (isset($params['gender']) && !empty($params['gender'])) {
            $data['gender'] = $params['gender'];
        }
        if (isset($params['birthday_y'])) {
            $data['birthday'] = $params['birthday_y'];
        }
        if(!empty($data)){
            StudentModel::updateRecord($studentId, $data);
        }
    }

    /**
     * 模糊搜索，按姓名查找学生
     * @param $keyword
     * @param $limit
     * @return array
     */
    public static function searchByName($keyword,$limit){
        return StudentModel::searchByName($keyword,$limit);
    }

    /**
     * 模糊搜索，按手机号查找学生
     * @param $keyword
     * @param $limit
     * @return array
     */
    public static function searchByMobile($keyword,$limit)
    {
        return StudentModel::searchByMobile($keyword,$limit);
    }

    /**
     * 模糊搜索，按手机号查找付费学生
     * @param $keyword
     * @param $limit
     * @return array
     */
    public static function searchPaidUserByMobile($keyword,$limit)
    {
        return StudentModel::searchPaidUserByMobile($keyword,$limit);
    }

    /**
     * 获取student的基础信息
     * @param $studentId
     * @return mixed|null
     */
    public static function getStudentById($studentId)
    {
        return StudentModel::getById($studentId);
    }

    /**
     * 根据uuid获取学生信息
     * @param $uuid
     * @return mixed
     */
    public static function getByUUID($uuid)
    {
        return StudentModel::getByUUID($uuid);
    }

    /**
     * 学生注册
     * @param $params
     * @param $operatorId
     * @return int|mixed|null|string
     * @throws \Exception
     */
    public static function studentRegister($params, $operatorId = 0)
    {
        //添加学生
        $res = self::insertStudent($params, $operatorId);
        if($res['code'] != Valid::CODE_SUCCESS){
            return $res;
        }

        $studentId = $res['data']['studentId'];

        return [
            'code'       => 0,
            'student_id' => $studentId,
        ];
    }

    /**
     * 添加学生
     * 本地如果已经存在此学生，更新学生信息，返回学生id
     * 如果没有，新增学生记录，返回id
     * @param $params
     * @param int $operatorId
     * @return array
     */
    public static function insertStudent($params, $operatorId = 0)
    {
        $birthday   = $params['birthday'] ?? '';
        $gender     = $params['gender'] ?? StudentModel::GENDER_MALE;

        $userCenter = new UserCenter();

        $authResult = $userCenter->studentAuthorization(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            $params['mobile'], $params['name'], '',$birthday, strval($gender));

        if (empty($authResult["uuid"])) {
            return Valid::addErrors([], "user_center", "uc_user_add_failed");
        }

        $uuid = $authResult['uuid'];

        $student = StudentModel::getRecord([
            'uuid' => $uuid,
        ]);

        if(empty($student)) {
            $studentId = StudentModel::saveStudent($params, $authResult["uuid"], $operatorId);
            if(empty($studentId)) {
                return Valid::addErrors([], 'student', 'save_student_fail');
            }
        } else {
            $studentId = $student['id'];
            $params['update_time'] = time();
            $affectRows = StudentModel::updateRecord($studentId, $params);
            if($affectRows == 0) {
                return Valid::addErrors([], 'student', 'update_student_fail');
            }
        }

        return ['code' => Valid::CODE_SUCCESS, 'data' => ['studentId' => $studentId]];
    }

    /**
     * 获取学生ID
     * @param $uuid
     * @return mixed
     */
    public static function getStudentIdByUUID($uuid)
    {
        $student = StudentModel::getByUUID($uuid);
        return $student['id'] ?? '';
    }

    /**
     * 转介绍更新学生数据
     * @param $studentId
     * @param $params
     */
    public static function refereeUpdateStudent($studentId, $params)
    {
        $data = [];
        $data['birthday'] = $params['birthday'];
        $data['update_time'] = time();
        StudentModel::updateRecord($studentId, $data);
    }

    /**
     * 获取学生国家代码编号
     * @param $studentId
     * @return mixed
     */
    public static function getStudentCountryCode($studentId)
    {
        return StudentModel::getCountryCode($studentId);
    }

    /**
     * 查询一条指定机构和学生id的记录
     * @param $orgId
     * @param $studentId
     * @param $status
     * @return array|null
     */
    public static function getOrgStudent($orgId, $studentId, $status = null)
    {
        return StudentModel::getOrgStudent($orgId, $studentId, $status);
    }

    /**
     * 绑定学生和机构，返回lastId
     * 关系存在时候，只更新，不插入新的记录
     * 已经绑定的不会返回错误，同样返回lastId
     * @param $orgId
     * @param $studentId
     * @return ResponseError|int|mixed|null|string
     */
    public static function bindOrg($orgId, $studentId)
    {
        $studentOrg = StudentOrgModel::getRecord(['org_id' => $orgId, 'student_id' => $studentId]);
        if(empty($studentOrg)) {
            //save
            $lastId = StudentOrgModel::insertRecord([
                'org_id'      => $orgId,
                'student_id'  => $studentId,
                'status'      => StudentOrgModel::STATUS_NORMAL,
                'update_time' => time(),
                'create_time' => time(),
            ]);
            if($lastId == 0) {
                return new ResponseError('save_student_org_fail');
            }
            return $lastId;
        } else {
            //update
            if($studentOrg['status'] != StudentOrgModel::STATUS_NORMAL) {
                $affectRows = StudentOrgModel::updateStatus($orgId, $studentId, StudentOrgModel::STATUS_NORMAL);
                if($affectRows == 0) {
                    return new ResponseError('update_student_org_status_error');
                }
                return $studentOrg['id'];
            }
            return $studentOrg['id'];
        }
    }

    /**
     * 更新学生和机构关系的状态(解绑/绑定)
     * @param $orgId
     * @param $studentId
     * @param $status
     * @return int|null
     */
    public static function updateStatusWithOrg($orgId, $studentId, $status) {
        return StudentOrgModel::updateStatus($orgId, $studentId, $status);
    }
}