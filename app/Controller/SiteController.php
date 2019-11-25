<?php

namespace App\Controller;

use Lightning\MVC\ResponseBuilder AS Response;
use Psr\Http\Message\ServerRequestInterface;
use function Lightning\container;

class SiteController
{
    public function testOutputAction(ServerRequestInterface $request, Response $response)
    {
        // echo "handling\r\n";
        $dbm = container()->get('dbm');
        $response->setData(Response::TYPE_TEXT, 'hello-from-lighting');
        $response->send();
        // echo "still doing something\r\n";
        // $this->memoryWatch();
    }

    public function testDatabaseAction(ServerRequestInterface $request, Response $response)
    {
        try {
            $dbm = container()->get('dbm');
            $sql = "SELECT id FROM user ORDER BY RAND() LIMIT 1";
            $promise = $dbm->query('home-db', $sql, 'slave', true);
            $promise->then(function($query_result) use ($response){
                $result = $query_result->result;
                $id = current($result)['id'];
                $id = str_pad($id, 36, 'a', STR_PAD_LEFT);
                $response->setData(Response::TYPE_JSON, ['pad_id' => $id]);
                $response->send();
            });
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

    private function memoryWatch()
    {
        $loop = container()->get('loop');
        $loop->addPeriodicTimer(10, function() {
            $byte = memory_get_peak_usage(true);
            $peak = number_format(bcdiv($byte, 1024, 4), 2) . "KB";
            $byte = memory_get_usage(true);
            $current = number_format(bcdiv($byte, 1024, 4), 2) . "KB";
            $byte = memory_get_usage();
            $php_current = number_format(bcdiv($byte, 1024, 4), 2) . "KB";
            $byte = memory_get_peak_usage(true);
            $php_peak = number_format(bcdiv($byte, 1024, 4), 2) . "KB";
            echo json_encode(['total_peak' => $peak, 'total_current' => $current, 'php_peak' => $php_peak, 'php_current' => $php_current]) . "\r\n";
        });
    }
}