<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/11
 * Time: 15:41
 */

namespace App\Controllers\Real;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Models\CopyManageModel;
use App\Services\CopyManageService;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * 真人业务线学生端商城接口控制器文件
 * Class StudentActivity
 * @package App\Routers
 */
class MagicStoneShop extends ControllerBase
{
    /**
     * 获取魔法石商城规则说明文案
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function ruleDesc(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $data = CopyManageService::getRuleDesc(CopyManageModel::RULE_DESC_TYPE_REAL_MAGIC_STONE);
        return HttpHelper::buildResponse($response, $data);
    }
}
