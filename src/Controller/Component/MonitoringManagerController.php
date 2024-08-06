<?php

namespace App\Controller\Component;

use App\Util\AppUtil;
use App\Manager\LogManager;
use App\Manager\AuthManager;
use App\Manager\ServiceManager;
use App\Annotation\Authorization;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class MonitoringManagerController
 *
 * Handles the the monitoring manager page
 *
 * @package App\Controller\Component
 */
class MonitoringManagerController extends AbstractController
{
    private AppUtil $appUtil;
    private LogManager $logManager;
    private AuthManager $authManager;
    private ServiceManager $serviceManager;

    public function __construct(
        AppUtil $appUtil,
        LogManager $logManager,
        AuthManager $authManager,
        ServiceManager $serviceManager
    ) {
        $this->appUtil = $appUtil;
        $this->logManager = $logManager;
        $this->authManager = $authManager;
        $this->serviceManager = $serviceManager;
    }

    /**
     * Renders the monitoring manager page
     *
     * @return Response The rendered monitoring manager page view
     */
    #[Route('/manager/monitoring', methods:['GET'], name: 'app_manager_monitoring')]
    public function monitoring(): Response
    {
        // get services list
        $services = $this->serviceManager->getServicesList();

        // get page limit from config
        $pageLimit = (int) $this->appUtil->getEnvValue('LIMIT_CONTENT_PER_PAGE');

        // get monitoring logs
        $monitoringLogs = $this->logManager->getMonitoringLogs($pageLimit);

        // return view
        return $this->render('component/monitoring-manager/monitoring-dashboard.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // monitoring data
            'services' => $services,
            'monitoringLogs' => $monitoringLogs,
            'serviceManager' => $this->serviceManager,
        ]);
    }

    /**
     * Renders the services config page
     *
     * @return Response The rendered monitoring config page view
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/monitoring/config', methods:['GET'], name: 'app_manager_monitoring_config')]
    public function monitoringConfig(): Response
    {
        // get services list
        $services = $this->serviceManager->getServicesList();

        // return view
        return $this->render('component/monitoring-manager/monitoring-config.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // services config data
            'services' => $services,
        ]);
    }
}
