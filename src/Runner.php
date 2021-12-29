<?php

declare(strict_types=1);

namespace GingTeam\WorkermanRuntime;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Workerman\Connection\ConnectionInterface;
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
        $httpServer            = new Worker('http://0.0.0.0:8000');
        $httpServer->name      = 'Symfony Runtime';
        $httpServer->count     = (int) shell_exec('nproc') * 2;
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

        $sfRequest = new SymfonyRequest(
            $request->get(),
            $request->post(),
            [],
            $request->cookie(),
            $request->file(),
            static::prepareForServer($connection, $request),
            $request->rawBody()
        );

        $sfResponse = $this->kernel->handle($sfRequest);

        switch (true) {
            case $sfResponse instanceof BinaryFileResponse && $sfResponse->headers->has('Content-Range'):
            case $sfResponse instanceof StreamedResponse:
                ob_start(function ($buffer) use ($connection) {
                    $connection->send($buffer);

                    return '';
                });
                $sfResponse->sendContent();
                ob_end_clean();
                break;
            case $sfResponse instanceof BinaryFileResponse:
                /** @var BinaryFileResponse $sfResponse */
                $connection->send((new Response())->withFile($sfResponse->getFile()->getPathname()));
                break;
            default:
                $connection->send(
                    new Response(
                        $sfResponse->getStatusCode(),
                        $sfResponse->headers->all(),
                        $sfResponse->getContent()
                    )
                );
        }

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($sfRequest, $sfResponse);
        }
    }

    public static function prepareForServer(ConnectionInterface $connection, Request $request): array
    {
        $server = [
            'REMOTE_ADDR'        => $connection->getRemoteIp(),
            'REMOTE_PORT'        => $connection->getRemotePort(),
            'REQUEST_URI'        => $request->uri(),
            'REQUEST_METHOD'     => $request->method(),
            'REQUEST_TIME'       => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_PROTOCOL'    => 'HTTP/'.$request->protocolVersion(),
            'HTTP_USER_AGENT'    => '',
        ];

        foreach ($request->header() as $key => $value) {
            $key = \strtoupper(\str_replace('-', '_', (string) $key));
            if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $server[$key] = $value;
            } else {
                $server['HTTP_'.$key] = $value;
            }
        }

        return array_merge($server, $_SERVER);
    }
}
