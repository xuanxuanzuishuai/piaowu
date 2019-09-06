<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/9/3
 * Time: 11:59 AM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\FilterModel;
use App\Models\FlagsModel;

class FilterService
{
    const CONDITION_TYPES = ['>', '==', '<', '!=', 'in'];
    const CONDITION_KEYS = [
        'id', 'uuid', 'mobile', 'gender', 'create_time', 'channel_id',
        'sub_start_date', 'sub_end_date', 'trial_start_date', 'trial_end_date',
        'first_pay_time', 'platform', 'version'
    ];

    /**
     * 过滤器列表
     * @param $params
     * @return array
     */
    public static function list($params)
    {
        $where = [];
        if (!empty($params['name'])) {
            $where['name[~]'] = $params['name'];
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $where['status'] = $params['status'];
        }

        if (!empty($params['flag_id'])) {
            $where['flag_id'] = $params['flag_id'];
        }

        $count = FilterModel::countRecords($where);
        if (empty($count)) {
            $list = [];
        } else {
            list($page, $pageSize) = Util::formatPageCount($params);
            $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];
            $list = FilterModel::getRecords($where, '*', false);
        }

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

        $meta = self::getMeta();

        return ['list' => $list, 'total_count' =>$count, 'meta' => $meta];
    }

    /**
     * 添加过滤器
     * @param $flagId
     * @param $name
     * @param $conditions
     * @param $operator
     * @return mixed|null
     * @throws RunTimeException
     */
    public static function add($flagId, $name, $conditions, $operator)
    {
        $flag = FlagsModel::getById($flagId);
        if (empty($flag)) {
            throw new RunTimeException(['invalid_flag']);
        }

        if (!self::validateConditions($conditions)) {
            throw new RunTimeException(['invalid_conditions']);
        }

        $data = [
            'name' => $name,
            'flag_id' => $flagId,
            'create_time' => time(),
            'status' => Constants::STATUS_FALSE,
            'conditions' => $conditions,
            'operator' => $operator,
        ];
        $id = FilterModel::insertRecord($data, false);

        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }

        return FilterModel::getById($id);
    }

    /**
     * 编辑过滤器
     * @param $id
     * @param $data
     * @param $operator
     * @return mixed
     * @throws RunTimeException
     */
    public static function modify($id, $data, $operator)
    {
        $flag = FilterModel::getById($id);
        if (empty($flag)) {
            throw new RunTimeException(['record_not_found']);
        }

        if (!empty($data['conditions'])) {
            if (!self::validateConditions($data['conditions'])) {
                throw new RunTimeException(['invalid_conditions']);
            }
        }

        $validFields = ['name', 'flag_id', 'status', 'conditions'];
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
        $update['update_time'] = time();

        $cnt = FilterModel::updateRecord($id, $update, false);
        if (empty($cnt)) {
            throw new RunTimeException(['update_failure']);
        }

        return FilterModel::getById($id);
    }

    public static function meta()
    {
        return [
            'flags' => [],
            'keys' => [],
            'types' => [],
        ];
    }

    public static function checkFlagFilters($object, $flagId)
    {
        $filters = self::getFilterByFlag($flagId);
        if (empty($filters)) {
            return false;
        }

        foreach ($filters as $filter) {
            $conditions = json_decode($filter['conditions'], true);

            if (empty($conditions)) {
                return false;
            }

            if (!self::checkAllConditions($object, $conditions)) {
                return false;
            }
        }

        return true;
    }

    public static function checkAllConditions($object, $conditions)
    {
        foreach ($conditions as $condition) {
            if (!self::checkCondition($object, $condition)) {
                return false;
            }
        }
        return true;
    }

    public static function checkCondition($object, $condition)
    {
        $key = $condition['key'];

        if (!isset($object[$key])) {
            return false;
        }

        switch ($condition['type']) {
            case '>':
                return  $object[$key] > $condition['value'];
                break;
            case '==':
                return  $object[$key] == $condition['value'];
                break;
            case '<':
                return  $object[$key] < $condition['value'];
                break;
            case '!=':
                return $object[$key] != $condition['value'];
                break;
            case 'in':
                return in_array($object[$key], explode(',', $condition['value']));
                break;
            default:
                return false;
        }
    }

    public static function getFilterByFlag($flagId)
    {
        $filters = FilterModel::getByFlagId($flagId);
        return $filters;
    }

    /**
     * 检查过滤器条件数据
     * @param $conditions
     * @return bool
     */
    public static function validateConditions($conditions)
    {
        $conditions = json_decode($conditions, true);
        if (!(is_array($conditions) && !empty($conditions))) {
            return false;
        }

        foreach ($conditions as $c) {
            if (empty($c['key'])) {
                return false;
            }
            if (!in_array($c['key'], self::CONDITION_KEYS)) {
                return false;
            }
            if (!in_array($c['type'], self::CONDITION_TYPES)) {
                return false;
            }
            if ($c['type'] == 'in' && empty($c['value'])) {
                return false;
            }
        }
        return true;
    }

    public static function getMeta()
    {
        $flags = FlagsModel::getRecords([], ['id', 'name', 'status'], false);

        return [
            'flags' => $flags,
            'condition_types' => self::CONDITION_TYPES,
            'condition_keys' => self::CONDITION_KEYS
        ];
    }
}