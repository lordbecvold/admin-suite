<?php

namespace App\Middleware;

use ReflectionMethod;
use Twig\Environment;
use App\Manager\AuthManager;
use App\Annotation\Authorization;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Class AuthorizationMiddleware
 *
 * The middleware for checking the user authorization
 *
 * @package App\Middleware
 */
class AuthorizationMiddleware
{
    private Environment $twig;
    private AuthManager $authManager;

    public function __construct(Environment $twig, AuthManager $authManager)
    {
        $this->twig = $twig;
        $this->authManager = $authManager;
    }

    /**
     * Handle the user authorization check
     *
     * @param RequestEvent $event The request event
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        /** @var string $controller controller class path */
        $controller = $request->attributes->get('_controller');

        // split the controller string into the class and method
        list($controllerClass, $methodName) = explode('::', $controller);

        // check if the method exists in the controller class
        if (!method_exists($controllerClass, $methodName)) {
            return;
        }

        // get the reflection method object
        $reflectionMethod = new ReflectionMethod($controllerClass, $methodName);

        // get authorization attribute from method annotation
        $authorization = $reflectionMethod->getAttributes(Authorization::class);

        // check if annotation exists
        if (empty($authorization)) {
            $authorizationRequired = 'USER';
        } else {
            // get authorization attribute from annotation
            $authorizationAttribute = $authorization[0]->newInstance();
            $authorizationRequired = $authorizationAttribute->getAuthorization();
        }

        // check if user have permission to access the page
        if ($authorizationRequired == 'ADMIN' && !$this->authManager->isLoggedInUserAdmin()) {
            // render the maintenance template
            $content = $this->twig->render('component/no-permissions.twig', [
                'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
                'userData' => $this->authManager->getLoggedUserRepository()
            ]);

            // set the response
            $response = new Response($content, Response::HTTP_FORBIDDEN);
            $event->setResponse($response);
        }
    }
}
