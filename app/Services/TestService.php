<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/28
 * Time: 7:07 PM
 */

namespace App\Services;


use App\Libs\MyErrorException;
use App\Libs\Valid;
use PHPUnit\Runner\Exception;

class TestService
{
    public  function check() {

        throw new Exception("lskdjfldkjfdl");
        throw new MyErrorException(Valid::addErrors([],Valid::CODE_EXCEPTION,'sys_unknown_errors',[]));
    }
}