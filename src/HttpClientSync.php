<?php
/**
 * Class HttpClientSync
 *
 * Author:  Kernel Huang
 * Date:    10/10/18
 * Time:    4:50 PM
 */

namespace Client;

use Common\JsonFormat;
use Exceptions\NotFoundException;
use Message\Message;

/**
 * Http客户端请求
 *
 * Class HttpClientSync
 * @package Client
 */
class HttpClientSync
{
	private $url        = '';       // 请求地址
	private $data       = '';       // 提交数据
	private $method     = '';       // Post/Get方法
	private $timeout;               // 默认超时值30秒

	public $charset     = 'utf-8';  // 使用utf8字符编码
	public $type        = 'json';   // 默认用JSON数据类型
	public $header      = [];       // 头部设置
	public $dataType    = [
		'data'  => 'multipart/form-data',
		'url'   => 'application/X-www-form-urlencoded',
		'json'  => 'application/json',
	];

	/**
	 * 初始化参数
	 *
	 * @param $url
	 * @param $data
	 * @param $method
	 * @return mixed|string
	 * @throws NotFoundException
	 */
	private function init($url, $data, $method) {
		$this->url    = $url;
		$this->data   = $data;
		$this->method = $method;
		return $this->call();
	}

	/**
	 * 使用Post方法请求
	 *
	 * @param string $url
	 * @param string $data
	 * @param int $second
	 * @return mixed|string
	 * @throws NotFoundException
	 * @throws \Exceptions\UnFormattedException
	 */
	public function post($url = '', $data = '', $second = 30 ) {
		$this->timeout = $second;

		if ($this->type == 'json') {
			$toJson = JsonFormat::boot($data);
			return $this->init($url, $toJson, CURLOPT_POST);
		}

		return $this->init($url, $data, CURLOPT_POST);
	}

	/**
	 * 使用Get方法请求
	 *
	 * @param string $url
	 * @param int $second
	 * @return mixed|string
	 * @throws NotFoundException
	 */
	public function get($url = '', $second = 30) {
		$this->timeout = $second;
		return $this->init($url, '', CURLOPT_HTTPGET);
	}

	/**
	 * 组合头部
	 *
	 * @param string $auth
	 * @return array
	 */
	public function header($auth  = '') {
		$this->header = array(
			'Content-Type: ' . $this->dataType[$this->type] . '; charset=' . $this->charset,
			'Authorization: ' . $auth
		);
		return $this->header;
	}

	/**
	 * 调用cUrl库发起Post/Get请求
	 *
	 * @return mixed|string
	 * @throws NotFoundException
	 */
	private function call() {
		if (!extension_loaded('curl')) {
			throw new NotFoundException('cURL extension');
		}

		if (!function_exists('curl_exec')) {
			throw new NotFoundException('curl_exec function');
		}

		// 初始化cUrl
		$channel = \curl_init();

		// 设置超时值
		\curl_setopt($channel, CURLOPT_TIMEOUT, $this->timeout);

		// 设置Post/Get请求方法
		\curl_setopt($channel, $this->method, 1);

		// 设置Post请求方法
		if ($this->method == CURLOPT_POST) {
			// 设置POST方法提交数据
			\curl_setopt($channel, CURLOPT_POSTFIELDS, $this->data);
		}

		// 设置请求的Url
		\curl_setopt($channel, CURLOPT_URL, $this->url);
		// 设置请求头部
		\curl_setopt($channel, CURLOPT_HTTPHEADER, !empty($this->header) ?? $this->header());
		// 设置返回数据, 而不直接显示
		\curl_setopt($channel, CURLOPT_RETURNTRANSFER, 1);

		// 执行cUrl
		try {
			$content = \curl_exec($channel);
			\curl_close($channel);
			return $content;

		} catch (\Exception $e) {
			// 获取cUrl错误码
			$errno = \curl_errno($channel);
			\curl_close($channel);
			$errorMsg = $this->method . Message::NG . 'error code: ' . $errno . $e->getMessage();
			echo $errorMsg;
		}

		return false;
	}

	/**
	 * 获取请求的原始数据流
	 *
	 * @return bool|false|string
	 */
	public function getRaw() {
		try {
			return file_get_contents('php://input');

		} catch (\Exception $e) {

			$errorMsg = $e->getMessage();
			echo $errorMsg;
		}

		return false;
	}

	/**
	 * 析构函数
	 */
	public function __destruct() {
		unset($this->url);
		unset($this->data);
		unset($this->method);
		unset($this->dataType);
		unset($this->charset);
	}
}
