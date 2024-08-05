<?php

namespace App\Middleware;

use App\Manager\AuthManager;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class AuthentificatedCheckMiddleware
 *
 * Middleware for checking authentication status before accessing admin routes
 *
 * @package App\Middleware
 */
class AuthentificatedCheckMiddleware
{
    private AuthManager $authManager;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(AuthManager $authManager, UrlGeneratorInterface $urlGenerator)
    {
        $this->authManager = $authManager;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Handles login redirect for non-authenticated users
     *
     * @param RequestEvent $event The request event
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        // check if path info is a string and not login or register page
        if (
            is_string($pathInfo) &&
            !str_starts_with($pathInfo, '/api') &&
            $pathInfo !== '/login' &&
            $pathInfo !== '/register' &&
            $pathInfo !== '/' &&
            !str_starts_with($pathInfo, '/error') &&
            !preg_match('#^/(_profiler|_wdt)#', $pathInfo)
        ) {
            // check if user is loggedin
            if (!$this->authManager->isUserLogedin()) {
                // get login page route url
                $loginUrl = $this->urlGenerator->generate('app_auth_login');

                // redirect to login page
                $event->setResponse(new RedirectResponse($loginUrl));
            }
        }
    }
}
