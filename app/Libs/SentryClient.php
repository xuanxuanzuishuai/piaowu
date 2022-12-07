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
     * 上报日常
     * @param $exception
     * @param array $data
     * @return bool
     */
    public static function captureException($exception, $data = [])
    {
        return true;
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