<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/9
 * Time: 6:14 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Models\CourseModel;
use App\Models\PrivilegeModel;
use App\Models\ScheduleModel;
use App\Models\ScheduleUserModel;
use App\Models\StudentAppModel;
use App\Models\StudentModel;
use App\Services\Queue\QueueService;

class StudentAppService
{
    /**
     * 获取学生应用列表
     * @param $studentId
     * @return array
     */
    public static function getStudentAppList($studentId)
    {
        $student_app = StudentAppModel::getStudentAppList($studentId);
        $studentWeiXinMap = [];//UserWeiXinService::getWeiXinMapByApp($studentId);
        $instrumentMap = DictService::getTypeMap(Constants::DICT_TYPE_INSTRUMENT);
        $data = [];
        foreach($student_app as $app){
            $row = [];
            $row['id'] = $app['id'];
            $row['app_name'] = $app['name'];
            $row['cc_id'] = $app['cc_id'];
            $row['cc_name'] = $app['cc_name'];
            $row['ca_id'] = $app['ca_id'];
            $row['ca_name'] = $app['ca_name'];
            $row['is_bind_weixin'] = empty($studentWeiXinMap[$app['app_id']]['open_id']) ? 0 : 1;
            $data[$app['instrument']]['instrument_name'] = $instrumentMap[$app['instrument']];
            $data[$app['instrument']]['has_instrument'] = $app['has_instrument'];
            $data[$app['instrument']]['level'] = $app['level'];
            $data[$app['instrument']]['start_year'] = $app['start_year'];
            $data[$app['instrument']]['apps'][] = $row;
        }
        return $data;
    }

    /**
     * 更新学生应用数据
     * @param $data
     */
    public static function updateStudentApp($data)
    {
        StudentAppModel::updateStudentAppData($data);
    }

    /**
     * 获取学生应用数据
     * @param $studentId
     * @return array
     */
    public static function getStudentAppMap($studentId)
    {
        $apps = StudentAppModel::getStudentAppList($studentId);
        $data = [];
        foreach ($apps as $app){
            $data[$app['id']] = $app;
        }
        return $data;
    }

    /**
     * 更新学生状态
     * @param $studentId
     * @param $status
     * @param $appId
     * @return int
     */
    public static function updateStudentStatus($studentId, $status, $appId)
    {
        if (self::isPaidStatus($studentId, $appId)) {
            return null;
        }
        return StudentAppModel::updateStudentStatus($studentId, $status, $appId);
    }

    /**
     * 更新第一次支付时间
     * @param $studentId
     * @param $appId
     * @param $firstPayTime
     * @return int
     */
    public static function updateStudentFirstPayTime($studentId,$appId,$firstPayTime){
        return StudentAppModel::updateFirstPayTime($studentId,$appId,$firstPayTime);
    }

    /**
     * 获取学生应用ID
     * @param $studentId
     * @param $appId
     * @return bool
     */
    public static function getStudentAppId($studentId, $appId)
    {
        $studentApp = StudentAppModel::getStudentApp($studentId, $appId);
        if(empty($studentApp)){
            return false;
        }
        return $studentApp['id'];
    }

    /**
     * CRM获取业务线学生信息, 没有则创建
     * @param $mobile
     * @param $appId
     * @param $studentName
     * @param $channelId
     * @param $uuId
     * @param $employeeId
     * @return mixed
     * @throws \Exception
     */
    public static function crmAddStudentApp($mobile, $appId, $studentName, $channelId, $uuId, $employeeId)
    {
        $studentApp = StudentAppModel::getStudentAppByMobile($mobile, $appId);
        if (empty($studentApp)) {
           $student = StudentService::getStudentByMobile($mobile);
           if (empty($student)) {
               // add student
               $authAppId = UserCenter::AUTH_APP_ID_STUDENT;
               $result = StudentService::addStudent($authAppId, $studentName, $mobile, $channelId, $uuId);

               if (!empty($result) && $result['code'] == Valid::CODE_PARAMS_ERROR) {
                   return Valid::addErrors([], 'student_id', 'insert_student_failed');
               }
               $student['id'] = $result['data']['studentId'];
           }

           // add student_app
           self::addStudentApp($student['id'], $appId, $employeeId);
           return StudentAppModel::getStudentAppByMobile($mobile, $appId);
        }
        return $studentApp;
    }

    /**
     * 添加学生应用信息
     * @param $studentId
     * @param $appId
     * @param $ccId
     * @param $start_year
     * @param $has_instrument
     * @return int|mixed|null|string
     */
    public static function addStudentApp($studentId, $appId, $ccId = null, $start_year = '', $has_instrument = '')
    {
        $now = time();
        $data = [
            'student_id' => $studentId,
            'app_id' => $appId,
            'create_time' => $now,
            'status'=> StudentAppModel::STATUS_REGISTER
        ];
        if (!empty($ccId)) {
            $data['cc_id'] = $ccId;
            $data['cc_update_time'] = $now;
        }
        !empty($start_year) && $data['start_year'] = $start_year;
        !empty($has_instrument) && $data['has_instrument'] = $has_instrument;
        return StudentAppModel::insertRecord($data);
    }

