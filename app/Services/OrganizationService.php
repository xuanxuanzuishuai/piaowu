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
        return OrganizationModel::insertRecord($data, false);
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
            $r['amount'] /= 100;
        }
        return [$records, $total];
    }

    /**
     * 按机构名称模糊搜索机构
     * @param $key
     * @return array
     */
    public static function fuzzySearch($key)
    {
        return OrganizationModel::getRecords([
            'name[~]' => $key
        ],['id','name'],false);
    }

    /**
     * 根据机构ID查询一条记录
     * @param $id
     * @return mixed
     */
    public static function getInfo($id)
    {
        return OrganizationModel::getInfo($id);
    }

    public static function generateOrganization($orgId) {
        $baseUrl = $_ENV["TEACHER_WECHAT_VUE_URL"] . "/" . "?org_id=" . $orgId;
        return OrganizationModel::getInfo($id);
    }
}