<?php

namespace App\Manager;

use App\Entity\Log;
use App\Util\AppUtil;
use App\Util\CookieUtil;
use App\Util\SessionUtil;
use App\Util\VisitorInfoUtil;
use Symfony\Component\Finder\Finder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class LogManager
 *
 * The manager for logging
 *
 * @package App\Manager
 */
class LogManager
{
    // log levels constants
    public const LEVEL_CRITICAL = 1;
    public const LEVEL_WARNING = 2;
    public const LEVEL_NOTICE = 3;
    public const LEVEL_INFO = 4;

    private AppUtil $appUtil;
    private CookieUtil $cookieUtil;
    private SessionUtil $sessionUtil;
    private ErrorManager $errorManager;
    private VisitorInfoUtil $visitorInfoUtil;
    private EntityManagerInterface $entityManager;

    public function __construct(
        AppUtil $appUtil,
        CookieUtil $cookieUtil,
        SessionUtil $sessionUtil,
        ErrorManager $errorManager,
        VisitorInfoUtil $visitorInfoUtil,
        EntityManagerInterface $entityManager
    ) {
        $this->appUtil = $appUtil;
        $this->cookieUtil = $cookieUtil;
        $this->sessionUtil = $sessionUtil;
        $this->errorManager = $errorManager;
        $this->entityManager = $entityManager;
        $this->visitorInfoUtil = $visitorInfoUtil;
    }

