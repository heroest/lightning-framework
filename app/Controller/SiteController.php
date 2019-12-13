<?php

namespace App\Controller;

use Lightning\MVC\Output;
use Psr\Http\Message\ServerRequestInterface;
use function Lightning\container;

class SiteController
{
    public function testOutputAction(ServerRequestInterface $request, Output $output)
    {
        // echo "handling\r\n";
        $dbm = container()->get('dbm');
        $output->setData(Output::TYPE_TEXT, 'hello-from-lighting');
        $output->send();
        // echo "still doing something\r\n";
        // $this->memoryWatch();
    }

    public function testMkDatabaseAction(ServerRequestInterface $request, Output $output)
    {
        $dbm = container()->get('dbm');
        $rand = mt_rand(1000, 9999);
        $sql = "SELECT {$rand} AS random_num, id FROM user ORDER BY RAND() LIMIT 1";
        $promise = $dbm->query('vip-music', $sql, 'slave', 'fetch_row');
        $promise->then(function($query_result) use ($output) {
            try {
                $row = $query_result->result;
                $id = $row['id'];
                $id = str_pad($id, 36, 'a', STR_PAD_LEFT);
                $output->setData(Output::TYPE_JSON, ['pad_id' => $id]);
                $output->send();
            } catch (\Throwable $e) {
                echo $e->getMessage() . "\r\n";
            }
        });
    }

    public function testDatabaseAction(ServerRequestInterface $request, Output $output)
    {
        try {
            $dbm = container()->get('dbm');
            $sql = "SELECT id FROM user ORDER BY RAND() LIMIT 1";
            $promise = $dbm->query('home-db', $sql, 'slave', 'fetch_row');
            $promise->then(function($query_result) use ($output){
                $result = $query_result->result;
                $id = current($result)['id'];
                $id = str_pad($id, 36, 'a', STR_PAD_LEFT);
                $output->setData(Output::TYPE_JSON, ['pad_id' => $id]);
                $output->send();
            });
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }
}