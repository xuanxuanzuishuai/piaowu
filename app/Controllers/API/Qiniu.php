<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 14:19
 */

namespace App\Controllers\API;


use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Services\DictService;
use Qiniu\Auth;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Qiniu extends ControllerBase
{
    /**
     * 获取七牛上传Token
     *
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function token(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $appId = UserCenter::AUTH_APP_ID_DSS;//$params['app_id'];

        list($bucket, $accessKey, $secretKey, $domain, $qiniuFolder) = DictService::getKeyValuesByArray(Constants::DICT_TYPE_SYSTEM_ENV, [
            'QINIU_BUCKET_' . $appId,
            'QINIU_ACCESS_KEY_' . $appId,
            'QINIU_SECRET_KEY_' . $appId,
            'QINIU_DOMAIN_' . $appId,
            'QINIU_FOLDER_' . $appId
        ]);
        list($fsizeLimit, $imgSizeLimit) = DictService::getKeyValuesByArray(Constants::DICT_TYPE_SYSTEM_ENV, [
            'QINIU_FILE_SIZE_LIMIT_' . $appId,
            'QINIU_IMAGE_FILE_SIZE_LIMIT_' . $appId
        ]);
        list($callbackHost, $callbackUrl, $callbackBody) = DictService::getKeyValuesByArray(Constants::DICT_TYPE_SYSTEM_ENV, [
            'QINIU_CALLBACK_HOST_' . $appId,
            'QINIU_CALLBACK_URL_' . $appId,
            'QINIU_CALLBACK_BODY_' . $appId
        ]);

        if (empty($bucket) || empty($secretKey) || empty($accessKey) || empty($domain)) {
            return $response->withJson(Valid::addErrors([], 'app_id', 'app_id_is_invalid'), 200);
        }
        $auth = new Auth($accessKey, $secretKey);
//    SimpleLogger::info("", ['QINIU:' => $bucket , ' AK:' => $accessKey , ' SK:' =>  $secretKey]);
        $policy = [
            'scope' => $bucket . ':' . $qiniuFolder,
            'isPrefixalScope' => intval(1),
            'insertOnly' => intval(1),
            'fsizeLimit' => empty($fsizeLimit) ? 200000000 : intval($fsizeLimit)
        ];
        // 默认只能上传50M以内的图片
        if (!empty($request->getParam('notimg'))) {
            $policy['mimeLimit'] = 'image/*';
            $policy['fsizeLimit'] = empty($imgSizeLimit) ? 50000000 : intval($imgSizeLimit);
        }
        // 回调设置
        if (!empty($callbackHost) && !empty($callbackUrl) && !empty($callbackBody)) {
            $policy['callbackHost'] = $callbackHost;
            $policy['callbackUrl'] = $callbackUrl;
            //回调参数：可以指定上传时的终端信息、用户信息、页面地址等，待提交时进行比对，以便删除不用的资源或进行安全处理
//        'callbackBody' => 'key=$(key)&hash=$(etag)&user=$(x:user_id)',
            $policy['callbackBody'] = $callbackBody . '&app_id=' . $appId;
        }
        $upToken = $auth->uploadToken($bucket, null, 3600, $policy);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'uptoken' => $upToken,
                'domain' => $domain,
                'file_folder' => $qiniuFolder
            ],
            'errors' => []], StatusCode::HTTP_OK);

    }

    /**
     *  七牛上传成功回调
     * @param Request $request
     * @param Response $response
     * @param $args
     */
    public function callback(Request $request, Response $response, $args)
    {

    }

}