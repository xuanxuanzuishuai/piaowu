<?php


namespace App\Controllers\WeChatCS;


use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\Valid;
use App\Services\WeChatCSService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 微信客服
 * Class WeChatCS
 * @package App\Controllers\API
 */
class WeChatCS extends ControllerBase
{
    /**
     * 获取当前有效的微信客服数据
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function getWeChatCS(Request $request, Response $response, $args)
    {
        $result = WeChatCSService::getWeChatCS();
        $result['qr_url'] = AliOSS::signUrls($result['qr_url']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function addWeChatCS(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'cs_id_is_required'
            ],
            [
                'key'        => 'url',
                'type'       => 'required',
                'error_code' => 'cs_url_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $result = WeChatCSService::addWeChatCS($params['name'], $params['url']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function setWeChatCS(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'cs_id',
                'type'       => 'required',
                'error_code' => 'cs_id_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $result = WeChatCSService::setWeChatCS($request->getParam('cs_id'));
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function getWeChatCSList(Request $request, Response $response, $args)
    {
        $result = WeChatCSService::getWeChatCSList();
        foreach($result as $key => $value) {
            $result[$key]['qr_url'] = AliOSS::signUrls($value['qr_url']);
            if($value['status'] == 1) {
                $result[$key]['status'] = '已发布';
            }
            else {
                $result[$key]['status'] = '未发布';
            }
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ]);
    }
}