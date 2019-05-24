<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/5/10
 * Time: 14:35
 */

namespace App\Controllers\StudentOrgWX;

use App\Controllers\ControllerBaseForOrg;
use App\Libs\SimpleLogger;
use Slim\Http\Request;
use Slim\Http\Response;


class Callback extends ControllerBaseForOrg
{

    /**
     * 消息接收
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function weChatCallback(Request $request, Response $response)
    {
        $token = $_ENV['STUDENT_WEIXIN_ORG_TOKEN'];

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
                // todo 这是明文的，以后要加上加密
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
            SimpleLogger::info('weixin event: ' . $event, []);
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
        }
        return $result;
    }

}
