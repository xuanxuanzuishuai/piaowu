<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/6/22
 * Time: 3:29 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\ListTree;
use App\Libs\Util;
use App\Models\DeptModel;

class DeptService
{
    /**
     * 组织架构关系树形列表
     * @return array
     */
    public static function tree()
    {
        $tree = DeptModel::getTree();

        return ['tree' => $tree];
    }

    /**
     * 列表
     * @param $params
     * @return array
     */
    public static function list($params)
    {
        $where = [];

        if (!empty($params['id'])) {
            $where['d.id'] = $params['id'];
        } elseif (!empty($params['name'])) {
            $where['d.name[~]'] = Util::sqlLike($params['name']);
        }

        if (isset($params['status'])) {
            $where['d.status'] = $params['status'];
        }

        if (isset($params['parent_id'])) {
            $where['d.parent_id'] = $params['parent_id'];
        }

        list($page, $count) = Util::formatPageCount($params);

        list($records, $totalCount) = DeptModel::list($where, $page, $count);

        return [$records, $totalCount];
    }

    /**
     * @param $params
     * @param $operator
     * @return int
     * @throws RunTimeException
     */
    public static function modify($params, $operator)
    {
        if (isset($params['id'])) {
            $ret = self::update($params, $operator);
        } else {
            $ret = self::new($params, $operator);
        }

        return $ret;
    }

    /**
     * @param $params
     * @param $operator
     * @return int
     * @throws RunTimeException
     */
    public static function new($params, $operator)
    {
        $now = time();

        if (!empty($params['parent_id'])) {
            $parent = DeptModel::getById($params['parent_id']);

            if (empty($parent)) {
                throw new RunTimeException(['parent_not_found']);
            }
        }

        $insert = [
            'name' => $params['name'],
            'parent_id' => $params['parent_id'] ?? 0,
            'status' => Constants::STATUS_TRUE,
            'create_time' => $now,
            'update_time' => 0,
            'operator' => $operator,
        ];

        $id = DeptModel::insertRecord($insert);

        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }

        return $id;
    }

    /**
     * @param $params
     * @param $operator
     * @return int
     * @throws RunTimeException
     */
    public static function update($params, $operator)
    {
        $dept = DeptModel::getById($params['id']);
        if (empty($dept)) {
            throw new RunTimeException(['record_not_found']);
        }

        $update = [];

        if (isset($params['parent_id']) && $params['parent_id'] != $dept['parent_id']) {
            if ($params['parent_id'] == $dept['id']) {
                throw new RunTimeException(['dept_invalid_parent_id']);
            }

            if ($params['parent_id'] == 0) {
                $update['parent_id'] = 0;

            } else {
                $parent = DeptModel::getById($params['parent_id']);
                if (empty($parent)) {
                    throw new RunTimeException(['record_not_found']);
                }

                $update['parent_id'] = $params['parent_id'];
            }
        }

        if (isset($params['status']) && $params['status'] != $dept['status']) {
            $update['status'] = $params['status'];
        }

        if (isset($params['name']) && $params['name'] != $dept['name']) {
            $update['name'] = $params['name'];
        }

        if (empty($update)) {
            throw new RunTimeException(['nothing_change']);
        }

        $update['update_time'] = time();
        $update['operator'] = $operator;

        $count = DeptModel::updateRecord($params['id'], $update);

        if (empty($count)) {
            throw new RunTimeException(['update_failure']);
        }

        return $count;
    }
}