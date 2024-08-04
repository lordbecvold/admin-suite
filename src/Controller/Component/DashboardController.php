<?php

namespace App\Controller\Component;

use App\Util\ServerUtil;
use App\Manager\BanManager;
use App\Manager\LogManager;
use App\Manager\UserManager;
use App\Manager\AuthManager;
use App\Manager\ServiceManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class DashboardController
 *
 * Controller to handle the dashboard component
 *
 * @package App\Controller
 */
class DashboardController extends AbstractController
{
    private ServerUtil $serverUtil;
    private LogManager $logManager;
    private BanManager $banManager;
    private UserManager $userManager;
    private AuthManager $authManager;
    private ServiceManager $serviceManager;

    public function __construct(
        ServerUtil $serverUtil,
        LogManager $logManager,
        BanManager $banManager,
        UserManager $userManager,
        AuthManager $authManager,
        ServiceManager $serviceManager
    ) {
        $this->serverUtil = $serverUtil;
        $this->logManager = $logManager;
        $this->banManager = $banManager;
        $this->userManager = $userManager;
        $this->authManager = $authManager;
        $this->serviceManager = $serviceManager;
    }

    /**
     * Handle the dashboard page view
     *
     * @return Response The dashboard view
     */
    #[Route('/dashboard', methods:['GET'], name: 'app_dashboard')]
    public function dashboard(): Response
    {
        // get warning data
        $diagnosticData = $this->serverUtil->getDiagnosticData();
        $antiLogStatus = $this->logManager->isAntiLogEnabled();

        // get host system info
        $ramUsage = $this->serverUtil->getRamUsage();
        $diskUsage = $this->serverUtil->getDiskUsage();
        $hostUptime = $this->serverUtil->getHostUptime();
        $hostSystemInfo = $this->serverUtil->getSystemInfo();

        // get process list
        $processList = $this->serverUtil->getProcessList();

        // service manager
        $services = $this->serviceManager->getServicesList();

        // get logs count
        $authLogsCount = $this->logManager->getAuthLogsCount();
        $allLogsCount = $this->logManager->getLogsCountWhereStatus();
        $readedLogsCount = $this->logManager->getLogsCountWhereStatus('READED');
        $unreadedLogsCount = $this->logManager->getLogsCountWhereStatus('UNREADED');

        // get users count
        $onlineUsersCount = count($this->authManager->getOnlineUsersList());
        $bannedUsersCount = $this->banManager->getBannedCount();
        $usersCount = $this->userManager->getUsersCount();

        // return dashboard view
        return $this->render('component/dashboard/dashboard.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // warning data
            'antiLogStatus' => $antiLogStatus,
            'diagnosticData' => $diagnosticData,

            // host system info
            'ramUsage' => $ramUsage,
            'diskUsage' => $diskUsage,
            'hostUptime' => $hostUptime,
            'hostSystemInfo' => $hostSystemInfo,

            // process list
            'processList' => $processList,

            // service manager
            'serviceManager' => $this->serviceManager,
            'services' => $services,

            // logs count
            'allLogsCount' => $allLogsCount,
            'authLogsCount' => $authLogsCount,
            'readedLogsCount' => $readedLogsCount,
            'unreadedLogsCount' => $unreadedLogsCount,

            // users count
            'usersCount' => $usersCount,
            'onlineUsersCount' => $onlineUsersCount,
            'bannedUsersCount' => $bannedUsersCount
        ]);
    }
}
