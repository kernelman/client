<?php
/**
 * Class HttpClientSync
 *
 * Author:  Kernel Huang
 * Date:    10/10/18
 * Time:    4:50 PM
 */

namespace Client;

use Exceptions\NotFoundException;
use Logs\Services\Logs;
use Message\Message;

/**
 * Http客户端请求
 *
 * Class HttpClientSync
 * @package Client
 */
class HttpClientSync
{
    private $url     = '';  // 请求地址
    private $data    = '';  // 提交数据
    private $method  = '';  // Post/Get方法
    private $timeout;       // 默认超时值30秒

    public $charset  = 'utf-8';
    public $dataType = 'X-www-form-urlencoded'; // 默认用Form数据类型

    /**
     * @param $url
     * @param $data
     * @param $method
     * @return mixed|string
     * @throws NotFoundException
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnFormattedException
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
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnFormattedException
     */
    public function post($url = '', $data ='', $second = 30 ) {
        $this->timeout = $second;
        return $this->init($url, $data, 'CURLOPT_POST');
    }

    /**
     * 使用Get方法请求
     *
     * @param string $url
     * @param string $data
     * @param int $second
     * @return mixed|string
     * @throws NotFoundException
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnFormattedException
     */
    public function get($url = '', $data ='', $second = 30) {
        $this->timeout = $second;
        return $this->init($url, $data, 'CURLOPT_HTTPGET');
    }

    /**
     * 组合头部
     *
     * @return array
     */
    private function header() {

        return array(
            'Content-Type: application/' . $this->dataType . '; charset=' . $this->charset,
            'Content-Length: ' . strlen($this->data)
        );
    }

    /**
     * 调用cUrl库发起Post/Get请求
     *
     * @return mixed|string
     * @throws NotFoundException
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnFormattedException
     */
    private function call() {
        if (!extension_loaded('curl')) {
            throw new NotFoundException('cUrl扩展库.');
        }

        // 初始化cUrl
        $content = '';
        $channel = \curl_init();

        // 设置超时值
        \curl_setopt($channel, CURLOPT_TIMEOUT, $this->timeout);
        // 设置Post/Get请求方法
        \curl_setopt($channel, $this->method, 1);
        // 设置请求的Url
        \curl_setopt($channel, CURLOPT_URL, $this->url);
        // 设置提交数据
        \curl_setopt($channel, CURLOPT_POSTFIELDS, $this->data);
        // 设置请求头部
        \curl_setopt($channel, CURLOPT_HTTPHEADER, $this->header());

        // 执行cUrl
        try {
            $content = \curl_exec($channel);
            \curl_close($channel);

        } catch (\Exception $e) {
            // 获取cUrl错误码
            $errno = \curl_errno($channel);
            \curl_close($channel);
            $errorMsg = $this->method . Message::NG . 'error code: ' . $errno . $e->getMessage();
            // 写错误日志
            $this->log($errorMsg);
        }

        return $content;
    }

    /**
     * 获取请求的原始数据流
     *
     * @return bool|string
     * @throws NotFoundException
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnFormattedException
     */
    public function getRaw() {
        $rawData = '';

        try {
            $rawData = file_get_contents('php://input');

        } catch (\Exception $e) {

            $errorMsg = $e->getMessage();
            $this->log($errorMsg);
        }

        return $rawData;
    }

    /**
     * 写日志
     *
     * @param $msg
     * @param null $info
     * @throws NotFoundException
     * @throws \Exceptions\InvalidArgumentException
     * @throws \Exceptions\UnFormattedException
     */
    private function log($msg, $info = null) {
        $log            = new \stdClass();
        $log->schema    = 'Client';
        $log->module    = 'http';
        $log->message   = $msg;
        $log->content   = $info;

        Logs::info($log);
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
