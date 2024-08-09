<?php

namespace App\Controller\Component;

use App\Manager\AuthManager;
use App\Annotation\Authorization;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class TerminalController
 *
 * This controller is responsible for rendering the terminal page
 *
 * @package App\Controller\Component
 */
class TerminalController extends AbstractController
{
    private AuthManager $authManager;

    public function __construct(AuthManager $authManager)
    {
        $this->authManager = $authManager;
    }

    /**
     * Renders the terminal page
     *
     * @return Response The terminal page view
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/terminal', methods:['GET'], name: 'app_terminal')]
    public function terminalPage(): Response
    {
        // return terminal view
        return $this->render('component/terminal/terminal.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository()
        ]);
    }
}
