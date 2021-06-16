<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2020/12/10
 * Time: 11:37 AM
 */

namespace App\Services;

use App\Models\Erp\OpnCollectionModel;

class ErpOpnService
{
    /**
     * 曲谱教材下拉框搜索
     * @param $params
     * @return mixed
     */
    public static function opnDropDownSearch($params)
    {
        $where = [];
        if (!empty($params['opn_name'])) {
            $where['name[~]'] = $params['opn_name'];
        }

        if (!empty($params['opn_id'])) {
            $where['id'] = $params['opn_id'];
        }
        if (empty($where)) {
            return [];
        }
        $where['type'] = OpnCollectionModel::TYPE_SIGN_UP;
        return OpnCollectionModel::getRecords($where, ['id', 'name']);
    }
}
