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
        $where = [
            'name[~]' => $params['opn_name'] . '%',
            'type' => OpnCollectionModel::TYPE_SIGN_UP,
        ];
        return OpnCollectionModel::getRecords($where, ['id', 'name']);
    }
}
