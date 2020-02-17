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
     * Sentry 上报
     * @param $message
     * @param $data
     * @param array $trace
     */
    public static function captureError($message, $data, $trace = [])
    {
        $extra = [
            'extra' => [
                'stack_trace' => $trace
            ]
        ];
        $sentryClient = new \Raven_Client($_ENV['SENTRY_NOTIFY_URL']);
        $sentryClient->captureMessage($message, $data, $extra);
    }

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
        $sentryClient = new \Raven_Client($_ENV['SENTRY_NOTIFY_URL']);
        $sentryClient->captureMessage($exception->getMessage(), $data, $extra);
    }
}