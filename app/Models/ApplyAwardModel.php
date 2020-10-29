<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/11/5
 * Time: 8:33 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class ApplyAwardModel extends Model
{
    public static $table = "apply_award";
    const START_SUPPLY = 1; //发起审批

    public static function getList($params, $page = 1, $count = 20)
    {
        $where = [
            'ORDER' => [self::$table . '.id' => 'DESC'],
            'LIMIT' => [($page - 1) * $count, $count]
        ];
        if (!empty($params['id'])) {
            $where[self::$table . '.id'] = $params['id'];
        }
        if (!empty($params['student_name'])) {
            $studentIdInfo = StudentModel::getRecords(['name[~]' => $params['student_name']], 'id');
            $where[self::$table . '.student_id'] = $studentIdInfo;
        }

        if (!empty($params['mobile'])) {
            $studentIdInfo = StudentModel::getRecords(['mobile[~]' => $params['mobile']], 'id');
            $where[self::$table . '.student_id'] = $studentIdInfo;
        }

        if (!empty($params['event_task_id'])) {
            $where[self::$table . '.expect_event_task_id'] = $params['event_task_id'];
        }

        if (!empty($params['status'])) {
            $where[self::$table . '.status'] = $params['status'];
        }

        if (!empty($params['employee_name'])) {
            $employeeUuidInfo = EmployeeModel::getRecords(['name[~]' => $params['employee_name']], 'uuid');
            $where[self::$table . '.supply_employee_uuid'] = $employeeUuidInfo;
        }

        if (!empty($params['supply_start_time'])) {
            $where[self::$table . '.create_time[>=]'] = strtotime($params['supply_start_time']);
        }
        if (!empty($params['supply_end_time'])) {
            $where[self::$table . '.create_time[<]'] = strtotime($params['supply_end_time']);
        }

        return MysqlDB::getDB()->select(self::$table,
        [
            '[><]' . StudentModel::$table => ['student_id' => 'id'],
            '[><]' . EmployeeModel::$table => ['supply_employee_uuid' => 'uuid']
        ],
        [
            self::$table . '.id',
            StudentModel::$table . '.name(student_name)',
            StudentModel::$table . '.mobile',
            self::$table . '.status',
            self::$table . '.expect_event_task_id',
            self::$table . '.amount',
            EmployeeModel::$table . '.name(employee_name)',
            self::$table . '.create_time',
            self::$table . '.image_key',
            self::$table . '.cash_deal_id',
            self::$table . '.workflow_id',
            self::$table . '.reissue_reason'
        ], $where);
    }
}
