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
use Monolog\Processor\UidProcessor;

class StudentTimeLineLogger
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
            self::$logger->pushProcessor(new UidProcessor());
        }

        return self::$logger;
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