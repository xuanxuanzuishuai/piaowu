<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/9/8
 * Time: 10:23 AM
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Models\ActivityCenterModel;
use App\Services\ActivityCenterService;
use Slim\Http\Request;
use Slim\Http\Response;


class ActivityCenter extends ControllerBase
{

    /**
     * 获取列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);

        $params['channel'] = ActivityCenterModel::CHANNEL_WX;
        $data = ActivityCenterService::getUserList($params, $this->ci['user_info']['user_id'], $page, $count);
        return HttpHelper::buildResponse($response, $data);
    }
}
