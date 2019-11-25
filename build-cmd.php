<?php

$date_tag = date('ymdHis');
$phar = new Phar("cmd.{$date_tag}.phar");
$phar->buildFromDirectory(dirname(__FILE__) . '/lightning', '#^((?!tests).)*$#i');
$phar->setStub($phar->createDefaultStub('cmd'));