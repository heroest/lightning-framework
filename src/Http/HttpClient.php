<?php
namespace Lightning\Http;

use React\HttpClient\{
    Client as BaseClient,
    Response as BaseResponse,
    Request
};
use React\Promise\{Deferred, PromiseInterface};
use React\Socket\Connector;
use Sue\Http\{HttpException, Result};
use function Lightning\loop;
use function React\Promise\Timer\timeout;

class Client
{

    private static $instance = null;
    private static $loop;
    private static $baseClient = null;

    private function __construct()
    {
        self::$loop = loop();
        $connector = new Connector(self::$loop);
        self::$baseClient = new BaseClient(self::$loop, $connector);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get请求
     *
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return PromiseInterface
     */
    public function get(string $url, array $params = [], array $headers = [], float $timeout = 15): PromiseInterface
    {
        $parts = self::parseUrl($url);
        $url = $parts['url'];
        $params = array_merge($parts['query'], $params);
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->doRequest('GET', $url, $headers, $timeout);
    }

    /**
     * POST请求
     *
     * @param string $url
     * @param array $post_data
     * @param array $headers
     * @param float $timeout
     * @return PromiseInterface
     */
    public function post(string $url, array $post_data = [], array $headers = [], float $timeout = 15): PromiseInterface
    {
        $post_field = http_build_query($post_data);
        $headers = array_merge([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Content-Length' => strlen($post_field)
        ], $headers);

        return $this->doRequest('POST', $url, $headers, $timeout, $post_field);
    }

    /**
     * JSON格式的POST请求
     *
     * @param string $url
     * @param array $post_data
     * @param array $headers
     * @param float $timeout
     * @return PromiseInterface
     */
    public function jsonPost(string $url, array $post_data = [], array $headers = [], float $timeout = 15): PromiseInterface
    {
        $post_field = json_encode($post_data, JSON_UNESCAPED_UNICODE);
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($post_field)
        ], $headers);

        return $this->doRequest('POST', $url, $headers, $timeout, $post_field);
    }

    private function doRequest(string $method, string $url, array $headers, float $timeout, ?string $post_field = null): PromiseInterface
    {
        /** @var Request $request */
        $request = null;
        $deferred = new Deferred(function () use (&$request) {
            $request->close();
            throw new HttpException("请求超时");
        });

        $promise = $deferred->promise();
        $request = self::$baseClient->request($method, $url, $headers);

        $promise->then(function () use (&$request) {
            $request->close();
        });

        $result = new Result();
        $request->on('response', function (BaseResponse $response) use ($deferred, $result, $promise) {
            $promise->then(function () use ($response) {
                $response->close();
            });
            self::handleResponse($response, $result, $deferred);
        });

        $request->on('error', function ($error) use ($deferred) {
            $deferred->reject($error);
        });
        $request->end($post_field);
        $result->timer('request');
        return timeout($promise, $timeout, self::$loop);
    }

    private static function handleResponse(BaseResponse $response, Result $result, Deferred $deferred)
    {
        $result->timer('response');
        $result->headers = $response->getHeaders();
        $result->code = $response->getCode();

        $data = '';
        $chunk_count = 0;
        $response->on('data', function ($chunk) use (&$data, &$chunk_count) {
            $data .= $chunk;
            $chunk_count++;
        });
        $response->on('end', function () use (&$data, &$chunk_count, $result, $deferred) {
            $result->chunkCount = $chunk_count;
            $result->data = $data;
            $result->timer('end');
            $deferred->resolve($result);
        });
    }

    private static function parseUrl(string $url): array
    {
        if (false !== $index = stripos($url, '?')) {
            $base_url = substr($url, 0, $index);
            parse_str(substr($url, $index + 1), $query);
            return ['url' => $base_url, 'query' => $query];
        } else {
            return ['url' => $url, 'query' => []];
        }
    }
}