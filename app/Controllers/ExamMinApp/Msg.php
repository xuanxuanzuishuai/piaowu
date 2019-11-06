<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/4
 * Time: 下午12:16
 */

namespace App\Controllers\ExamMinApp;

use App\Libs\SimpleLogger;
use App\Libs\WeChat\MsgErrorCode;
use App\Libs\WeChat\SHA1;
use App\Libs\WeChat\WXBizMsgCrypt;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Controllers\ControllerBase;
use App\Libs\WeChat\Helper;

// 音基小程序客服消息接口
class Msg extends ControllerBase
{
    public function notify(Request $request, Response $response)
    {
        $params = $request->getParams();
        $wx = new WXBizMsgCrypt($_ENV['EXAM_TOKEN'], $_ENV['EXAM_ENCODING_AES_KEY'], $_ENV['EXAM_MINAPP_ID']);
        //验证消息的确来自微信服务器
        if(!empty($params['echostr'])) {
            $sha1 = SHA1::getSHA1([$params['timestamp'], $params['nonce'], $_ENV['EXAM_TOKEN']]);
            if($sha1 == $params['signature']) {
                return $response->getBody()->write($params['echostr']);
            } else {
                return $response->getBody()->write("error");
            }
        }

        $postData = file_get_contents('php://input');
        $code = $wx->decryptMsg($params['msg_signature'], $params['timestamp'], $params['nonce'], $postData, $msg);
        $ele = null;

        if($code == MsgErrorCode::$OK) {
            SimpleLogger::info('exam server msg', ['msg' => $msg]);

            $ele = simplexml_load_string($msg);

            switch (trim((string)$ele->Content)) {
                case '1':
                    // 原网址 https://a.app.qq.com/o/simple.jsp?pkgname=com.theone.aipeilian
                    // 百度短网址服务 https://dwz.cn/
                    Helper::sendMsg([
                        'touser'  => (string)$ele->FromUserName,
                        'msgtype' => 'text',
                        'text'    => ['content' => '海量音基题库随心做，助力音基考级通关！https://dwz.cn/mY4uAjAG'],
                    ]);
                    return $response->getBody()->write("success");
            }
            //转到客服消息
            if(in_array((string)$ele->MsgType, ['text', 'image', 'link', 'miniprogrampage'])) {
                $xmlString = $wx->transfer2Server((string)$ele->FromUserName, (string)$ele->ToUserName, (string)$ele->CreateTime);
                SimpleLogger::info("transfer to server:", ['content' => $xmlString]);
                return $response->getBody()->write($xmlString);
            }
            //剩下的信息都回success
            return $response->getBody()->write("success");
        } else {
            $params['code'] = $code;
            SimpleLogger::error('decrypt msg error', $params);
            return $response->getBody()->write("success");
        }
    }
}