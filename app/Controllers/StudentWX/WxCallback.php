<?php
/**
 * 微信回调
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Services\Morning\MorningWeChatHandlerService;
use Exception;
use Slim\Http\Request;
use Slim\Http\Response;


class WxCallback extends ControllerBase
{
    public static function morningCallback(Request $request, Response $response)
    {
        $token = $_ENV['MORNING_WX_TOKEN'];
        $params = $request->getParams();
        $signature = $params["signature"];
        $t = $params["timestamp"];
        $nonce = $params["nonce"];
        $echostr = $params["echostr"] ?? '';

        $list = [$token, $t, $nonce];
        sort($list, SORT_STRING);
        $s = implode($list);
        $str = sha1($s);
        $result = '';
        if ($str == $signature) {
            if (!empty($echostr)) {
                $result = $echostr;
            } else {
                $data = self::encodingData(file_get_contents('php://input'));
                SimpleLogger::info("data valid", ["data" => $data]);
                self::_weChatMsgController($data);
            }
        }
        $response->getBody()->write($result);
        return $response;
    }

    /**
     * 处理事件的具体逻辑
     * @param $data
     * @return void
     */
    public static function _weChatMsgController($data)
    {
        try {
            $msgType = (string)$data['MsgType'];
            // 消息推送事件
            if ($msgType == 'event') {
                $event = (string)$data['Event'];
                SimpleLogger::info('student weixin event: ' . $event, []);
                switch ($event) {
                    // 暂时只处理关注
                    case 'subscribe':
                        // 关注公众号
                        $actResult = MorningWeChatHandlerService::subscribe($data);
                        break;
                    case 'CLICK':
                        // 点击自定义菜单事件
                        $actResult = MorningWeChatHandlerService::menuClickEventHandler($data);
                        break;
                    case 'unsubscribe':
                        //取消关注公众号
                        $actResult = MorningWeChatHandlerService::unsubscribe($data);
                        break;
                    default:
                        break;
                }
            } elseif ($msgType == "text") {
				//文本消息
				$actResult = MorningWeChatHandlerService::text($data);
			}
        } catch (Exception $e) {
            SimpleLogger::error("weixin callback event controller fail.", [$actResult ?? [], $e->getMessage()]);
        }
        SimpleLogger::info("weixin callback event result.", [$actResult ?? []]);
    }

    public static function encodingData($dataStr)
    {
        SimpleLogger::info("data valid", ["data" => $dataStr]);
        $xml = simplexml_load_string($dataStr, "SimpleXMLElement", LIBXML_NOCDATA);
        return json_decode(json_encode($xml), true);
    }
}
