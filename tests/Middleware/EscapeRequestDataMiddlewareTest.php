<?php

namespace App\Tests\Middleware;

use App\Util\SecurityUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use App\Middleware\EscapeRequestDataMiddleware;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class EscapeRequestDataMiddlewareTest
 *
 * Test cases for EscapeRequestDataMiddleware class
 *
 * @package App\Tests\Middleware
 */
class EscapeRequestDataMiddlewareTest extends TestCase
{
    /**
     * Test the security escaping of request data
     *
     * @return void
     */
    public function testEscapeRequestData(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        // arrange
        $securityUtil = $this->createMock(SecurityUtil::class);
        $securityUtil->method('escapeString')->willReturnCallback(function ($value) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
        });

        // create a request with unescaped data
        $requestData = [
            'name' => '<script>alert("XSS Attack!");</script>',
            'email' => 'user@example.com',
            'message' => '<p>Hello, World!</p>'
        ];

        // create a request event
        $request = new Request([], $requestData);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        // create a request event
        $event = new RequestEvent(
            $this->createMock('Symfony\Component\HttpKernel\HttpKernelInterface'),
            $request,
            1
        );

        // act
        $middleware = new EscapeRequestDataMiddleware($securityUtil, $urlGenerator);
        $middleware->onKernelRequest($event);

        // assert response
        $this->assertEquals('&lt;script&gt;alert(&quot;XSS Attack!&quot;);&lt;/script&gt;', $request->get('name'));
        $this->assertEquals('user@example.com', $request->get('email'));
        $this->assertEquals('&lt;p&gt;Hello, World!&lt;/p&gt;', $request->get('message'));
    }
}
