<?php

namespace Lightning\Web;

use React\Http\Io\ServerRequest;

class Input
{
    private $request = null;
    private $postField = null;

    private function __construct(ServerRequest $request)
    {
        $this->request = $request;
    }

    public static function parseRequest(ServerRequest $request): self
    {
        return new self($request);
    }

    public function getServerRequest(): ServerRequest
    {
        return $this->request;
    }

    public function header(string $name, $default = null)
    {
        if (!$this->hasHeader($name)) {
            return $default;
        } else {
            return $this->request->getHeaderLine($name);
        }
    }

    public function hasHeader(string $name)
    {
        return $this->request->hasHeader($name);
    }

    public function isMethod(string $method): bool
    {
        return strtolower($this->request->getMethod()) === strtolower($method); 
    }

    public function cookie(?string $name = null, $default = null)
    {
        return self::basicGetResult(
            $this->request->getCookieParams(),
            $name,
            $default
        );
    }

    public function serverParam(?string $name = null, $default = null)
    {
        return self::basicGetResult(
            $this->request->getServerParams(),
            $name,
            $default
        );
    }

    public function query(?string $name = null, $default = null)
    {
        return self::basicGetResult(
            $this->request->getQueryParams(),
            $name,
            $default
        );
    }

    public function post(?string $name = null, $default = null)
    {
        return self::basicGetResult(
            $this->parsePostParams(),
            $name,
            $default
        );
    }

    public function upload(?string $name = null) 
    {
        return self::basicGetResult(
            $this->request->getUploadedFiles(),
            $name
        );
    }

    public function getClientIp(): string
    {
        $ip = $this->serverParam('REMOTE_ADDR');
        return $ip === null ? '0.0.0.0' : strval($ip);
    }

    public function rawPost(): string
    {   
        if (null === $this->postField) {
            $this->postField = $this->request->getBody()->getContents();
        }
        return $this->postField;
    }

    private static function basicGetResult(array $params, ?string $name, $default = null)
    {
        if (null === $name) {
            return $params;
        } elseif (isset($params[$name])) {
            return $params[$name];
        } else {
            return $default;
        }
    }

    private function parsePostParams()
    {
        $content_type = $this->header('Content-Type');
        if (!empty($content_type) and (false !== stripos($content_type, 'application/json'))) {
            $json = $this->rawPost();
            $data = json_decode($json, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $data : [];
        } else {
            $data = $this->request->getParsedBody();
            return !empty($data) ? $data : [];
        }
    }
}