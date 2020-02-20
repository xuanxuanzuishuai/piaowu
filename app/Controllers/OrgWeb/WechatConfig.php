<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/11
 * Time: 5:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\UserCenter;
use App\Models\WeChatConfigModel;
use App\Services\WeChatConfigService;
use App\Services\WeChatService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Models\StudentModelForApp;
use App\Models\UserWeixinModel;

/**
 * 微信配置控制器
 * Class WechatConfig
 * @package App\Controllers\OrgWeb
 */
class WechatConfig extends ControllerBase
{
    /**
     * 配置公众号事件推送内容
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function setWechatOfficeConfig(Request $request, Response $response, $args)
    {
        //接收数据
        $rules = [
            [
                'key' => 'content',
                'type' => 'required',
                'error_code' => 'content_is_required'
            ],
            [
                'key' => 'msg_type',
                'type' => 'required',
                'error_code' => 'msg_type_is_required'
            ],
            [
                'key' => 'content_type',
                'type' => 'required',
                'error_code' => 'content_type_is_required'
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required'
            ],
            [
                'key' => 'wechat_type',
                'type' => 'required',
                'error_code' => 'wechat_type_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }
        //组合数据
        $time = time();
        $data = [
            'content' => Util::textEncode($params['content']),
            'msg_type' => $params['msg_type'],
            'content_type' => $params['content_type'],
            'event_type' => $params['event_type'],
            'type' => $params['wechat_type'],
        ];
        if ($params['id']) {
            $id = $params['id'];
            $data['update_time'] = $time;
            $data['update_uid'] = self::getEmployeeId();
            $affectRows = WeChatConfigService::updateWechatConfig($params['id'], $data);
        } else {
            $data['create_time'] = $time;
            $data['create_uid'] = self::getEmployeeId();
            $id = $affectRows = WeChatConfigService::addWechatConfig($data);
        }
        //返回数据
        if (empty($affectRows)) {
            return $response->withJson(Valid::addErrors([], 'wechat_config', 'wechat_config_set_fail'));
        }
        return $response->withJson([
            'code' => 0,
            'data' => ['id' => $id]
        ], StatusCode::HTTP_OK);
    }


    /**
     * 推送内容详情
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'wechat_config_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = WeChatConfigService::getWechatConfigDetail(['id' => $params['id']]);
        return $response->withJson([
            'code' => 0,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 数据列表
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);

        list($count, $list) = WeChatConfigService::getWechatConfigList($params, $params['page'], $params['count']);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'count' => $count,
                'list' => $list
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 发送微信自定义消息
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendSelfMessage(Request $request, Response $response, $args)
    {
        //接收参数
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'content',
                'type' => 'required',
                'error_code' => 'content_is_required'
            ],
            [
                'key' => 'wechat_type',
                'type' => 'required',
                'error_code' => 'wechat_type_is_required'
            ],
            [
                'key' => 'content_type',
                'type' => 'required',
                'error_code' => 'content_type_is_required'
            ]
        ];
        //验证参数
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //发送微信推送消息
        $res = WeChatService::notifyUserCustomizeMessage($params['mobile'], 0, [], ["type" => $params['wechat_type'], 'content' => $params['content'], 'content_type' => $params['content_type']]);
        //返回结果
        if (empty($res)) {
            return $response->withJson(Valid::addErrors([], 'message', 'wx_send_fail'));
        }
        return $response->withJson([
            'code' => 0,
            'data' => [],
        ], 200);
    }
}