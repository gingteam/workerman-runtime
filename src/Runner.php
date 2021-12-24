<?php

declare(strict_types=1);

namespace GingTeam\WorkermanRuntime;

use Nyholm\Psr7\ServerRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
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
    private $httpFoundationFactory;

    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
        $this->httpFoundationFactory = new HttpFoundationFactory();
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

        $psrRequest = static::createRequest($request);
        $sfRequest = $this->httpFoundationFactory->createRequest($psrRequest);
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

    public static function createRequest(Request $request): ServerRequest
    {
        $psrRequest = new ServerRequest(
            $request->method(),
            $request->uri(),
            $request->header(),
            $request->protocolVersion()
        );

        $psrRequest = $psrRequest
            ->withCookieParams($request->cookie())
            ->withQueryParams($request->get())
            ->withParsedBody($request->post())
            ->withUploadedFiles($request->file());

        return $psrRequest;
    }
}
