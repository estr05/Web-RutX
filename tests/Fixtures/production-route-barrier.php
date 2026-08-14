<?php

declare(strict_types=1);
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$root = $kernel->handle(Request::create('/', 'GET'));
$playground = $kernel->handle(Request::create('/playground', 'GET'));

echo 'ROOT:'.$root->getStatusCode().' PLAYGROUND:'.$playground->getStatusCode();
