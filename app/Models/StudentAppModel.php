<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/6
 * Time: 8:09 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class StudentAppModel extends Model
{
    public static $table = 'student_app';
    public static $redisExpire = 1;

    const STATUS_REGISTER = 1;      // 注册
    const STATUS_BOOK = 2;          // 已预约
    const STATUS_CONFIRM = 3;       // 待出席
    const STATUS_ATTEND = 4;        // 已出席
    const STATUS_FINISH = 41;       // 已完课
    const STATUS_NOT_ATTEND = 5;    // 未出席
    const STATUS_CANCEL = 6;        // 已取消
    const STATUS_PAID = 7;          // 付费

    const DEVICE_PASS = 1;          // 设备测试通过
    const DEVICE_NOT_PASS = 0;      // 设备测试未通过
    const MANUAL_ENTRY = 1;         // 手动添加学生

    /** @var int 乐器演奏等级  0 未定级 1 启蒙 2 标准 3 资深 4 高级 5 特级 */
    const STUDENT_LEVEL_UNDEFINED = 0;      // 未定级
    const STUDENT_LEVEL_ENLIGNTENMENT = 1;  // 启蒙
    const STUDENT_LEVEL_STANDARD = 2;       // 标准
    const STUDENT_LEVEL_SENIOR = 3;         // 资深
    const STUDENT_LEVEL_ADVANCED = 4;       // 高级
    const STUDENT_LEVEL_SPECIAL = 5;       // 特级


    const FIRST_PAY_YES = 1;      // 首次付费
    const FIRST_PAY_NO = 0;       // 不是首次付费

    /**
     * 获取学生应用列表
     * @param $studentId
     * @return array
     */
    public static function getStudentAppList($studentId)
    {
        $db = MysqlDB::getDB();
        $sql = "SELECT `app`.*, `ea`.name, `ea`.instrument, 
                       `cau`.`name` AS `ca_name`,
                       `ccu`.`name` AS `cc_name`
                FROM `".self::$table."` AS `app`
                LEFT JOIN `".AppModel::$table."` AS `ea` ON app.app_id = ea.id 
                LEFT JOIN `".EmployeeModel::$table."` AS `cau` ON app.`ca_id` = `cau`.`id`
                LEFT JOIN `".EmployeeModel::$table."` AS `ccu` ON app.`cc_id` = `ccu`.`id`
                WHERE `app`.`student_id` = :student_id ";
        return $db->queryAll($sql, [':student_id'=>$studentId]);
    }

    /**
     * 更新学生应用数据
     * @param $data
     */
    public static function updateStudentAppData($data)
    {
        $appData = self::makeStudentAppData($data);
        foreach($appData as $item) {
            self::updateRecord($item['id'], $item);
        }
    }

    /**
     * 生成学生应用更新数据
     * @param $instruments
     * @return array
     */
    public static function makeStudentAppData($instruments)
    {
        $data = [];
        $t = time();
        foreach($instruments as $instrument){
            foreach($instrument['apps'] as $app){
                $row = [];
                $row['id'] = $app['id'];
                if(!empty($app['ca_id'])){
                    if(self::checkUpdateCa($app['ca_id'], $app['id'])){
                        $row['ca_id'] = $app['ca_id'];
                        $row['ca_update_time'] = $t;
                    }
                }
                $row['has_instrument'] = $instrument['has_instrument'];
                $row['level'] = $instrument['level'];
                $row['start_year'] = $instrument['start_year'];
                $row['update_time'] = $t;
                $data[] = $row;
            }
        }

        return $data;
    }

    /**
     * 判断学员CA是否变更
     * @param $caId
     * @param $studentAppId
     * @return bool
     */
    public static function checkUpdateCa($caId, $studentAppId){
        $data = self::getById($studentAppId);
        return $caId == $data['ca_id'] ? false : true;
    }

    /**
     * 更新学生状态
     * @param $studentId
     * @param $status
     * @param $appId
     * @return int|null
     */
    public static function updateStudentStatus($studentId, $status, $appId)
    {
        return self::batchUpdateRecord([
            'status' => $status,
            'update_time' => time()
        ], [
            'student_id' => $studentId,
            'app_id' => $appId
        ]);
    }

    /**
     * 更新设备测试课课程状态
     * @param $studentId
     * @param $appId
     * @param $deviceScheduleId
     * @param $deviceStatus
     * @return int|null
     */
    public static function updateStudentDeviceSchedule($studentId, $appId, $deviceScheduleId, $deviceStatus)
    {
        $update = [
            'device_schedule_id' => $deviceScheduleId,
            'update_time' => time()
        ];
        if (isset($deviceStatus)) {
            $update['device_status'] = $deviceStatus;
        }
        return self::batchUpdateRecord($update, [
            'student_id' => $studentId,
            'app_id' => $appId
        ]);
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
        return self::batchUpdateRecord([
            'level' => $level,
            'update_time' => time()
        ], [
            'student_id' => $studentId,
            'app_id' => $appId
        ]);
    }

    /**
     * 获取学生应用数据
     * @param $studentId
     * @param $appId
     * @return mixed
     */
    public static function getStudentApp($studentId, $appId)
    {
        return MysqlDB::getDB()->get(self::$table, '*', ['student_id' => $studentId, 'app_id' => $appId]);
    }

    /**
     * 根据手机号、业务线获取学生信息
     * @param $mobile
     * @param $appId
     * @return mixed
     */
    public static function getStudentAppByMobile($mobile, $appId)
    {
        return MysqlDB::getDB()->get(self::$table, [
            '[><]' . StudentModel::$table => ['student_id' => 'id']
        ], [
            self::$table . '.level',
            self::$table . '.student_id',
            StudentModel::$table . '.name',
            StudentModel::$table . '.mobile',
            StudentModel::$table . '.uuid',
            StudentModel::$table . '.channel_id'
        ], [
            StudentModel::$table . '.mobile' => $mobile,
            self::$table . '.app_id' => $appId
        ]);
    }

    /**
     * 获取学生的详细信息
     * @param $studentId
     * @param $appId
     * @return mixed
     */
    public static function getStudentInfo($studentId, $appId)
    {
        return MysqlDB::getDB()->get(self::$table, [
            '[><]' . StudentModel::$table => ['student_id' => 'id']
        ], [
            self::$table . '.id(student_app_id)',
            self::$table . '.level',
            self::$table . '.student_id',
            self::$table . '.start_year',
            self::$table . '.status',
            self::$table . '.has_instrument',
            self::$table . '.device_schedule_id',
            self::$table . '.device_status',
            self::$table . '.create_time',
            StudentModel::$table . '.name',
            StudentModel::$table . '.uuid',
            StudentModel::$table . '.birthday',
            StudentModel::$table . '.gender',
            StudentModel::$table . '.channel_id',
            StudentModel::$table . '.channel_level',
            StudentModel::$table . '.mobile'
        ], [
            self::$table . '.student_id' => $studentId,
            self::$table . '.app_id' => $appId
        ]);
    }

    /**
     * 更新学生CC数据
     * @param $studentAppId
     * @param $employeeId
     * @return int|null
     */
    public static function updateStudentAppCC($studentAppId, $employeeId)
    {
        $update = [
            'cc_id' => $employeeId,
            'cc_update_time' => time()
        ];
        return self::updateRecord($studentAppId, $update);
    }

    /**
     * 获取学生的AppId
     * @param $studentId
     * @return array
     */
    public static function getAppIds($studentId)
    {
        return MysqlDB::getDB()->select(self::$table, 'app_id', ['student_id' => $studentId]);
    }

    /**
     * 获取学生等级
     * @param $studentId
     * @param $appId
     * @return mixed
     */
    public static function getLevel($studentId, $appId)
    {
        return MysqlDB::getDB()->get(self::$table, 'level', ['student_id' => $studentId, 'app_id' => $appId]);
    }

    /**
     * 获取学生数据
     * @param $id
     * @return mixed
     */
    public static function getStudentData($id)
    {
        return MysqlDB::getDB()->get(self::$table.'(sa)', [
        '[><]'.StudentModel::$table.'(s)' => ['sa.student_id' => 'id']
        ], ['s.id(student_id)', 's.uuid', 'sa.app_id', 'sa.status'], ['sa.id' => $id]);
    }

    /**
    * 更新第一次支付时间
    * @param $studentId
    * @param $appId
    * @param $firstPayTime
    * @return int
    */
    public static function updateFirstPayTime($studentId,$appId,$firstPayTime)
    {
        $where = [
            'student_id'     => $studentId,
            'app_id'         => $appId,
            'first_pay_time' => null,
        ];
        return MysqlDB::getDB()->update(self::$table, ['first_pay_time' => $firstPayTime], $where)->rowCount();
    }


    /**
     * 批量更新学员等级
     * @param $level
     * @param $where
     * @return int
     */
    public static  function batchUpdateStudentLevel($level, $where) {
        return StudentAppModel::batchUpdateRecord(['level' => $level], ['student_id'  => $where]);
    }

    /**
     * 获取学生状态
     * @param $studentId
     * @param $appId
     * @return mixed
     */
    public static function getStudentStatus($studentId, $appId)
    {
        return MysqlDB::getDB()->get(self::$table, 'status', ['student_id' => $studentId, 'app_id' => $appId]);
    }
}