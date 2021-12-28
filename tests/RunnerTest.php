<?php

use GingTeam\WorkermanRuntime\Runner;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Request;

test('request', function () {
    $request = new Request("GET / HTTP/1.1\r\nHost: localhost:8000\r\nUser-Agent: Symfony\r\n\r\n");
    $server = Runner::prepareForServer($request);

    /** @var TestCase $this */
    $this->assertSame('Symfony', $server['HTTP_USER_AGENT']);
});
