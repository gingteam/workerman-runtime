<?php

use GingTeam\WorkermanRuntime\Runner;
use PHPUnit\Framework\TestCase;
use Workerman\Protocols\Http\Request;

test('request', function () {
    $request = new Request("GET / HTTP/1.1\r\nHost: localhost:8000\r\nUser-Agent: Symfony\r\n\r\n");
    $psrRequest = Runner::createRequest($request);

    /** @var TestCase $this */
    $this->assertSame('Symfony', $psrRequest->getHeaderLine('User-Agent'));
});
