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
use App\Models\StudentModel;

class FlagsService
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

    /**
     * 修改学生标签
     * @param $id
     * @param $flagsArray
     * @return array
     * @throws RunTimeException
     */
    public static function modifyStudent($id, $flagsArray)
    {
        $student = StudentModel::getById($id);
        if (empty($student)) {
            throw new RunTimeException(['unknown_student']);
        }

        $flags = self::arrayToFlags($flagsArray);

        if ($flags == $student['flags']) {
            throw new RunTimeException(['nothing_change']);
        }

        $update = [
            'update_time' => time(),
            'flags' => $flags
        ];
        $cnt = StudentModel::updateRecord($id, $update, false);
        if (empty($cnt)) {
            throw new RunTimeException(['update_failure']);
        }

        return [];
    }

    /**
     * 检查是否有指定标签
     * @param $object
     * @param $flagId
     * @return bool
     */
    public static function hasFlag($object, $flagId)
    {
        $flagBit = self::getFlagBit($flagId);
        $flags = $object['flags'];
        if ($flags & $flagBit == $flagBit) {
            return true;
        }

        $hasAutoFlag = FilterService::checkFlagFilters($object, $flagId);
        if ($hasAutoFlag) {
            return true;
        }

        return false;
    }

    /**
     * 获取标签的bit
     * flagId -> bit
     * 1 -> 1(...0001)
     * 2 -> 2(...0010)
     * 3 -> 4(...0100)
     * 4 -> 8(...1000)
     * ...
     * 63 -> 4611...(0100...)
     * 64 -> -922...(1000...)
     * 最大64位
     * @param $flagId
     * @return int
     */
    public static function getFlagBit($flagId)
    {
        return 1<<($flagId-1);
    }

    /**
     * 将 flags 转为数组
     * 2(10) => [2]
     * 9(1001) => [1, 8]
     * 38(100110) => [2, 4, 32]
     * @param $flags
     * @return array
     */
    public static function flagsToArray($flags)
    {
        $flags = decbin($flags);
        $len = strlen($flags);
        $flagArray = [];
        for($i = 1; $i <= $len; $i++) {
            if ($flags[$len-$i]) {
                $flagArray[] = $i;
            }
        }
        return $flagArray;
    }

    /**
     * 将数组转为 flags
     * @param $flagsArray
     * @return int
     */
    public static function arrayToFlags($flagsArray)
    {
        $flags = 0;
        foreach ($flagsArray as $bit) {
            $flags |= (1<<(intval($bit)-1));
        }
        return $flags;
    }

    /**
     * 获取当前可用标签
     */
    public static function getValidFlags()
    {
        $flagHash = FlagsModel::getHash();
        return ['valid_flags' => $flagHash];
    }
}