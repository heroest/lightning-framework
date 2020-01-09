<?php
namespace Lightning\Http;

use Lightning\Exceptions\HttpException;
use Lightning\Http\RequestResult;
use React\HttpClient\{Client, Response, Request};
use React\EventLoop\LoopInterface;
use React\Promise\{Deferred, PromiseInterface};
use React\Socket\Connector;
use function Lightning\container;

class HttpClient
{
    private $client;

    public function __construct()
    {
        $config = container()->get('config');
        $timeout = $config->get('http_client.timeout', 30);
        $loop = container()->get('loop');
        $connector = new Connector($loop, ['timeout' => $timeout]);
        $this->client = new Client($loop, $connector);
    }

    public function get(string $url, array $params = [], array $headers = []): PromiseInterface
    {
        $parts = self::parseUrl($url);
        $url = $parts['url'];
        $params = array_merge($parts['query'], $params);
        if (!empty($params)) {
            $url .= ('?' . http_build_query($params));
        }

        $deferred = new Deferred();
        $result = new RequestResult();
        $request = $this->client->request('GET', $url, $headers);
        $result->time_request = microtime(true);
        $request->on('response', function(Response $response) use ($request, $deferred, $result) {
            self::handleResponse($request, $response, $result, $deferred);
        });
        $request->on('error', function($error) use ($deferred){
            $deferred->reject($error);
        });
        $request->end();
        return $deferred->promise();
    }

    public function post(string $url, array $post_data = [], array $headers = []): PromiseInterface
    {
        $deferred = new Deferred();
        $result = new RequestResult();
        $post_field = http_build_query($post_data);
        $post_headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Content-Length' => strlen($post_field)
        ];
        $request = $this->client->request('POST', $url, array_merge($post_headers, $headers));
        $result->time_request = microtime(true);
        $request->on('response', function(Response $response) use ($request, $deferred, $result) {
            self::handleResponse($request, $response, $result, $deferred);
        });
        $request->on('error', function($error) use ($deferred){
            $deferred->reject($error);
        });
        $request->end($post_field);
        return $deferred->promise();
    }

    public function jsonPost(string $url, array $post_data = [], array $headers = []): PromiseInterface
    {
        $deferred = new Deferred();
        $result = new RequestResult();
        $post_field = json_encode($post_data, JSON_UNESCAPED_UNICODE);
        $post_headers = [
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($post_field)
        ];
        $request = $this->client->request('POST', $url, array_merge($post_headers, $headers));
        $result->time_request = microtime(true);
        $request->on('response', function(Response $response) use ($request, $deferred, $result) {
            self::handleResponse($request, $response, $result, $deferred);
        });
        $request->on('error', function($error) use ($deferred){
            $deferred->reject($error);
        });
        $request->end($post_field);
        return $deferred->promise();
    }

    private static function handleResponse(Request $request, Response $response, RequestResult $result, Deferred $deferred)
    {
        $result->time_response = microtime(true);
        $result->headers = $response->getHeaders();
        $result->code = $response->getCode();

        $skip_codes = container()->get('config')->get('http-client.ignore-content-on-codes');
        if (is_array($skip_codes) and in_array($result->code, $skip_codes)) {
            $result->time_end = microtime(true);
            $request->close();
            $response->close();
            $deferred->resolve($result);
            return;
        }

        $data = '';
        $chunk_count = 0;
        $response->on('data', function($chunk) use (&$data, &$chunk_count) {
            $data .= $chunk;
            $chunk_count++;
        });
        $response->on('end', function() use (&$data, &$chunk_count, $result, $deferred) {
            $result->data = $data;
            $result->chunk_count = $chunk_count;
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
}