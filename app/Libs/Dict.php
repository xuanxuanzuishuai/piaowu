<?php

namespace App\Libs;

use App\Services\DictService;

/**
 * 字典
 */
class Dict
{

    /**
     * 返回是否
     * @param $status
     * @return string
     */
    public static function isOrNotStr($status)
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_YES_OR_NO, $status);
    }

    /**
     * 返回正常、废除
     * @param $status
     * @return string
     */
    public static function normalOrInvalidStr($status)
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_NORMAL_OR_INVALID, $status);
    }

    public static function getOrgCCRoleId()
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_CC_ROLE_ID_CODE_ORG);
    }
}
