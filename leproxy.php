<?php

namespace LeProxy\LeProxy;

use Clue\Commander\Router;
use Clue\Commander\NoRouteFoundException;
use React\EventLoop\Factory;

require __DIR__ . '/vendor/autoload.php';

// parse options from command line arguments (argv)
$commander = new Router();
$commander->add('-h | --help', function () {
    exit('LeProxy HTTP/SOCKS proxy

Usage:
    $ php leproxy.php [<listenAddress> [<upstreamProxy>...]]
    $ php leproxy.php --help

Arguments:
    <listenAddress>
        The socket address to listen on.
        The address consists of a full URI which may contain a username and
        password, host and port.
        By default, LeProxy will listen on the address 127.0.0.1:1080.

    <upstreamProxy>
        An upstream proxy servers where each connection request will be
        forwarded to (proxy chaining).
        Any number of upstream proxies can be given.
        Each address consists of full URI which may contain a scheme, username
        and password, host and port. Default scheme is `http://`.

    --help, -h
        shows this help and exits

Examples:
    $ php leproxy.php
        Runs LeProxy on default address 127.0.0.1:1080 (local only)

    $ php leproxy.php user:pass@0.0.0.0:1080
        Runs LeProxy on all addresses (public) and require authentication

    $ php leproxy.php 127.0.0.1:1080 http://user:pass@127.1.1.1:1080
        Runs LeProxy locally without authentication and forwards all connection
        requests through an upstream proxy that requires authentication.
');
});
$commander->add('[<listen> [<path>...]]', function ($args) {
    return $args + array(
        'listen' => '127.0.0.1:1080',
        'path' => array()
    );
});
try {
    $args = $commander->handleArgv();
} catch (NoRouteFoundException $e) {
    fwrite(STDERR, 'Usage Error: Invalid command arguments given, see --help' . PHP_EOL);

    // sysexits.h: #define EX_USAGE 64 /* command line usage error */
    exit(64);
}

$loop = Factory::create();

// set next proxy server chain -> p1 -> p2 -> p3 -> destination
$connector = ConnectorFactory::createConnectorChain($args['path'], $loop);

// listen on 127.0.0.1:1080 or first argument
$proxy = new LeProxyServer($loop, $connector);
$socket = $proxy->listen($args['listen']);

$addr = str_replace('tcp://', 'http://', $socket->getAddress());
echo 'LeProxy HTTP/SOCKS proxy now listening on ' . $addr . PHP_EOL;

if ($args['path']) {
    echo 'Forwarding via: ' . implode(' -> ', $args['path']) . PHP_EOL;
}

$loop->run();
