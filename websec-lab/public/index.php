<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/catalog.php';
require dirname(__DIR__) . '/src/media.php';
require dirname(__DIR__) . '/src/http.php';
require dirname(__DIR__) . '/src/handlers.php';

start_session();
route_request();
