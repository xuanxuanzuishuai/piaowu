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
use App\Services\WechatReferralService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Models\PosterModel;

class Poster extends ControllerBase
{
    /**
     * 海报添加
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function add(Request $request, Response $response, $args)
    {
        //接收数据
        $rules = [
            [
                'key' => 'poster_url',
                'type' => 'required',
                'error_code' => 'poster_url_is_required'
            ],
            [
                'key' => 'apply_type',
                'type' => 'required',
                'error_code' => 'poster_apply_type_is_required'
            ]

        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }
        //此版本先把配置文件以配置形式写入，等版本更新在做成后台可操作配置方式
        $config = PosterModel::$settingConfig;
        $settings = [
            'qr_x' => $params['qr_x'] ? $params['qr_x'] : $config[$params['apply_type']]['qr_x'],
            'qr_y' => $params['qr_y'] ? $params['qr_y'] : $config[$params['apply_type']]['qr_y'],
            'poster_width' => $params['poster_width'] ? $params['poster_width'] : $config[$params['apply_type']]['poster_width'],
            'poster_height' => $params['poster_height'] ? $params['poster_height'] : $config[$params['apply_type']]['poster_height'],
            'qr_width' => $params['qr_width'] ? $params['qr_width'] : $config[$params['apply_type']]['qr_width'],
            'qr_height' => $params['qr_height'] ? $params['qr_height'] : $config[$params['apply_type']]['qr_height'],
        ];
        $content1 = $params['content1'] ? $params['content1'] : $config[$params['apply_type']]['content1'];
        $content2 = $params['content2'] ? $params['content2'] : $config[$params['apply_type']]['content2'];
        $time = time();
        $posterType = $params['poster_type'] ?? PosterModel::POSTER_TYPE_WECHAT_STANDARD;
        $posterStatus = $params['poster_status'] ?? PosterModel::STATUS_PUBLISH;
        //组合图片数据
        $data = [
            'url' => $params['poster_url'],
            'apply_type' => $params['apply_type'],
            'poster_type' => $posterType,
            'status' => $posterStatus,
            'content1' => $content1,
            'content2' => $content2,
            'settings' => json_encode($settings),
            'create_time' => $time,
            'creator_id' => self::getEmployeeId(),
        ];
        //如果是标准海报：生效海报有且只能有一个
        $unPublishPosterList = [];
        if ($posterType == PosterModel::POSTER_TYPE_WECHAT_STANDARD && $posterStatus == PosterModel::STATUS_PUBLISH) {
            $updateData = [
                'status' => PosterModel::STATUS_NOT_PUBLISH,
                'update_time' => $time,
                'updator_id' => self::getEmployeeId(),
            ];
            WechatReferralService::updatePosterWhere(["apply_type" => $params['apply_type'], "poster_type" => $posterType], $updateData);
            //获取当前已失效的海报列表
            list($count, $unPublishPosterList) = WechatReferralService::getPosterList(["apply_type" => $params['apply_type'], "poster_type" => $posterType]);
        }
        $id = WechatReferralService::addPoster($data);
        //增加数据
        if (empty($id)) {
            return $response->withJson(Valid::addErrors([], 'poster', 'poster_add_fail'));
        }
        //删除以失效海报底图合成的二维码海报
        if ($unPublishPosterList) {
            WechatReferralService::delPosterQrFile($posterType, $params['apply_type'], $unPublishPosterList);
        }
        //返回数据
        return $response->withJson([
            'code' => 0,
            'data' => ['id' => $id]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 海报修改
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function modify(Request $request, Response $response, $args)
    {
        //接收数据
        $rules = [
            [
                'key' => 'poster_url',
                'type' => 'required',
                'error_code' => 'poster_url_is_required'
            ],
            [
                'key' => 'apply_type',
                'type' => 'required',
                'error_code' => 'poster_apply_type_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }
        $posterType = $params['poster_type'] ?? PosterModel::POSTER_TYPE_WECHAT_STANDARD;
        $posterStatus = $params['poster_status'] ?? PosterModel::STATUS_PUBLISH;
        $time = time();
        $unPublishPosterList = [];
        //如果是标准海报：生效海报有且只能有一个
        if ($posterType == PosterModel::POSTER_TYPE_WECHAT_STANDARD && $posterStatus == PosterModel::STATUS_PUBLISH) {
            $updateData = [
                'status' => PosterModel::STATUS_NOT_PUBLISH,
                'update_time' => $time,
                'updator_id' => self::getEmployeeId(),
            ];
            WechatReferralService::updatePosterWhere(["apply_type" => $params['apply_type'], "poster_type" => $posterType], $updateData);
            //获取当前已失效的海报列表
            $detail = WechatReferralService::getPosterDetail(['id' => $params['id']]);
            if (($detail['url'] != $params['poster_url']) || ($detail['apply_type'] != $params['apply_type'])) {
                list($count, $unPublishPosterList) = WechatReferralService::getPosterList(["apply_type" => $params['apply_type'], "poster_type" => $posterType]);
                //把本次修改替换掉的海报底图的二维码海报删除
                array_unshift($unPublishPosterList,$detail);
            }
        }
        //组合数据
        $data = [
            'url' => $params['poster_url'],
            'apply_type' => $params['apply_type'],
            'poster_type' => $posterType,
            'status' => $posterStatus,
            'update_time' => $time,
            'updator_id' => self::getEmployeeId(),
        ];
        $affectRows = WechatReferralService::updatePoster($params['id'], $data);
        //返回数据
        if (empty($affectRows)) {
            return $response->withJson(Valid::addErrors([], 'poster', 'poster_update_fail'));
        }
        //删除以失效海报底图合成的二维码海报
        if ($unPublishPosterList) {
            WechatReferralService::delPosterQrFile($posterType, $params['apply_type'], $unPublishPosterList);
        }
        return $response->withJson([
            'code' => 0,
            'data' => ['id' => $params['id']]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 海报详情
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'poster_id',
                'type' => 'required',
                'error_code' => 'poster_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = WechatReferralService::getPosterDetail(['id' => $params['poster_id']], ['id', 'url', 'apply_type']);
        return $response->withJson([
            'code' => 0,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 海报列表
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        //接收数据
        $rules = [
            [
                'key' => 'apply_type',
                'type' => 'required',
                'error_code' => 'poster_apply_type_is_required'
            ]

        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }
        //默认查询标准海报
        $params['poster_type'] = $params['poster_type'] ?? PosterModel::POSTER_TYPE_WECHAT_STANDARD;
        $params['poster_status'] = $params['poster_status'] ?? PosterModel::STATUS_PUBLISH;
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        list($count, $list) = WechatReferralService::getPosterList($params, $params['page'], $params['count']);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'count' => $count,
                'list' => $list
            ]
        ], StatusCode::HTTP_OK);
    }
}