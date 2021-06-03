<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/17
 * Time: 5:48 PM
 */

namespace App\Libs;


class SentryClient
{
    /**
     * Sentry 上报异常
     * @param \Exception $exception
     * @param $data
     */
    public static function captureException($exception, $data = [])
    {
        $extra = [
            'extra' => [
                'stack_trace' => $exception->getTrace()
            ]
        ];
        $otherInfo = '';
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $str = PHP_EOL . "{$k}: " . $v;
                $otherInfo .= $str;
            }
        }
        $sentryClient = new \Raven_Client($_ENV['SENTRY_NOTIFY_URL']);
        $sentryClient->captureMessage('log_uid: ' . SimpleLogger::getWriteUid() . PHP_EOL . 'exception info: ' . $exception->getMessage() . $otherInfo, [], $extra);
    }
}