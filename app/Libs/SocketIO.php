<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/7/18
 * Time: 下午3:53
 */

namespace App\Libs;

use GuzzleHttp\Client;

class SocketIO
{
  const API_REQUEST_URL = '/lb/ws_conf/:room/:disgest';
  const API_CREATE_TOKEN = '/user/create_token';
  const API_REFRESH_TOKEN = '/user/refresh_token';
  const API_PUSH_MESSAGE = '/admin/system/push';

  const DEFAULT_ROOM_ID = 1;

  const CONTENT_TYPE_MESSAGE = 'message';
  const CONTENT_TYPE_SYSTEM = 'system';

  const COMMAND_NEW_LOGIN = "new_login";


  private $tokenUrl, $tokenKey, $tokenSecret;

  public function __construct($socketIoTokenUrl, $socketIoTokenKey, $socketIoTokenSecret)
  {
      $this->tokenUrl = $socketIoTokenUrl;
      $this->tokenKey = $socketIoTokenKey;
      $this->tokenSecret = $socketIoTokenSecret;
  }

    // 暂时不需要负载平衡
//  public static function requestUrl($roomId) {
//    $client = new Client();
//    $disgest = md5($_ENV['SOCKET_IO_APP_KEY'] . $roomId . $_ENV['SOCKET_IO_APP_SECRET']);
//
//    $api = str_replace(':room', $roomId, self::API_REQUEST_URL);
//    $api = str_replace(':disgest', $disgest, $api);
//    SimpleLogger::info(print_r($api,true));
//
//    $response = $client->request('GET', $_ENV['SOCKET_IO_SERVER_URL'] . $api);
//    $body = $response->getBody()->getContents();
//    $status = $response->getStatusCode();
//    SimpleLogger::info($body);
//    SimpleLogger::info($status);
//
//    if (200 == $status) {
//      $res = json_decode($body,true);
//      if($res['code'] !== 0) {
//        SimpleLogger::info(print_r($res, true));
//        return false;
//      }
//      return $res['data'];
//    } else {
//      SimpleLogger::info(print_r($body, true));
//      return false;
//    }
//  }

    /**
   * 创建token和刷新token调用
   * @param $api
   * @param $data
   * @return bool
   */
  private function commonAPI($api, $data) {
    $client = new Client();
    $data['headers'] = [
      'Content-Type' => 'application/json'
    ];
    SimpleLogger::info(__FILE__ . ':' . __LINE__, ["api" => $api, "data" => $data]);
    $response = $client->request('POST', $api, $data);
    $body = $response->getBody()->getContents();
    $status = $response->getStatusCode();
    SimpleLogger::info(__FILE__ . ':' . __LINE__, ["api" => $api, "body" => $body, "status" => $status]);

    if (200 == $status) {
      $res = json_decode($body,true);
      if(!empty($res['meta']) && $res['meta']['status'] !== 0) {
        SimpleLogger::info(__FILE__ . ':' . __LINE__ , [print_r($res,true)]);
        return false;
      }
      return $res['data'];
    } else {
      SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($body, true)]);
      return false;
    }
  }

  /**
   * 创建token
   * @param $userId
   * @return bool
   */
  public function createToken($userId) {
    return self::commonAPI($this->tokenUrl . self::API_CREATE_TOKEN, [
      'query' => [
        'uid' => $this->tokenKey,
        'token' => $this->tokenSecret
      ],
      'json' => [
        'uid' => $userId
      ]
    ]);
  }

  /**
   * 刷新token
   * @param $userId
   * @return bool
   */
  public function refreshToken($userId) {
    return self::commonAPI($this->tokenUrl . self::API_REFRESH_TOKEN, [
      'query' => [
        'uid' => $this->tokenKey,
        'token' => $this->tokenSecret
      ],
      'json' => [
        'uid' => $userId
      ]
    ]);
  }

    /**
     * 给用户发送消息
     * @param $messageId
     * @param $userId
     * @param $title
     * @param $content
     * @param $url
     * @param $ext
     * @return bool
     */
  public function notifyToUser($messageId, $userId, $title, $content, $url, $ext){
      $SocketUrl = $this->tokenUrl;
      $query = [
          'query' => [
              'uid' => $this->tokenKey,
              'token' => $this->tokenSecret
          ],
          'json' =>  [
              'userId' => $userId,
              'content' => [
                  'type' => self::CONTENT_TYPE_MESSAGE,
                  'messageId' => $messageId,
                  'title' => $title,
                  'content' => $content,
                  'url' => $url,
                  'ext' => $ext
              ]
          ]
      ];

      return self::commonAPI($SocketUrl . self::API_PUSH_MESSAGE, $query);
  }

    /**
     * 给所有用户发送消息
     * @param $messageId
     * @param $title
     * @param $content
     * @param $url
     * @return bool
     */
  public function notifyToAll($messageId, $title, $content, $url){
      $SocketUrl = $this->tokenUrl;
      $query = [
          'query' => [
              'uid' => $this->tokenKey,
              'token' => $this->tokenSecret
          ],
          'json' =>  [
              'roomId' => self::DEFAULT_ROOM_ID,
              'content' => [
                  'type' => self::CONTENT_TYPE_MESSAGE,
                  'message_id' => $messageId,
                  'title' => $title,
                  'content' => $content,
                  'url' => $url
              ]
          ]
      ];

      return self::commonAPI($SocketUrl . self::API_PUSH_MESSAGE, $query);

  }

    /**
     * 给用户发送控制消息
     * @param $userId
     * @param $command
     * @param $token
     * @return bool
     */
  public function notifySysInfoToUser($userId, $command, $token){
      $SocketUrl = $this->tokenUrl;
      $query = [
          'query' => [
              'uid' => $this->tokenKey,
              'token' => $this->tokenSecret
          ],
          'json' =>  [
              'userId' => $userId,
              'content' => [
                  'type' => self::CONTENT_TYPE_SYSTEM,
                  'token' => $token,
                  'command' => $command
              ]
          ]
      ];

      return self::commonAPI($SocketUrl . self::API_PUSH_MESSAGE, $query);
  }

}