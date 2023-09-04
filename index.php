<?php
require_once __DIR__ . '/vendor/autoload.php';
use Luracast\Restler\Restler;
use api\Stripe;

$restler = new Restler();


$restler->addAPIClass(Stripe::class);

$restler->handle();