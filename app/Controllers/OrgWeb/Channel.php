<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/3
 * Time: 10:55
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Services\ChannelService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\Valid;

class Channel extends ControllerBase
{
    /**
     * 获取渠道
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getChannels(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'parent_channel_id',
                'type' => 'required',
                'error_code' => 'parent_channel_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $channels = ChannelService::getChannels($params);
        return $response->withJson([
            'code' => 0,
            'data' => $channels
        ]);
    }
}