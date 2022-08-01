<?php
/**
 * 微信回调
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Services\MorningReferral\MorningWeChatHandlerService;
use Slim\Http\Request;
use Slim\Http\Response;


class WxCallback extends ControllerBase
{
    public static function morningCallback(Request $request, Response $response)
    {
        // TODO qingfeng.lian 检查生成的sign
        $data = file_get_contents('php://input');
        SimpleLogger::info("data valid", ["data" => $data]);
        $xml = simplexml_load_string($data, "SimpleXMLElement", LIBXML_NOCDATA);
        $msgType = (string)$xml->MsgType;
        $result = '';
        // 消息推送事件
        if ($msgType == 'event') {
            $event = (string)$xml->Event;
            SimpleLogger::info('student weixin event: ' . $event, []);
            switch ($event) {
                // 暂时只处理关注
                case 'subscribe':
                    // 关注公众号
                    $result = MorningWeChatHandlerService::subscribe($xml);
                    break;
                case 'CLICK':
                    // 点击自定义菜单事件
                    $result = MorningWeChatHandlerService::menuClickEventHandler($xml);
                    break;
                case 'unsubscribe':
                    //取消关注公众号
                    break;
                default:
                    break;
            }
        } else { //text, image, voice, location ... 等客服消息
            // MorningWeChatHandlerService::autoReply($xml);
        }
        SimpleLogger::info("weixin callback event result.", [$result]);
        $response->getBody()->write('');
        return $response;
    }
}
