<?php
namespace Lightning\MVC;

use React\Promise\Deferred;
use React\Http\Response;
use InvalidArgumentException;
use Throwable;

class ResponseBuilder
{
    const TYPE_TEXT = 'text';
    const TYPE_HTML = 'html';
    const TYPE_JSON = 'json';

    private $code = 200;
    private $data;
    private $headers = [];

    private $deferred;
    private $sent = false;
    
    public function __construct()
    {
        $this->deferred = new Deferred();
    }

    public function setData(string $type = self::TYPE_TEXT, $mixed)
    {
        $content_type = '';
        switch ($type) {
            case self::TYPE_TEXT: 
                if (!is_string($mixed)) {
                    throw new InvalidArgumentException("2nd parameter expected to be a string when content-type is set to text");
                }
                $content_type = 'text/plain';
                $this->data = $mixed;
                break;

            case self::TYPE_HTML: 
                if (!is_string($mixed)) {
                    throw new InvalidArgumentException("2nd parameter expected to be a string when content-type is set to html");
                }
                $content_type = 'text/html';
                $this->data = $mixed;
                break;

            case self::TYPE_JSON: 
                $content_type = 'application/json'; 
                $this->data = is_string($mixed) ? $mixed : json_encode($mixed, JSON_UNESCAPED_UNICODE);
                break;

            default:
                throw new InvalidArgumentException("Unknown response content type: {$type}");
        }
        $this->headers['Content-Type'] = "{$content_type};charset=UTF-8";
    }

    public function setHeader(string $key, string $value)
    {
        $this->headers[$key] = $value;
    }

    public function setMultipleHeader(array $headers)
    {
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
    }

    public function send()
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;
        $headers = self::normalizeHeaders($this->headers);
        $this->deferred->resolve(new Response($this->code, $headers, $this->data));
        // $this->deferred = null;
    }

    public function promise()
    {
        return $this->deferred->promise();
    }

    private function normalizeHeaders(array $headers)
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $arr = array_map('ucfirst', explode('-', $key));
            $result[implode('-', $arr)] = $value;
        }
        return $result;
    }
}