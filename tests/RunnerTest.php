<?php

use GingTeam\WorkermanRuntime\Runner;
use PHPUnit\Framework\TestCase;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Protocols\Http\Request;

test('request', function () {
    $request = new Request("GET / HTTP/1.1\r\nHost: localhost:8000\r\nUser-Agent: Symfony\r\n\r\n");
    $connection = new AsyncTcpConnection('0.0.0.0:8000');

    $server = Runner::prepareForServer($connection, $request);

    /** @var TestCase $this */
    $this->assertSame('/', $server['REQUEST_URI']);
    $this->assertSame('GET', $server['REQUEST_METHOD']);
    $this->assertSame('Symfony', $server['HTTP_USER_AGENT']);
});
