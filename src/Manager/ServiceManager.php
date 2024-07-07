<?php

namespace App\Manager;

/**
 * Class ServiceManager
 *
 * Service manager provides all services methods (start, stop, status, etc..)
 *
 * @package App\Manager
 */
class ServiceManager
{
    private LogManager $logManager;
    private AuthManager $authManager;
    private ErrorManager $errorManager;

    public function __construct(
        LogManager $logManager,
        AuthManager $authManager,
        ErrorManager $errorManager
    ) {
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
        $servicesList = null;

        try {
            // get services list from services.json
            $servicesFile = file_get_contents(__DIR__ . '/../../config/suite/services.json');

            if ($servicesFile) {
                // decode json
                $servicesList = (array) json_decode($servicesFile, true);
            }
        } catch (\Exception $e) {
            $this->errorManager->handleError('error to get services-list.json file: ' . $e->getMessage(), 500);
            ;
        }

        return $servicesList;
    }

    /**
     * Runs an action on a specified service
     *
     * @param string $serviceName The name of the service
     * @param string $action The action to run on the service
     *
     * @return void
     */
    public function runAction(string $serviceName, string $action): void
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
            $this->logManager->log('action-runner', $user->getUsername() . ' ' . $action . $serviceName, 1);

            // executed final command
            $this->executeCommand($command);
        } else {
            $this->errorManager->handleError('error action runner is only for authentificated users', 401);
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
            $this->errorManager->handleError('error to get service status: ' . $e->getMessage(), 500);
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
            $this->errorManager->handleError('error to check socket: ' . $e->getMessage(), 500);
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
            $this->errorManager->handleError('error to check process: ' . $e->getMessage(), 500);
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
            $this->errorManager->handleError('error to get ufw status' . $e->getMessage(), 500);
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
    public function executeCommand($command): void
    {
        try {
            exec($command);
        } catch (\Exception $e) {
            $this->errorManager->handleError('error to executed command: ' . $e->getMessage(), 500);
        }
    }
}