    /**
     * 添加学生应用信息
     * @param $studentId
     * @param $params
     * @return int|mixed|null|string
     */
    public static function erpAddStudentApp($studentId, $params)
    {
        $now = time();
        $data = [
            'student_id' => $studentId,
            'app_id' => $params['app_id'],
            'create_time' => $now,
            'status'=> StudentAppModel::STATUS_REGISTER
        ];
        if(!empty($params['has_instrument'])){
            $data['has_instrument'] = $params['has_instrument'];
        }
        if(!empty($params['start_year'])){
            $data['start_year'] = $params['start_year'];
        }
        if(!empty($params['level'])){
            $data['level'] = $params['level'];
        }
        if(!empty($params['is_manual'])){
            $data['is_manual'] = $params['is_manual'];
        }
        if(!empty($params['ca_id'])){
            $data['ca_id'] = $params['ca_id'];
            $data['ca_update_time'] = $now;
        }
        return StudentAppModel::insertRecord($data);
    }

    /**
     * 更新学生应用信息
     * @param $studentAppId
     * @param $params
     */
    public static function crmUpdateData($studentAppId, $params)
    {
        $studentAppData = [];
        if (isset($params['level'])) {
            $studentAppData['level'] = $params['level'];
        }
        if (isset($params['start_y'])) {
            $studentAppData['start_year'] = $params['start_y'];
        }
        if (isset($params['has_instrument'])) {
            $studentAppData['has_instrument'] = $params['has_instrument'];
        }
        if(!empty($studentAppData)){
            $studentAppData['update_time'] = time();
            StudentAppModel::updateRecord($studentAppId, $studentAppData);
        }
    }

    /**
     * 更新学生设备测试课信息
     * @param $studentId
     * @param $appId
     * @param $deviceScheduleId
     * @param $deviceStatus
     */
    public static function updateStudentDevice($studentId, $appId, $deviceScheduleId, $deviceStatus)
    {
        StudentAppModel::updateStudentDeviceSchedule($studentId, $appId, $deviceScheduleId, $deviceStatus);
    }

    /**
     * 学生状态改变队列
     * @param $scheduleId
     * @param $studentStatus
     * @throws \Exception
     */
    public static function pushStudentStatusQueue($scheduleId, $studentStatus)
    {
        $schedule = ScheduleService::getScheduleInfo($scheduleId);
        $studentId = $schedule['student_id'];
        $appId = $schedule['app_id'];
        if ($schedule['course_type'] == CourseModel::TYPE_TEST && !self::isPaidStatus($studentId, $appId)) {
            $student = StudentService::getStudentById($studentId);
            $queue = new QueueService();
            switch ($studentStatus) {
                case StudentAppModel::STATUS_CANCEL:
                    $queue->StudentStatusCancel($student['uuid'], $scheduleId, $appId, $studentId);
                    break;
                case StudentAppModel::STATUS_ATTEND:
                    if ($schedule['s_student_status'] == ScheduleUserModel::STUDENT_STATUS_ATTEND && $schedule['status'] == ScheduleModel::STATUS_IN_CLASS) {
                        $queue->StudentStatusAttend($student['uuid'], $scheduleId, $appId, $schedule['s_enter_time'], $studentId);
                    }
                    break;
                case StudentAppModel::STATUS_NOT_ATTEND:
                    $queue->StudentStatusNotAttend($student['uuid'], $scheduleId, $appId, $studentId);
                    break;
                case StudentAppModel::STATUS_FINISH:
                    $queue->StudentStatusFinish($student['uuid'], $scheduleId, $appId, $studentId, $schedule['s_teacher_status']);
                    break;
            }
        }
    }

    /**
     * 获取学生ID
     * @param $studentAppId
     * @return mixed
     */
    public static function getStudentIdByStudentAppId($studentAppId)
    {
        $studentApp = StudentAppModel::getById($studentAppId);
        return $studentApp['student_id'];
    }

    /**
     * 获取studentApp信息
     * @param $studentAppId
     * @return mixed|null
     */
    public static function getStudentAppById($studentAppId)
    {
        return StudentAppModel::getById($studentAppId);
    }


    /**
     * 获取学生的详细信息
     * @param $studentId
     * @param $appId
     * @return mixed
     */
    public static function getStudentInfo($studentId, $appId)
    {
        return StudentAppModel::getStudentInfo($studentId, $appId);
    }

    /**
     * 获取学生的详细信息
     * @param $studentAppId
     * @param $employeeId
     * @return mixed
     */
    public static function updateStudentCC($studentAppId, $employeeId)
    {
        return StudentAppModel::updateStudentAppCC($studentAppId, $employeeId);
    }

    /**
     * 获取学生appId
     * @param $studentId
     * @return array
     */
    public static function getAppIds($studentId)
    {
        return StudentAppModel::getAppIds($studentId);
    }

