<?php


namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\DictModel;
use App\Models\SourceMaterialModel;

class SourceMaterialService
{

    const SOURCE_TYPE_CONFIG = 'source_type_config';

    const SOURCE_ENABLE_STATUS = 'source_enable_status';

    /**
     * 素材添加
     * @param $data
     * @param $employeeId
     * @return int|mixed|string
     * @throws RunTimeException
     */
    public static function soucrceAdd($data, $employeeId)
    {
        $data['create_time'] = time();
        $data['operator_id'] = $employeeId;
        $data['mark']        = !empty($data['mark']) ? Util::textEncode($data['mark']) : '';

        $res = SourceMaterialModel::insertRecord($data);
        if (empty($res)) {
            SimpleLogger::error('source material add fail', $data);
            throw new RunTimeException(['insert_failure']);
        }
        return $res;
    }

    /**
     * 素材编辑
     * @param $data
     * @param $employeeId
     * @return int
     * @throws RunTimeException
     */
    public static function soucrceEdit($data, $employeeId)
    {
        $sourceMaterial = SourceMaterialModel::getRecord(['id' => $data['id']]);
        if (empty($sourceMaterial)) {
            throw new RunTimeException(['record_not_found']);
        }
        $update = [
            'name' => $data['name'],
            'mark' => !empty($data['mark']) ? Util::textEncode($data['mark']) : '',
        ];
        if ($sourceMaterial['enable_status'] == 1) {
            $update['type']       = $data['type'];
            $update['image_path'] = $data['image_path'];
        }
        $update['update_time'] = time();
        $update['operator_id'] = $employeeId;
        $res = SourceMaterialModel::updateRecord($data['id'], $update);
        if (empty($res)) {
            SimpleLogger::error('source material edit fail', $update);
            throw new RunTimeException(['update_failure']);
        }
        return $res;
    }

    /**
     * 素材库列表
     * @param $params
     * @param $page
     * @param $limit
     * @return array
     */
    public static function sourceList($params, $page, $limit)
    {
        $limitOffset = [($page - 1) * $limit, $limit];
        list($lists, $totalCount) = SourceMaterialModel::sourceList($params, $limitOffset);
        if (empty($lists)) {
            return compact('lists', 'totalCount');
        }
        $dictInfos = DictService::getList(self::SOURCE_TYPE_CONFIG);
        $dictInfos = !empty($dictInfos) ? array_column($dictInfos, 'key_value', 'key_code') : [];
        foreach ($lists as &$val) {
            $val['status']        = $val['enable_status'];
            $val['type_value']    = $dictInfos[$val['type']] ?? '';
            $val['image_path']    = AliOSS::replaceCdnDomainForDss($val['image_path']);
            $val['enable_status'] = SourceMaterialModel::$enableStatusLists[$val['enable_status']] ?? '未知';
            $val['create_time']   = date('Y-m-d H:i:s', $val['create_time']);
        }
        return compact('lists', 'totalCount');
    }

    /**
     * 素材类型添加
     * @param $data
     * @param $employeeId
     * @return int|mixed|string
     * @throws RunTimeException
     */
    public static function sourceTypeAdd($data)
    {
        //查询是否有重复的
        $conds = [
            'type'      => self::SOURCE_TYPE_CONFIG,
            'key_value' => $data['name']
        ];
        $count = DictModel::getCount($conds);
        if ($count > 0) {
            throw new RunTimeException(['source_type_is_repeat']);
        }
        $insert = [
            'type'      => self::SOURCE_TYPE_CONFIG,
            'key_value' => $data['name']
        ];
        unset($conds['key_value']);
        $conds['ORDER']     = ['id' => 'DESC'];
        $dict               = DictModel::getRecord($conds, ['key_code']);

        $insert['key_code'] = empty($dict) ? 0 : $dict['key_code'] + 1;
        $res                = DictModel::insertRecord($insert);
        if (empty($res)) {
            SimpleLogger::error('source material type add fail', $insert);
            throw new RunTimeException(['source_material_type_add_fail']);
        }
        // 缓存失效
        DictModel::delCache(self::SOURCE_TYPE_CONFIG, 'dict_list_');
        return $res;
    }

    /**
     * 启用状态修改
     * @param $id
     * @param $enableStatus
     * @param $employeeId
     * @return int
     * @throws RunTimeException
     */
    public static function editEnableStatus($id, $enableStatus, $employeeId)
    {
        $sourceMaterial = SourceMaterialModel::getRecord(['id' => $id]);
        if (empty($sourceMaterial) || $sourceMaterial['enable_status'] != SourceMaterialModel::NOT_ENABLED_STATUS) {
            throw new RunTimeException(['record_not_found']);
        }
        $update = [
            'enable_status' => $enableStatus,
            'operator_id'   => $employeeId,
        ];
        $res    = SourceMaterialModel::updateRecord($id, $update);
        if (empty($res)) {
            throw new RunTimeException(['update_failure']);
        }
        return $res;
    }

}
