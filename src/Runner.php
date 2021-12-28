<?php

declare(strict_types=1);

namespace GingTeam\WorkermanRuntime;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class Runner implements RunnerInterface
{
    private $kernel;

    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function run(): int
    {
        $httpServer = new Worker('http://0.0.0.0:8000');

        $httpServer->name = 'Symfony Runtime';
        $httpServer->count = (int) shell_exec('nproc') * 2;

        $httpServer->onMessage = [$this, 'handle'];

        Worker::runAll();

        return 0;
    }

    public function handle(TcpConnection $connection, Request $request)
    {
        $path = realpath(getcwd().$request->path());
        if (false !== $path && is_file($path)) {
            $connection->send((new Response())->withFile($path));

            return;
        }

        $server = array_merge(
            $_SERVER,
            [
                'REMOVE_ADDR' => $connection->getRemoteIp(),
                'REMOVE_PORT' => $connection->getRemotePort(),
            ],
            static::prepareForServer($request)
        );

        $sfRequest = new SymfonyRequest(
            $request->get(),
            $request->post(),
            [],
            $request->cookie(),
            $request->file(),
            $server,
            $request->rawBody()
        );

        $sfResponse = $this->kernel->handle($sfRequest);

        $connection->send(
            new Response(
                $sfResponse->getStatusCode(),
                $sfResponse->headers->all(),
                $sfResponse->getContent()
            )
        );

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($sfRequest, $sfResponse);
        }
    }

    public static function prepareForServer(Request $request): array
    {
        $server = [
            'REQUEST_URI' => $request->uri(),
            'REQUEST_METHOD' => $request->method(),
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_PROTOCOL' => 'HTTP/'.$request->protocolVersion(),
            'HTTP_USER_AGENT' => '',
        ];

        foreach ($request->header() as $key => $value) {
            $key = \strtoupper(\str_replace('-', '_', (string) $key));
            if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $server[$key] = $value;
            } else {
                $server['HTTP_'.$key] = $value;
            }
        }

        return $server;
    }
}