    /**
     * Log a message to the database
     *
     * @param string $name The log name
     * @param string $message The log message
     * @param int $level The log level
     *
     * @throws \Exception If the log entity cannot be persisted
     *
     * @return void
     */
    public function log(string $name, string $message, int $level = 1): void
    {
        // check if log can be saved
        if (str_contains($message, 'Connection refused')) {
            return;
        }

        // check if log blocking is enabled
        if ($this->isAntiLogEnabled()) {
            return;
        }

        // check if database logging is enabled
        if (!$this->appUtil->isDatabaseLoggingEnabled()) {
            return;
        }

        // check required log level
        if ($level > $this->appUtil->getLogLevel()) {
            return;
        }

        // get user data
        $ipAddress = $this->visitorInfoUtil->getIP();
        $userAgent = (string) $this->visitorInfoUtil->getUserAgent();

        // check if the ip address is null
        if ($ipAddress == null) {
            $ipAddress = 'Unknown';
        }

        // init log entity
        $log = new Log();

        // set the log properties
        $log->setName($name)
            ->setMessage($message)
            ->setStatus('UNREADED')
            ->setUserAgent($userAgent)
            ->setIpAdderss($ipAddress)
            ->setTime(new \DateTime())
            ->setLevel($level);

            // set user id if user logged in
            $userId = $this->sessionUtil->getSessionValue('user-identifier', 0);
        if (is_numeric($userId)) {
            $log->setUserId((int) $userId);
        } else {
            $log->setUserId(0);
        }

        try {
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'log-error: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Set the anti-log cookie
     *
     * @return void
     */
    public function setAntiLog(): void
    {
        // log action
        $this->log('anti-log', 'anti-log enabled', self::LEVEL_WARNING);

        // set the anti-log cookie
        $this->cookieUtil->set(
            name: 'anti-log',
            value: $this->appUtil->getAntiLogToken(),
            expiration: time() + (60 * 60 * 24 * 7 * 365)
        );
    }

    /**
     * Unset the anti-log cookie
     *
     * @return void
     */
    public function unSetAntiLog(): void
    {
        // log action
        $this->log('anti-log', 'anti-log disabled', self::LEVEL_WARNING);

        // unset the anti-log cookie
        $this->cookieUtil->unset('anti-log');
    }

    /**
     * Check if anti-log is enabled
     *
     * @return bool True if anti-log is enabled, false otherwise
     */
    public function isAntiLogEnabled(): bool
    {
        // check if anti-log is seted
        if (!$this->cookieUtil->isCookieSet('anti-log')) {
            return false;
        }

        // get anti-log token from cookie
        $cookieToken = $this->cookieUtil->get('anti-log');

        // check if anti-log token is valid
        if ($cookieToken == $this->appUtil->getAntiLogToken()) {
            return true;
        }

        return false;
    }

    /**
     * Get the count of logs based on their status
     *
     * This method retrieves the count of logs from the repository based on the specified status
     * If the status is 'all', it counts all logs. Otherwise, it counts logs
     * matching the given status.
     *
     * @param string $status The status of the logs to count. Defaults to 'all'
     * @param int $userId The user id for get all count logs
     *
     * @return int The count of logs
     */
    public function getLogsCountWhereStatus(string $status = 'all', int $userId = 0): int
    {
        $repository = $this->entityManager->getRepository(Log::class);

        // get all logs or by status
        if ($status == 'all') {
            if ($userId != 0) {
                $count = $repository->count(['user_id' => $userId]);
            } else {
                $count = $repository->count();
            }
        } else {
            $count = $repository->count(['status' => $status]);
        }

        // return logs count
        return $count;
    }

    /**
     * Get the count of auth logs
     *
     * @return int The count of auth logs
     */
    public function getAuthLogsCount(): int
    {
        $repository = $this->entityManager->getRepository(Log::class);

        return $repository->count(
            ['name' => 'authenticator', 'status' => 'UNREADED']
        );
    }

    /**
     * Fetch logs based on their status
     *
     * This method retrieves logs from the repository based on the specified status
     * If the status is 'all', it retrieves all logs. Otherwise, it fetches logs
     * matching the given status. It also logs this action.
     *
     * @param string $status The status of the logs to retrieve. Defaults to 'all'
     * @param int $userId The user id for get all logs
     * @param int $page The logs list page number
     *
     * @return array<mixed>|null An array of logs if found, or null if no logs are found
     */
    public function getLogsWhereStatus(string $status = 'all', int $userId = 0, int $page = 1): ?array
    {
        // get page limitter
        $perPage = $this->appUtil->getPageLimiter();

        $repository = $this->entityManager->getRepository(Log::class);

        // calculate offset
        $offset = ($page - 1) * $perPage;

        // get all logs or by status
        if ($status == 'all') {
            if ($userId != 0) {
                $logs = $repository->findBy(['user_id' => $userId], null, $perPage, $offset);
            } else {
                $logs = $repository->findBy([], null, $perPage, $offset);
            }
        } else {
            $logs = $repository->findBy(['status' => $status], ['id' => 'DESC'], $perPage, $offset);
        }

        // log action
        $this->log('log-manager', strtolower($status) . ' logs viewed', self::LEVEL_NOTICE);

        return $logs;
    }

    /**
     * Get monitoring logs
     *
     * @param int $limit The limit of logs to get
     *
     * @return array<mixed>|null The monitoring logs
     */
    public function getMonitoringLogs(int $limit): ?array
    {
        $repository = $this->entityManager->getRepository(Log::class);

        return $repository->findBy(['name' => 'monitoring'], ['id' => 'DESC'], $limit);
    }

    /**
     * Update the status of a log entry by its ID
     *
     * This method retrieves a log entry by its ID, updates its status to the specified new status
     * and persists the change to the database. If the log entry is not found, it handles the error
     *
     * @param int $id The ID of the log entry to update
     * @param string $newStatus The new status to set for the log entry
     *
     * @return void
     *
     * @throws \Exception If there is an error during the update process
     */
    public function updateLogStatusById(int $id, string $newStatus): void
    {
        $repository = $this->entityManager->getRepository(Log::class);

        /** @var \App\Entity\Log $log */
        $log = $repository->find($id);

        // check if log found
        if (!$log) {
            $this->errorManager->handleError(
                message: 'log status update error: log id: ' . $id . ' not found',
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // update status
        try {
            // update status
            $log->setStatus($newStatus);

            // flush data to database
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to update log status: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Set all logs with status 'UNREADED' to 'READED'
     *
     * This method fetches logs with status 'UNREADED' using getLogsWhereStatus()
     * updates their status to 'READED', and flushes the changes to the database
     *
     * @throws \Exception If there is an error while updating the log statuses
     *
     * @return void
     */
    public function setAllLogsToReaded(): void
    {
        $repository = $this->entityManager->getRepository(Log::class);

        /** @var \App\Entity\Log $logs */
        $logs = $repository->findBy(['status' => 'UNREADED']);

        if (is_iterable($logs)) {
            // set all logs to readed status
            foreach ($logs as $log) {
                $log->setStatus('READED');
            }
        }

        try {
            // flush changes to the database
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to set all logs status to "READED": ' . $e,
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Retrieves a list of system log files
     *
     * * @throws \Exception If there is an error during file retrieval (e.g., file not found or permission issue)
     *
     * @return array<string> An array of relative pathnames of log files found in the /var/log directory
     */
    public function getSystemLogs(): array
    {
        // system logs directory
        $systemLogsDirectory = $this->appUtil->getSystemLogsDirectory();

        // set permissions to 777 for system logs directory
        shell_exec('sudo chmod -R 777 ' . $systemLogsDirectory);

        // initialize Finder
        $finder = new Finder();

        // array to store log files
        $logFiles = [];

        try {
            $finder->files()->in($systemLogsDirectory);
            // iterate over found files
            foreach ($finder as $file) {
                // check if log is not archived
                if (!str_ends_with($file->getRelativePathname(), '.xz')) {
                    $logFiles[] = $file->getRelativePathname();
                }
            }
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to get system logs: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // log action
        $this->log('log-manager', 'system logs viewed', self::LEVEL_NOTICE);

        // return log files
        return $logFiles;
    }

    /**
     * Retrieves the content of a specific system log file
     *
     * @param string $logFile The relative pathname of the log file to retrieve
     * @throws \Exception If there is an error during file retrieval (e.g., file not found or permission issue)
     *
     * @return mixed The content of the log file, or null if the file does not exist
     */
    public function getSystemLogContent(string $logFile): mixed
    {
        $log = null;

        // check if file exists
        $filePath = $this->appUtil->getSystemLogsDirectory() . '/' . $logFile;
        if (!file_exists($filePath)) {
            $this->errorManager->handleError(
                message: 'error to get log file: ' . $filePath . ' not found',
                code: Response::HTTP_NOT_FOUND
            );
        }

        try {
            // get log file content
            $log = file_get_contents($filePath);
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to get log file: ' . $logFile . ', ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // log action
        $this->log(
            name: 'log-manager',
            message: 'system log: ' . $logFile . ' viewed',
            level: self::LEVEL_NOTICE
        );

        // return log content
        return $log;
    }

    /**
     * Get exception files
     *
     * @return array<mixed>|null
     */
    public function getExceptionFiles(): ?array
    {
        $files = [];

        try {
            /** @var array<string,array<string,string>> $exceptionFiles list of exception files */
            $exceptionFiles = $this->appUtil->loadConfig('exception-files.json');

            if (!is_array($exceptionFiles)) {
                $this->errorManager->handleError(
                    message: 'error to get exception files: exception files config is not an array',
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
                return null;
            }

            // check if exception files exist and add them to the files array
            foreach ($exceptionFiles as $exceptionFile) {
                if (file_exists($exceptionFile['path'])) {
                    $files[$exceptionFile['name']] = $exceptionFile;
                }
            }
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to get exception files: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $files;
    }

    /**
     * Delete exception file
     *
     * @param string $exceptionFile The name of the exception file to delete
     *
     * @throws \Exception If there is an error during file deletion
     *
     * @return void
     */
    public function deleteExceptionFile(string $exceptionFile): void
    {
        /** @var array<string,array<string,string>> $exceptionFiles list of exception files */
        $exceptionFiles = $this->getExceptionFiles();

        try {
            // check if exception file exists
            if (isset($exceptionFiles[$exceptionFile])) {
                $exceptionFile = $exceptionFiles[$exceptionFile]['path'];

                // delete exception file
                if (file_exists($exceptionFile)) {
                    // unlink exception file
                    unlink($exceptionFile);

                    // log action
                    $this->log(
                        name: 'log-manager',
                        message: 'exception file deleted: ' . $exceptionFile,
                        level: self::LEVEL_CRITICAL
                    );
                }
            }
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to delete exception file: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
