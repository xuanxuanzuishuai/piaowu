<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/17
 * Time: 下午7:25
 */

namespace App\Services;


use App\Libs\Constants;
use App\Models\OrganizationModel;

class OrganizationService
{

    /**
     * 保存机构
     * @param $data
     * @return int|mixed|null|string
     */
    public static function save($data)
    {
        return OrganizationModel::insertRecord($data);
    }

    /**
     * 更新机构
     * @param $id
     * @param $data
     * @return int|null
     */
    public static function updateById($id, $data)
    {
        return OrganizationModel::updateRecord($id, $data);
    }

    /**
     * 查询机构列表
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function selectOrgList($page, $count, $params)
    {
        list($records, $total) = OrganizationModel::selectOrgList($page, $count, $params);
        foreach($records as &$r) {
            $r['status'] = DictService::getKeyValue(Constants::DICT_TYPE_ORG_STATUS, $r['status']);
        }
        return [$records, $total];
    }
}