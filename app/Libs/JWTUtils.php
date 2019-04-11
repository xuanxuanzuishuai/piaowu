<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: Hemu
 * Date: 2018/6/26
 * Time: 下午1:23
 */

namespace App\Libs;

use Lcobucci\JWT\Parser;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Builder;

class JWTUtils
{
    private $issuer, $audience, $expire, $signerKey;
    public function __construct($issuer, $audience, $expire, $signerKey)
    {
        $this->issuer = $issuer;
        $this->audience = $audience;
        $this->expire = $expire;
        $this->signerKey = $signerKey;
    }

    /**
     * @param $type 0: user 1:teacher 2: admin 3: partner 4: school
     * @param $userId
     * @param $name
     * @return string
     */
    public function getToken($type, $userId, $name)
    {

        $signer = new Sha256();
        $token = (new Builder())
            ->setIssuer($this->issuer)
            ->setAudience($this->audience)
            ->setId($userId, true)
            ->setIssuedAt(time())
            ->setExpiration(time() + intval($this->expire))
            ->set('type', $type)
            ->set('uid', $userId)
            ->set('name', $name)
            ->sign($signer, $this->signerKey)
            ->getToken();
        return (string)$token;

    }

    /**
     * @param $token
     * @return array
     */
    public function verifyToken($token)
    {
        try {
            $signer = new Sha256();
            $token = (new Parser())->parse((string)$token);

            if (!$token->verify($signer, $this->signerKey)) {
                return Valid::addErrors([], 'token', 'invalid_signer');
            }

            $validData = new ValidationData();
            $validData->setIssuer($this->issuer);
            $validData->setAudience($this->audience);
            if (!$token->validate($validData)) {
                return Valid::addErrors([], 'token', 'invalid_token');
            }

            // 检查过期时间
            if ($token->isExpired()) {
                return Valid::addErrors([], 'expired', 'token_expired');
            }

            $type = $token->getClaim('type');
            $userId = $token->getClaim('uid');

            return [
                'code' => 0,
                'data' => [
                    'type' => $type,
                    'user_id' => $userId
                ]
            ];
        } catch (\Exception $e) {

            SimpleLogger::error(__FILE__ . ":" . __LINE__,["code"=>$e->getCode() ,"errs"=> $e->getMessage()]);

            return Valid::addErrors([], 'token', 'system_error');
        }
    }
}