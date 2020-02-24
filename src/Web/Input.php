<?php

namespace Lightning\Web;

use React\Http\Io\ServerRequest;

class Input
{
    private $request = null;

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

    public function getHeader(string $name, $default = null)
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

    public function getCookie(?string $name, $default = null)
    {
        return self::basicGetResult(
            $this->request->getCookieParams(),
            $name,
            $default
        );
    }

    public function getServer(?string $name, $default = null)
    {
        return self::basicGetResult(
            $this->request->getServerParams(),
            $name,
            $default
        );
    }

    public function getQuery(?string $name = null, $default = null)
    {
        return self::basicGetResult(
            $this->request->getQueryParams(),
            $name,
            $default
        );
    }

    public function getPost(?string $name = null, $default = null)
    {
        return self::basicGetResult(
            $this->parsePostParams(),
            $name,
            $default
        );
    }

    public function getUploadFiles(?string $name = null) 
    {
        return self::basicGetResult(
            $this->request->getUploadedFiles(),
            $name
        );
    }

    public function getClientIp(): string
    {
        $ip = $this->getServer('REMOTE_ADDR');
        return $ip === null ? '0.0.0.0' : strval($ip);
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
        $content_type = $this->getHeader('Content-Type');
        if (!empty($content_type) and (false !== stripos($content_type, 'application/json'))) {
            $json = $this->request->getBody()->getContents();
            $data = json_decode($json, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $data : [];
        } else {
            $data = $this->request->getParsedBody();
            return !empty($data) ? $data : [];
        }
    }
}