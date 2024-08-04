<?php

namespace App\Manager;

use App\Util\AppUtil;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ServiceManager
 *
 * Service manager provides all services methods (start, stop, status, etc..)
 *
 * @package App\Manager
 */
class ServiceManager
{
    private AppUtil $appUtil;
    private LogManager $logManager;
    private AuthManager $authManager;
    private ErrorManager $errorManager;

    public function __construct(
        AppUtil $appUtil,
        LogManager $logManager,
        AuthManager $authManager,
        ErrorManager $errorManager
    ) {
        $this->appUtil = $appUtil;
        $this->logManager = $logManager;
        $this->authManager = $authManager;
        $this->errorManager = $errorManager;
    }

    /**
     * Gets the services list from the services.json file
     *
     * @return array<mixed>>|null The services list, null
     */
    public function getServicesList(): ?array
    {
        return $this->appUtil->loadConfig('services.json');
    }

    /**
     * Runs systemd action on a specified service
     *
     * @param string $serviceName The name of the service
     * @param string $action The action to run on the service
     *
     * @return void
     */
    public function runSystemdAction(string $serviceName, string $action): void
    {
        // check if user logged in
        if ($this->authManager->isUserLogedin()) {
            $command = null;

            // check if action is related to ufw
            if ($serviceName == 'ufw') {
                $command = 'sudo ufw ' . $action;
            } else {
                // build action
                $command = 'sudo systemctl ' . $action . ' ' . $serviceName;
            }

            /** @var \App\Entity\User $user logged user */
            $user = $this->authManager->getLoggedUserRepository();

            // log action
            $this->logManager->log(
                name: 'action-runner',
                message: $user->getUsername() . ' ' . $action . ' ' . $serviceName,
                level: LogManager::LEVEL_WARNING
            );

            // executed final command
            $this->executeCommand($command);
        } else {
            $this->errorManager->handleError(
                'error action runner is only for authentificated users',
                Response::HTTP_UNAUTHORIZED
            );
        }
    }

    /**
     * Checks if a service is running
     *
     * @param string $service The name of the service
     *
     * @return bool The service is running, false otherwise
     */
    public function isServiceRunning(string $service): bool
    {
        try {
            $output = shell_exec('systemctl is-active ' . $service);
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                'error to get service status: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
            return false;
        }

        if ($output == null) {
            return false;
        }

        // check if service running
        if (trim($output) == 'active') {
            return true;
        }

        return false;
    }

    /**
     * Checks if a socket is open
     *
     * @param string $ip The IP address
     * @param int $port The port number
     * @param int $timeout The maximal timeout in seconds
     *
     * @return string Online if the socket is open, Offline otherwise
     */
    public function isSocktOpen(string $ip, int $port, int $timeout = 5): string
    {
        $status = 'Offline';
        $service = null;

        // open service socket
        try {
            $service = @fsockopen($ip, $port, timeout: $timeout);
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                'error to check socket: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // check if service is not null
        if ($service != null) {
            // check is service online
            if ($service >= 1) {
                $status = 'Online';
            }
        }

        return $status;
    }

    /**
     * Checks if a process is running
     *
     * @param string $process The name of the process
     *
     * @return bool The process is running, false otherwise
     */
    public function isProcessRunning(string $process): bool
    {
        try {
            exec('pgrep ' . $process, $pids);
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                'error to check process: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
            return false;
        }

        // check if outputed pid
        if (empty($pids)) {
            return false;
        }

        return true;
    }

    /**
     * Checks if UFW (Uncomplicated Firewall) is running
     *
     * @return bool UFW is running, false otherwise
     */
    public function isUfwRunning(): bool
    {
        try {
            // execute cmd
            $output = shell_exec('sudo ufw status');

            // check if output is string value
            if (is_string($output)) {
                // check if ufw running
                if (str_starts_with($output, 'Status: active')) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                'error to get ufw status' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return false;
    }

    /**
     * Checks if the services list file exists
     *
     * @return bool The services list file exists, false otherwise
     */
    public function isServicesListExist(): bool
    {
        // check if services list exist
        if ($this->getServicesList() != null) {
            return true;
        }

        return false;
    }

    /**
     * Executes a command
     *
     * @param string $command The command to execute
     *
     * @return void
     */
    public function executeCommand(string $command): void
    {
        try {
            exec($command);
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                'error to executed command: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Checks if a website is online
     *
     * @param string $url The URL of the website
     *
     * @return array<mixed> The results of the check (keys: isOnline, responseTime, responseCode)
     */
    public function checkWebsiteStatus(string $url): array
    {
        // initialize cURL session
        $ch = curl_init($url);

        if ($ch == false) {
            $this->errorManager->handleError(
                message: 'error to check website status: ' . $url,
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
            return ['isOnline' => false, 'responseTime' => 0, 'responseCode' => 0];
        }

        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // start timing
        $start = microtime(true);

        // execute cURL session
        curl_exec($ch);

        // get the response code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // end timing
        $end = microtime(true);
        $responseTime = round(($end - $start) * 1000);

        // close cURL session
        curl_close($ch);

        // determine if the site is online
        $isOnline = ($httpCode >= Response::HTTP_OK);

        // return an array with the results
        return [
            'isOnline' => $isOnline,
            'responseTime' => $responseTime,
            'responseCode' => $httpCode
        ];
    }
}
