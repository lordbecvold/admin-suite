<?php

namespace App\Tests\Middleware;

use App\Util\AppUtil;
use Twig\Environment;
use App\Manager\BanManager;
use App\Manager\LogManager;
use App\Manager\AuthManager;
use PHPUnit\Framework\TestCase;
use App\Middleware\BannedCheckMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Class BannedCheckMiddlewareTest
 *
 * Test the banned check middleware
 *
 * @package App\Tests\Middleware
 */
class BannedCheckMiddlewareTest extends TestCase
{
    /** @var AppUtil|MockObject */
    private AppUtil|MockObject $appUtil;

    /** @var Environment|MockObject */
    private Environment|MockObject $twig;

    /** @var LogManager|MockObject */
    private LogManager|MockObject $logManager;

    /** @var BanManager|MockObject */
    private BanManager|MockObject $banManager;

    /** @var AuthManager|MockObject */
    private AuthManager|MockObject $authManager;

    /**
     * Sets up the mock objects before each test
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->appUtil = $this->createMock(AppUtil::class);
        $this->twig = $this->createMock(Environment::class);
        $this->logManager = $this->createMock(LogManager::class);
        $this->banManager = $this->createMock(BanManager::class);
        $this->authManager = $this->createMock(AuthManager::class);
    }

    /**
     * Test request user banned
     *
     * @return void
     */
    public function testRequestUserBanned(): void
    {
        $this->appUtil->method('getEnvValue')->willReturn('admin@example.com');
        $this->authManager->method('isUserLogedin')->willReturn(true);
        $this->authManager->method('getLoggedUserId')->willReturn(1);
        $this->banManager->method('isUserBanned')->with(1)->willReturn(true);
        $this->banManager->method('getBanReason')->with(1)->willReturn('Violation of terms');

        $this->twig->method('render')->with('error/error-banned.twig', [
            'reason' => 'Violation of terms',
            'admin_contact' => 'admin@example.com'
        ])->willReturn('Rendered Template');

        $middleware = new BannedCheckMiddleware(
            $this->appUtil,
            $this->twig,
            $this->logManager,
            $this->banManager,
            $this->authManager
        );

        $request = new Request();
        $event = $this->createMock(RequestEvent::class);
        $event->method('getRequest')->willReturn($request);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($this->callback(function ($response) {
                return $response instanceof Response && $response->getStatusCode() === 403 && $response->getContent() === 'Rendered Template';
            }));

        $middleware->onKernelRequest($event);
    }

    /**
     * Test request user not banned
     *
     * @return void
     */
    public function testRequestUserNotBanned(): void
    {
        $this->authManager->method('isUserLogedin')->willReturn(true);
        $this->authManager->method('getLoggedUserId')->willReturn(1);
        $this->banManager->method('isUserBanned')->with(1)->willReturn(false);

        $middleware = new BannedCheckMiddleware(
            $this->appUtil,
            $this->twig,
            $this->logManager,
            $this->banManager,
            $this->authManager
        );

        $request = new Request();
        $event = $this->createMock(RequestEvent::class);
        $event->method('getRequest')->willReturn($request);

        $event->expects($this->never())->method('setResponse');

        $middleware->onKernelRequest($event);
    }
}
