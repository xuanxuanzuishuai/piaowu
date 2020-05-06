<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/14
 * Time: 16:35
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use Slim\Http\Request;
use Slim\Http\Response;

class Callback extends ControllerBase
{

    /**
     * 消息接收
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function weChatCallback(Request $request, Response $response)
    {
        $token = $_ENV['STUDENT_WEIXIN_TOKEN'];
        $params = $request->getParams();
        $signature = $params["signature"];
        $t = $params["timestamp"];
        $nonce = $params["nonce"];
        $echostr = $params["echostr"];

        $list = [$token, $t, $nonce];
        sort($list, SORT_STRING);
        $s = implode( $list );
        $str = sha1( $s );
        $result = "";
        if( $str == $signature ){
            if (!empty($echostr)){
                $result = $echostr;
            } else{
                $data = file_get_contents('php://input');
                SimpleLogger::info("data valid", ["data" => $data]);
                self::_weChatMsgController($data);
            }
        }

        $response->getBody()->write($result);
        return $response;
    }

    public function _weChatMsgController($data){
        $xml = simplexml_load_string($data);
        $msgType = (string)$xml->MsgType;

        $result = false;
        // 消息推送事件
        if ($msgType == 'event') {
            $event = (string)$xml->Event;
            SimpleLogger::info('student weixin event: ' . $event, []);
            switch ($event) {
                // 暂时只处理关注
                case 'subscribe':
                    // 关注公众号
                    WeChatMsgHandler::subscribe($xml);
                    break;
                case 'CLICK':
                    // 点击自定义菜单事件
                    WeChatMsgHandler::menuClickEventHandler($xml);
                    break;
                default:
                    break;
            }
        } else { //text, image, voice, location ... 等客服消息
            WeChatMsgHandler::autoReply($xml);
        }
        return $result;
    }

}
