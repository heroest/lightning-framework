<?php

namespace App\Controller;

use function Lightning\{container, loop};
use Lightning\Database\Query;
use Lightning\Database\QueryComponent\Expression;
class TestController
{
    public function showNameAction($name)
    {
        echo "hi, {$name}\r\n";
    }

    public function testQueryAction()
    {
        $sub = (new Query('vip-muisc'))
            ->from('user_public_info up')
            ->where(['up.purchase' => 0])
            ->where(['up.user_id' => new Expression('u.id')]);
        $query = new Query('vip-music');
        $promise = $query->from('user u')
                ->select(['id', 'nick'])
                ->where([
                    'AND', 
                    [
                        'OR', ['nick' => 'abc'], ['is_refund' => 1]
                    ],
                    ['NOT EXISTS', $sub]
                ])
                ->limit(7)
                ->all();
        $promise->then(function($query_result){
            var_dump($query_result->result);
        }, function($error){
            var_dump($error->getMessage());
        });
        return $promise;
    }

    public function mqWatcherAction()
    {
        $client = container()->get('http-client');
        $promise  = $client->get('https://www.jandan.net');
        $promise->then(function ($result) {
            echo $result->code . '-jandan:' . $result->time_end . "\r\n";
        }, function ($error) {
            echo $error->getMessage();
        });
        $promise = $client->get('http://www.baidu.com');
        $promise->then(function ($result) {
            echo $result->code . '-baidu:' . $result->time_end . "\r\n";
        }, function ($error) {
            echo $error->getMessage();
        });

        $this->memoryWatch();
        echo "started\r\n";
    }

    private function memoryWatch()
    {
        loop()->addPeriodicTimer(10, function () {
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
