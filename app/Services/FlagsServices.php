<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/9/3
 * Time: 2:51 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\EmployeeModel;
use App\Models\FlagsModel;

class FlagsServices
{
    /**
     * 获取标签列表
     * @param $params
     * @return array
     */
    public static function list($params)
    {
        $where = [];
        if (!empty($params['name'])) {
            $where['name[~]'] = $params['name'];
        }

        if (isset($params['status'])) {
            $where['status'] = $params['status'];
        }

        $list = FlagsModel::getRecords($where, '*', false);

        $operatorNameMap = [
            EmployeeModel::SYSTEM_EMPLOYEE_ID => EmployeeModel::SYSTEM_EMPLOYEE_NAME
        ];
        foreach ($list as $i => $flag) {
            $operatorId = $flag['operator'];
            if (empty($operatorNameMap[$operatorId])) {
                $operator = EmployeeModel::getById($operatorId);
                $operatorNameMap[$operatorId] = $operator['name'] ?? 'unknown';
            }
            $list[$i]['operator_name'] = $operatorNameMap[$operatorId];
        }

        return ['list' => $list];
    }

    /**
     * 添加标签
     * @param $name
     * @param $desc
     * @param $operator
     * @return mixed|null
     * @throws RunTimeException
     */
    public static function add($name, $desc, $operator)
    {
        $data = [
            'name' => $name,
            'desc' => $desc,
            'create_time' => time(),
            'status' => Constants::STATUS_TRUE,
            'operator' => $operator,
        ];
        $id = FlagsModel::insertRecord($data, false);

        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }

        return FlagsModel::getById($id);
    }

    /**
     * 修改标签
     * @param $id
     * @param $data
     * @param $operator
     * @return mixed|null
     * @throws RunTimeException
     */
    public static function modify($id, $data, $operator)
    {
        $flag = FlagsModel::getById($id);
        if (empty($flag)) {
            throw new RunTimeException(['record_not_found']);
        }

        $validFields = ['name', 'desc', 'status'];
        $update = [];
        foreach ($data as $k => $v) {
            if (!in_array($k, $validFields)) {
                throw new RunTimeException(['invalid_update_fields']);
            }
            if ($flag[$k] != $v) {
                $update[$k] = $v;
            }
        }

        if (empty($update)) {
            throw new RunTimeException(['nothing_change']);
        }

        $update['operator'] = $operator;

        $cnt = FlagsModel::updateRecord($id, $update, false);
        if (empty($cnt)) {
            throw new RunTimeException(['update_failure']);
        }

        return FlagsModel::getById($id);
    }
}