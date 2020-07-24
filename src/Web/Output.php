<?php

namespace Lightning\Web;

use React\Promise\{Deferred, PromiseInterface};
use React\Http\Response;
use InvalidArgumentException;

class Output
{
    const FORMAT_TEXT = 'text';
    const FORMAT_HTML = 'html';
    const FORMAT_JSON = 'json';

    private $code = 200;
    private $data;
    private $headers = [];

    private $format = self::FORMAT_TEXT;
    private $deferred;
    private $sent = false;

    public function __construct()
    {
        $this->deferred = new Deferred(function () {
            $this->sent = true;
        });
    }

    /**
     * set response content
     *
     * @param mixed $mixed
     * @return self
     */
    public function setContent($mixed): self
    {
        switch ($this->format) {
            case self::FORMAT_TEXT:
                if (!is_string($mixed)) {
                    throw new InvalidArgumentException("2nd parameter expected to be a string when content-type is set to text");
                }
                $this->data = $mixed;
                break;

            case self::FORMAT_HTML:
                if (!is_string($mixed)) {
                    throw new InvalidArgumentException("2nd parameter expected to be a string when content-type is set to html");
                }
                $this->data = $mixed;
                break;

            case self::FORMAT_JSON:
                $this->data = is_string($mixed) ? $mixed : json_encode($mixed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
        }
        return $this;
    }

    /**
     * set repsonse content format
     *
     * @param string $format
     * @return self
     */
    public function setFormat(string $format): self
    {
        $content_type = '';
        switch ($format) {
            case self::FORMAT_TEXT: $content_type = 'text/plain'; $this->format = $format; break;
            case self::FORMAT_HTML: $content_type = 'text/html'; $this->format = $format; break;
            case self::FORMAT_JSON: $content_type = 'application/json'; $this->format = $format; break;
            default: throw new InvalidArgumentException("Unknown response format: {$format}");
        }
        $this->headers['Content-Type'] = "{$content_type};charset=UTF-8";
        return $this;
    }

    public function setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function setMultipleHeader(array $headers): self
    {
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
        return $this;
    }

    public function setStatusCode(int $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function send()
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;
        $headers = self::normalizeHeaders($this->headers);
        $this->deferred->resolve(new Response($this->code, $headers, $this->data));
    }

    public function promise(): PromiseInterface
    {
        return $this->deferred->promise();
    }

    private static function normalizeHeaders(array $headers)
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $arr = array_map('ucfirst', explode('-', $key));
            $result[implode('-', $arr)] = $value;
        }
        return $result;
    }
}
