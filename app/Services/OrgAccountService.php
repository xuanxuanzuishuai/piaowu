<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/26
 * Time: 下午3:09
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Util;
use App\Models\OrgAccountModel;

class OrgAccountService
{
    public static function selectByPage($page, $count, $params)
    {
        list($page, $count) = Util::formatPageCount(['page' => $page, 'count' => $count]);

        list($records, $total) = OrgAccountModel::selectByPage($page, $count, $params);

        foreach($records as &$r) {
            $r['status'] = DictService::getKeyValue(Constants::DICT_TYPE_ORG_ACCOUNT_STATUS, $r['status']);
        }

        return [$records, $total];
    }
}