<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/28
 * Time: 7:12 PM
 */

namespace App\Libs;


use PHPUnit\Runner\Exception;

class MyErrorException extends Exception
{
    /**
     * 自定义error
     * @var array
     */
    private $error;

    /**
     * MyErrorException constructor.
     * @param array $error
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct($error = [], string $message = Null, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->error = $error;
    }

    /**
     * @return array
     */
    public function get()
    {
        return $this->error;
    }
}