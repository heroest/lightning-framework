<?php

namespace App\Controller;

use Lightning\MVC\Output;
use Psr\Http\Message\ServerRequestInterface;
use function Lightning\container;

class SiteController
{
    public function testOutputAction(ServerRequestInterface $request, Output $output)
    {
        $dbm = container()->get('dbm');
        $output->setData(Output::TYPE_TEXT, 'hello-from-lighting');
        $output->send();
    }

}