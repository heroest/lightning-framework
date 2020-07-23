<?php

namespace Lightning\Http;

use Lightning\Http\HttpException;

class Result
{
     /**
     * Http状态码
     *
     * @var string
     */

    public $code = '';

    /**
     * Http头部
     *
     * @var array
     */
    public $headers = [];

    /**
     * 请求返回消息体
     *
     * @var string
     */
    public $data = '';

    /**
     * 请求开始时间
     *
     * @var integer
     */
    public $timeRequest = 0;

    /**
     * 请求响应时间
     *
     * @var integer
     */
    public $timeResponse = 0;

    /**
     * 请求完成时间
     *
     * @var integer
     */
    public $timeEnd = 0;

    /**
     * 数据切片数量
     *
     * @var integer
     */
    public $chunkCount = 0;

    public function timer(string $action)
    {
        $key = 'time' . ucfirst($action);
        if (isset($this->$key)) {
            $this->$key = microtime(true);
        }
    }

    public function __set($name, $value)
    {
        throw new HttpException("Setting unknown property:{$name}");
    }
}