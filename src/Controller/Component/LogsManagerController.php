<?php

namespace App\Controller\Component;

use App\Util\AppUtil;
use App\Manager\LogManager;
use App\Manager\AuthManager;
use App\Manager\UserManager;
use App\Util\VisitorInfoUtil;
use App\Annotation\Authorization;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class LogsManagerController
 *
 * Controller to manage or display logs
 *
 * @package App\Controller\Component
 */
class LogsManagerController extends AbstractController
{
    private AppUtil $appUtil;
    private LogManager $logManager;
    private UserManager $userManager;
    private AuthManager $authManager;
    private VisitorInfoUtil $visitorInfoUtil;

    public function __construct(
        AppUtil $appUtil,
        LogManager $logManager,
        UserManager $userManager,
        AuthManager $authManager,
        VisitorInfoUtil $visitorInfoUtil
    ) {
        $this->appUtil = $appUtil;
        $this->logManager = $logManager;
        $this->userManager = $userManager;
        $this->authManager = $authManager;
        $this->visitorInfoUtil = $visitorInfoUtil;
    }

    /**
     * Display the logs table view
     *
     * @param Request $request The request object
     *
     * @return Response The logs table view
     */
    #[Route('/manager/logs', methods:['GET'], name: 'app_manager_logs')]
    public function logsTable(Request $request): Response
    {
        // get current page from request query params
        $page = (int) $request->query->get('page', '1');

        // get filter from request query params
        $filter = $request->query->get('filter', 'UNREADED');

        // get user id from query param
        $userId = $request->query->get('user_id', '0');

        // get page filters
        $limitPerPage = $this->appUtil->getPageLimiter();
        $mainDatabase = $this->appUtil->getMainDatabaseName();

        // get logs data
        $isAntiLogEnabled = $this->logManager->isAntiLogEnabled();
        $logsCount = $this->logManager->getLogsCountWhereStatus($filter, (int) $userId);
        $logs = $this->logManager->getLogsWhereStatus($filter, (int) $userId, (int) $page);

        // return logs table view
        return $this->render('component/log-manager/logs-table.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // instances for logs manager view
            'userManager' => $this->userManager,
            'authManager' => $this->authManager,
            'visitorInfoUtil' => $this->visitorInfoUtil,

            // database name
            'mainDatabase' => $mainDatabase,

            // logs data
            'logs' => $logs,
            'logsCount' => $logsCount,

            // anti log data
            'antiLogEnabled' =>  $isAntiLogEnabled,

            // filter helpers
            'filter' => $filter,
            'userId' => $userId,
            'currentPage' => (int) $page,
            'limitPerPage' => $limitPerPage
        ]);
    }

    /**
     * Renders the system logs table
     *
     * @param Request $request The request object
     *
     * @return Response The system log view
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/logs/system', methods:['GET'], name: 'app_manager_logs_system')]
    public function systemLogsTable(Request $request): Response
    {
        // get selected log file from query parameter
        $logFile = $request->query->get('file', 'none');

        // get log files from host system
        $logFiles = $this->logManager->getSystemLogs();

        // deflaut log content value
        $logContent = 'non-selected';

        // check if a log file is selected to display its content
        if ($logFile != 'none') {
            $logContent = $this->logManager->getSystemLogContent($logFile);
        }

        // render the system logs table
        return $this->render('component/log-manager/system-logs.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // log files list
            'logFiles' => $logFiles,

            // current log file name
            'logFile' => $logFile,

            // log file content
            'logContent' => $logContent
        ]);
    }

    /**
     * Fetches and displays the contents of the exception log
     *
     * @param Request $request The request object
     *
     * @return Response The exception log view
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/logs/exception/files', methods:['GET'], name: 'app_manager_logs_exception_files')]
    public function exceptionFiles(Request $request): Response
    {
        // get exception files
        $exceptionFiles = $this->logManager->getExceptionFiles();

        // get selected exception file from query parameter
        $exceptionFile = (string) $request->query->get('file', 'none');

        // deflaut exception file content value
        $exceptionContent = 'non-selected';

        // get exception file content
        if ($exceptionFile !== 'none' && isset($exceptionFiles[$exceptionFile])) {
            $fileInfo = $exceptionFiles[$exceptionFile];

            // Ensure $fileInfo is an array and contains 'path'
            if (is_array($fileInfo) && isset($fileInfo['path'])) {
                $exceptionFilePath = $fileInfo['path'];

                // check if exception file path is a string
                if (is_string($exceptionFilePath) && file_exists($exceptionFilePath)) {
                    $exceptionContent = file_get_contents($exceptionFilePath);
                } else {
                    $exceptionContent = 'exception file not found';
                }
            } else {
                $exceptionContent = 'exception file info invalid';
            }
        }

        // render the exception files view
        return $this->render('component/log-manager/exception-files.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // exception files
            'exceptionFiles' => $exceptionFiles,

            // log file content
            'logName' => $exceptionFile,
            'exceptionContent' => $exceptionContent
        ]);
    }

    /**
     * Delete exception file
     *
     * @param Request $request The request object
     *
     * @return Response The redirect response
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/logs/exception/delete', methods:['GET'], name: 'app_manager_logs_exception_delete')]
    public function deleteExceptionFile(Request $request): Response
    {
        // get exception file name from query parameter
        $exceptionFile = (string) $request->query->get('file', 'none');

        // delete exception file
        if ($exceptionFile !== 'none') {
            $this->logManager->deleteExceptionFile($exceptionFile);
        }

        // redirect back to the exception files page
        return $this->redirectToRoute('app_manager_logs_exception_files');
    }

    /**
     * Sets logs to 'READED'
     *
     * @param Request $request The request object
     *
     * @return Response Redirects to the dashboard page after setting logs to 'READED'
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/logs/set/readed', methods:['GET'], name: 'app_manager_logs_set_readed')]
    public function setAllLogsToReaded(Request $request): Response
    {
        // get current page from request query params
        $page = (int) $request->query->get('page', '1');

        // get filter from request query params
        $filter = $request->query->get('filter', 'UNREADED');

        // get user id from query param
        $userId = $request->query->get('user_id', '0');

        // get log id form query string
        $id = $request->get('id', 0);

        // validate and cast id to int
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

        // set all logs to readed
        if ($id == 0) {
            $this->logManager->setAllLogsToReaded();
            return $this->redirectToRoute('app_dashboard');
        }

        // set log status to readed
        $this->logManager->updateLogStatusById($id, 'READED');

        // redirect back to the logs table page
        return $this->redirectToRoute('app_manager_logs', [
            'page' => $page,
            'filter' => $filter,
            'user_id' => $userId
        ]);
    }
}
