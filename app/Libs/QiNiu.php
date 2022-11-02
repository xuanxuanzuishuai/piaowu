<?php

namespace App\Libs;

use Exception;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

class QiNiu
{
	//助教的图片信息
	const QI_NIU_DIR_ASSISTANT = "assistant";
	// declare property
	private $bucket;
	private $accessKey;
	private $secretKey;
	private $domain;
	private $folder;

	/**
	 * QiNiu constructor.
	 * Demo
	 *  $obj = new QiNiu();
	 *  $obj->uploadToken();
	 */
	public function __construct()
	{
		$dictConfig = array_column(DictConstants::getErpDictArr(DictConstants::ERP_SYSTEM_ENV['type'],
			['QINIU_DOMAIN_1', 'QINIU_FOLDER_1', 'QINIU_SECRET_KEY_1', 'QINIU_ACCESS_KEY_1', 'QINIU_BUCKET_1',])[DictConstants::ERP_SYSTEM_ENV['type']], 'value', 'code');

		$this->bucket = $dictConfig["QINIU_BUCKET_1"];
		$this->accessKey = $dictConfig['QINIU_ACCESS_KEY_1'];
		$this->secretKey = $dictConfig['QINIU_SECRET_KEY_1'];
		$this->domain = $dictConfig['QINIU_DOMAIN_1'];
		$this->folder = $dictConfig['QINIU_FOLDER_1'];
		SimpleLogger::info('[QiNiu init]', ["classCfg" => print_r($this, true)]);
	}

	/**
	 * make token and upload token
	 * @return string
	 */
	public function uploadToken(): string
	{
		$auth = new Auth($this->accessKey, $this->secretKey);
		return $auth->uploadToken($this->bucket);
	}

	/**
	 * get domain config
	 * @return mixed
	 */
	public function getDomain()
	{
		return $this->domain;
	}

	/**
	 * get folder config
	 * @return mixed
	 */
	public function getFolder()
	{
		return $this->folder;
	}

	/**
	 * format full file path
	 * @param $filePath
	 * @return string
	 */
	public function formatUrl($filePath): string
	{
		return "https://" . $this->domain . "/" . $filePath;
	}

	/**
	 * 上传图片
	 * @param string $filePath
	 * @param bool $isLocalResource 是否是本地资源 true是 false不是
	 * @param string $subDir
	 * @return string
	 * @throws Exceptions\RunTimeException
	 */
	public function upload(string $filePath, bool $isLocalResource, string $subDir): string
	{
		$url = '';
		if ($isLocalResource === false) {
			$filePath = Util::saveOssImgFile($filePath);
		}
		$extension = pathinfo($filePath, PATHINFO_EXTENSION);
		$fileName = md5(uniqid(microtime(true), true));
		$d1 = substr($fileName, 0, 2);
		$d2 = substr($fileName, 2, 2);
		$hashPath = "$d1/$d2";
		$path = "$subDir/$hashPath/$fileName";
		//上传文件远程文件路径
		$key = $this->folder . "/" . $path . '.' . $extension;
		try {
			$uploadToken = $this->uploadToken();
			$uploadMgr = new UploadManager();
			list($res, $err) = $uploadMgr->putFile($uploadToken, $key, $filePath);
			if ($err == null) {
				$url = $this->formatUrl($res['key']);
			}
		} catch (Exception $e) {
			SimpleLogger::error("QiNiu_upload_file_error", ['msg' => $e->getMessage(), 'params' => [$filePath, $subDir]]);
		} finally {
			unlink($filePath);
		}
		return $url;
	}
}