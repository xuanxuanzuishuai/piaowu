<?php
/**
 * Common Log Module.
 * HOWTO:
 * Step 1: Define the logger settings
 * LOG_FILE_PATH = '/tmp/growler-server.log'
 * LOG_BACKUP_COUNT = 10
 * # DEBUG     100
 * # INFO      200
 * # NOTICE    250
 * # WARNING   300
 * # ERROR     400
 * # CRITICAL  500
 * # ALERT     550
 * # EMERGENCY 600
 * LOG_LEVEL = 100
 *
 * Step 2: Coding
 * use App\Libs;
 * Libs\SimpleLogger::info('your message');
 *
 * @author tianye@xiaoyezi.com
 * @since 2015-12-31 16:29:16
 */

namespace App\Libs;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;

class SimpleLogger
{
    private static $logger = null;

    public static function getLogger()
    {
        if (self::$logger == null) {
            self::$logger = new Logger('App');
            /** @var RotatingFileHandler $handle */
            $handle = new RotatingFileHandler($_ENV['LOG_FILE_PATH'], $_ENV['LOG_BACKUP_COUNT'], $_ENV['LOG_LEVEL']);
            $handle->setFormatter(new JsonFormatter());
            self::$logger->pushHandler($handle);
            self::$logger->pushProcessor(self::getUidProcessor());
        }

        return self::$logger;
    }

    /**
     * 自定义log唯一id
     * 如果前端在header里添加identify，通过HTTP_IDENTIFY获取并设置为uid
     * 否则生成随机id
     * @return callable
     */
    public static function getUidProcessor()
    {
        return new class implements ProcessorInterface, ResettableInterface
        {
            private $uid;

            public function __construct($length = 32)
            {
                $identify = $_SERVER['HTTP_IDENTIFY'];
                if (!empty($identify)) {
                    $this->uid = $identify;
                }
                else {
                    $this->uid = $this->generateUid($length);
                }
            }

            public function __invoke(array $record)
            {
                $record['extra']['uid'] = $this->uid;

                return $record;
            }

            /**
             * @return string
             */
            public function getUid()
            {
                return $this->uid;
            }

            public function reset()
            {
                $this->uid = $this->generateUid(strlen($this->uid));
            }

            private function generateUid($length)
            {
                return substr(hash('md5', uniqid('', true)), 0, $length);
            }
        };
    }

    public static function debug($msg, $data)
    {
        self::getLogger()->debug($msg, $data);
    }

    public static function info($msg, $data)
    {
        self::getLogger()->info($msg, $data);
    }

    public static function notice($msg, $data)
    {
        self::getLogger()->notice($msg, $data);
    }

    public static function warning($msg, $data)
    {
        self::getLogger()->warning($msg, $data);
    }

    public static function error($msg, $data)
    {
        self::getLogger()->error($msg, $data);
    }

    public static function critical($msg, $data)
    {
        self::getLogger()->critical($msg, $data);
    }

    public static function emergency($msg, $data)
    {
        self::getLogger()->emergency($msg, $data);
    }
}