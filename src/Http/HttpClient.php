<?php

namespace Lightning\Http;

use Lightning\Exceptions\HttpException;
use Lightning\Http\Payload;
use Lightning\Http\RequestResult;
use React\HttpClient\{Client AS ReactHttpClient, Response, Request};
use React\Promise\{Deferred, PromiseInterface};
use React\Socket\Connector;
use function Lightning\{container, loop};

class HttpClient
{
    public function __construct()
    {
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
    public function get(string $url, array $params = [], array $headers = [], array $options = []): PromiseInterface
    {
        $parts = self::parseUrl($url);
        $url = $parts['url'];
        $params = array_merge($parts['query'], $params);
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $payload = new Payload();
        $payload->url = $url;
        $payload->method = 'GET';
        $payload->setOptions($options);

        return self::doRequest($payload);
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
    public function post(string $url, array $post_data = [], array $headers = [], $options = []): PromiseInterface
    {
        $post_field = http_build_query($post_data);

        $payload = new Payload();
        $payload->url = $url;
        $payload->method = 'POST';
        $payload->postField = $post_field;
        $payload->headers = array_merge([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Content-Length' => strlen($post_field)
        ], $headers);
        $payload->setOptions($options);

        return self::doRequest($payload);
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
    public function jsonPost(string $url, array $post_data = [], array $headers = [], $options = []): PromiseInterface
    {
        $post_field = json_encode($post_data);

        $payload = new Payload();
        $payload->url = $url;
        $payload->method = 'POST';
        $payload->postField = $post_field;
        $payload->headers = array_merge([
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($post_field)
        ], $headers);
        $payload->setOptions($options);

        return self::doRequest($payload);
    }

    private static function createClient($timeout): ReactHttpClient
    {
        $loop = loop();
        $connector = new Connector($loop, ['timeout' => $timeout]);
        return new ReactHttpClient($loop, $connector);
    }

    private static function doRequest(Payload $payload): PromiseInterface
    {
        $result = new RequestResult();
        $deferred = new Deferred();

        $options = $payload->getOptions();
        $method = $payload->method;
        $url = $payload->url;
        $headers = $payload->headers;
        $result->url = $url;
        $request = self::createClient($options['connection_timeout'])->request($method, $url, $headers);
        $deferred = self::setTimeout($deferred, $options['timeout']);
        $promise = $deferred->promise();

        //stop request after timeout
        $promise->then(function () use ($request) {
            $request->close();
        });

        $request->on('response', function (Response $response) use ($request, $deferred, $result, $payload) {
            $deferred->promise()->then(function () use ($response) {
                $response->close();
            });

            $options = $payload->getOptions();
            if (!empty($options['follow_redirects'])) {
                $headers = array_change_key_case($response->getHeaders(), CASE_LOWER);
                if (!empty($headers['location'])) {
                    $response->close();
                    $request->close();
                    $payload->url = $headers['location'];
                    $deferred->resolve(self::doRequest($payload));
                    return;
                }
            }
            self::handleResponse($request, $response, $result, $deferred);
        });

        $request->on('error', function ($error) use ($deferred, $result) {
            $result->error = $error;
            $deferred->resolve($result);
        });

        $request->end($payload->postField);
        $result->time_request = microtime(true);

        return $promise;
    }

    private static function handleResponse(Request $request, Response $response, RequestResult $result, Deferred $deferred): void
    {
        $result->time_response = microtime(true);
        $result->headers = $response->getHeaders();
        $result->code = $response->getCode();

        $data = '';
        $chunk_count = 0;
        $response->on('data', function ($chunk) use (&$data, &$chunk_count) {
            $data .= $chunk;
            $chunk_count++;
        });
        $response->on('end', function () use (&$data, &$chunk_count, $request, $response, $result, $deferred) {
            $request->close();
            $response->close();
            $result->chunk_count = $chunk_count;
            $result->data = $data;
            $result->time_end = microtime(true);
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

    private static function setTimeout(Deferred $deferred, float $timeout): Deferred
    {
        $promise = $deferred->promise();
        $timer = loop()->addTimer($timeout, function () use ($deferred) {
            $result = new RequestResult();
            $result->error = new HttpException("请求时间超时");
            $result->time_end = microtime(true);
            $deferred->resolve($result);
        });
        $promise->then(function () use ($timer) {
            loop()->cancelTimer($timer);
        });
        return $deferred;
    }
}
