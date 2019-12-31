<?php

namespace App\Controller;

use Lightning\MVC\Output;
use Psr\Http\Message\ServerRequestInterface;
use function Lightning\{container, await, loop};
use Lightning\Database\Query;
use Lightning\Database\QueryComponent\Expression;

class SiteController
{
    public function testOutputAction(ServerRequestInterface $request, Output $output)
    {
        $dbm = container()->get('dbm');
        $output->setData(Output::TYPE_TEXT, 'hello-from-lighting');
        $output->send();
    }

    public function qrCodeAction(ServerRequestInterface $request, Output $output)
    {
        $post_data = $request->getParsedBody();
        $promise = (new Query('vip-music', 'slave'))
                ->from('user')
                ->select(['channel_id_self'])
                ->where(['>', 'channel_id_self', 0])
                ->orderBy(new Expression('RAND()'))
                ->one();
        // $promise->then(function($query_result) use ($output) {
        //     try {
        //         $output->setData(Output::TYPE_JSON, ['result' => $query_result->result]);
        //         $output->send();
        //     } catch (Exception $e) {
        //         echo $e->getMessage() . "\r\n" . $e->getTraceAsString() . "\r\n";
        //     }
        // });

        $result = await($promise, loop());
        $data = $result->result;
        $id = $data['channel_id_self'];
        $output->setData(Output::TYPE_JSON, ['data' => str_pad($id, 20, 0)]);
        $output->send();
    }
}