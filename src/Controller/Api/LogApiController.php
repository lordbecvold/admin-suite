<?php

namespace App\Controller\Api;

use App\Util\AppUtil;
use App\Manager\LogManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class LogApiController
 *
 * The controller for the external log API
 *
 * @package App\Controller\Api
 */
class LogApiController extends AbstractController
{
    private AppUtil $appUtil;
    private LogManager $logManager;

    public function __construct(AppUtil $appUtil, LogManager $logManager)
    {
        $this->appUtil = $appUtil;
        $this->logManager = $logManager;
    }

    /**
     * Handle the external log API
     *
     * @param Request $request The request object
     *
     * @return Response The JSON response
     */
    #[Route('/api/external/log', methods:['GET'], name: 'app_api_external_log')]
    public function externalLog(Request $request): Response
    {
        // get access token
        $accessToken = (string) $request->query->get('token');

        // check if token is set
        if (empty($accessToken)) {
            return $this->json([
                'error' => 'Access token is not set'
            ], Response::HTTP_BAD_REQUEST);
        }

        // get api token
        $apiToken = $this->appUtil->getExternalApiLogToken();

        // check if token is valid
        if ($accessToken != $apiToken) {
            return $this->json([
                'error' => 'Access token is invalid'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // get log data
        $name = (string) $request->query->get('name');
        $message = (string) $request->query->get('message');
        $level = (int) $request->query->get('level');

        // check if parameters are set
        if (empty($name) || empty($message) || empty($level)) {
            return $this->json([
                'error' => 'Parameters name, message and level are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // log the message
        $this->logManager->log($name, $message, $level);

        return $this->json([
            'success' => 'Log message has been logged'
        ], Response::HTTP_OK);
    }
}
