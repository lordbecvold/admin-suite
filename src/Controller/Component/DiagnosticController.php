<?php

namespace App\Controller\Component;

use App\Util\ServerUtil;
use App\Manager\AuthManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class DiagnosticController
 *
 * This controller is responsible for rendering the diagnostic page
 *
 * @package App\Controller\Component
 */
class DiagnosticController extends AbstractController
{
    private ServerUtil $serverUtil;
    private AuthManager $authManager;

    public function __construct(ServerUtil $serverUtil, AuthManager $authManager)
    {
        $this->serverUtil = $serverUtil;
        $this->authManager = $authManager;
    }

    /**
     * Renders the diagnostic page
     *
     * @return Response The diagnostic page view
     */
    #[Route('/diagnostic', methods:['GET'], name: 'app_diagnostic')]
    public function diagnosticPage(): Response
    {
        // get diagnostic data
        $diagnosticData = $this->serverUtil->getDiagnosticData();

        // return diagnostic view
        return $this->render('component/diagnostic/diagnostics-page.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // diagnostic data
            'diagnosticData' => $diagnosticData
        ]);
    }
}