    /**
     * 更新学生应用信息
     * @param $studentAppId
     * @param $params
     */
    public static function refereeUpdateData($studentAppId, $params)
    {
        $t = time();
        $studentAppData = [];
        if (isset($params['start_year'])) {
            $studentAppData['start_year'] = $params['start_year'];
        }
        if (isset($params['has_instrument'])) {
            $studentAppData['has_instrument'] = $params['has_instrument'];
        }
        $studentAppData['update_time'] = $t;
        StudentAppModel::updateRecord($studentAppId, $studentAppData);
    }

    public static function crmUpdateStudentStatus($studentAppId, $status)
    {
        $t = time();
        $studentAppData['status'] = $status;
        $studentAppData['update_time'] = $t;
        StudentAppModel::updateRecord($studentAppId, $studentAppData);
    }

    /**
     * 更新学生等级
     * @param $studentId
     * @param $appId
     * @param $level
     * @return int|null
     */
    public static function updateStudentLevel($studentId, $appId, $level)
    {
        return StudentAppModel::updateStudentLevel($studentId, $appId, $level);
    }

    /**
     * 获取学生等级
     * @param $studentId
     * @param $appId
     * @return mixed
     */
    public static function getStudentLevel($studentId, $appId)
    {
        return StudentAppModel::getLevel($studentId, $appId);
    }

    /**
     * 低级学生等级
     * @param $level
     * @return bool
     */
    public static function isLowStudentLevel($level)
    {
        return in_array($level, [StudentAppModel::STUDENT_LEVEL_ENLIGNTENMENT, StudentAppModel::STUDENT_LEVEL_STANDARD]);
    }

    /**
     * 获取学生数据
     * @param $id
     * @param $level
     * @param $studentId
     * @param $operatorId
     * @return mixed
     */
    public static function updateStudentAppLevel($level, $id, $studentId, $operatorId)
    {
        $count = StudentAppModel::updateRecord($id, ['level' => $level]);
        $content = '手动修改, 级别：' . Dict::getStudentLevel($level);
        CommentsService::addStudentLevelRecord($studentId, $content, $operatorId, '');
        return $count;
    }

    /**
     * 获取学生数据
     * @param $id
     * @return mixed
     */
    public static function getStudentData($id)
    {
        return StudentAppModel::getStudentData($id);
    }


    public static function checkModifyStudentLevelPrivilege($operatorId)
    {
        $pathStr = Constants::DICT_MODIFY_STUDENT_LEVEL_URI;
        $method = 'post';
        $employee = EmployeeService::getById($operatorId);
        $privilege = PrivilegeModel::getPIdByUri($pathStr, $method);
        $pIds = EmployeePrivilegeService::getEmployeePIds($employee);
        return EmployeePrivilegeService::hasPermission($privilege, $pIds, $pathStr, $method);
    }

    /**
     * 导入付费学员等级信息
     * @param $data
     * @return int
     */
    public static function importPayStudentLevel($data) {
        // 获取学员等级
        $studentLevel = DictService::getList(Constants::DICT_TYPE_STUDENT_LEVEL);

        // key_value，key_code作为值的新数组
        $level = array_column($studentLevel, 'key_code', 'key_value');

        $cleanData = [];
        $mobile = [];
        $mobileLevel = [];

        foreach ($data as $key => $value) {
            // 整理后的数据
            $mobileLevel[$value[0]] = $level[$value[1]];
            // 学员的手机号码
            $mobile [] = $value[0];
        }

        // 获取手机号码对应的学员ID
        $studentInfo = StudentModel::getRecords(['mobile'  => $mobile]);
        // 学员手机号对应的ID(应用表中的student_id)
        $mobileStudentId = array_column($studentInfo, null,'mobile');
        foreach ($mobileLevel as $k => $v) {
            if(!empty($mobileStudentId[$k]['mobile']) && $k == $mobileStudentId[$k]['mobile']) {
                $cleanData[$v][] = $mobileStudentId[$k]['id'];
            }
        }

        $cnt = 0;
        // 更新次数较少，更新次数与等级个数有关
        foreach ($cleanData as $k => $v) {
            // 批量更新学员等级信息,$k为等级信息，$v学员ID
            $count = StudentAppModel::batchUpdateStudentLevel($k, $v);
            $cnt += $count;
        }
        return $cnt;
    }

    /**
     * 批量更新学生课管
     * @param  array $students 
     * @param  int $ca_id    
     * @return 
     */
    public static function batchUpdateStudentCA($app_id,$students,$ca_id){
        return StudentAppModel::batchUpdateRecord([
            'ca_id' => $ca_id, 
            'ca_update_time' => time()
        ],['student_id' => $students,'app_id' => $app_id]);
    }

    /**
     * 获取学生状态
     * @param $studentId
     * @param $appId
     * @return mixed
     */
    public static function isPaidStatus($studentId, $appId)
    {
        $status = StudentAppModel::getStudentStatus($studentId, $appId);
        if ($status == StudentAppModel::STATUS_PAID) {
            return true;
        }
        return false;
    }
}