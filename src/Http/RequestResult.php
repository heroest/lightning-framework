<?php
namespace Lightning\Http;

class RequestResult
{
    public $code = '';
    public $headers = [];
    public $data = '';
    public $chunk_count = 0;
    public $time_request = 0;
    public $time_response = 0;
    public $time_end = 0;    
}