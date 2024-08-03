<?php

namespace App\Controller;

use App\Manager\AuthManager;
use App\Manager\ErrorManager;
use App\Manager\ServiceManager;
use App\Annotation\Authorization;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class ActionRunnerController
 *
 * The controller for the action runner component
 *
 * @package App\Controller
 */
class ActionRunnerController extends AbstractController
{
    private AuthManager $authManager;
    private ErrorManager $errorManager;
    private ServiceManager $serviceManager;

    public function __construct(
        AuthManager $authManager,
        ErrorManager $errorManager,
        ServiceManager $serviceManager
    ) {
        $this->authManager = $authManager;
        $this->errorManager = $errorManager;
        $this->serviceManager = $serviceManager;
    }

    /**
     * Handle the service action runner component
     *
     * @param Request $request The request object
     *
     * @return Response The redirect response
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/service/action/runner', methods:['GET'], name: 'app_action_runner')]
    public function runServiceAction(Request $request): Response
    {
        // check if user is logged in
        if (!$this->authManager->isUserLogedin()) {
            return $this->redirectToRoute('app_auth_login');
        }

        // get request parameters
        $action = (string) $request->query->get('action', null);
        $referer = (string) $request->query->get('referer', null);
        $service = (string) $request->query->get('service', null);

        // check if request parameters are null
        if ($referer == null || $service == null || $action == null) {
            $this->errorManager->handleError(
                message: 'parameters service, action and referer are required',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // run service action
        $this->serviceManager->runSystemdAction($service, $action);

        // redirect back to referer
        return $this->redirectToRoute($referer);
    }
}
