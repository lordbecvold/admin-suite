<?php

namespace App\Controller\Api;

use App\Util\AppUtil;
use App\Util\SessionUtil;
use App\Util\SecurityUtil;
use App\Manager\LogManager;
use App\Manager\AuthManager;
use App\Annotation\Authorization;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class TerminalApiController
 *
 * This controller provides API functions for executing terminal commands
 *
 * @package App\Controller\Api
 */
class TerminalApiController extends AbstractController
{
    private AppUtil $appUtil;
    private LogManager $logManager;
    private SessionUtil $sessionUtil;
    private AuthManager $authManager;
    private SecurityUtil $securityUtil;

    public function __construct(
        AppUtil $appUtil,
        LogManager $logManager,
        AuthManager $authManager,
        SessionUtil $sessionUtil,
        SecurityUtil $securityUtil,
    ) {
        $this->appUtil = $appUtil;
        $this->logManager = $logManager;
        $this->sessionUtil = $sessionUtil;
        $this->authManager = $authManager;
        $this->securityUtil = $securityUtil;
    }

    /**
     * Execute terminal command
     *
     * @param Request $request The request object
     *
     * @return Response Command output response
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/api/system/terminal', methods: ['POST'], name: 'api_terminal')]
    public function terminalAction(Request $request): Response
    {
        // get command executor username
        $username = $this->authManager->getLoggedUsername();

        // set default working dir
        if ($this->sessionUtil->checkSession('terminal-dir')) {
            /** @var string $currentDir get curret working directory */
            $currentDir = $this->sessionUtil->getSessionValue('terminal-dir');

            // check if directory exist
            if (!file_exists($currentDir)) {
                chdir('/');
            } else {
                chdir($currentDir);
            }
        } else {
            chdir('/');
        }

        // get executed command
        $command = (string) $request->request->get('command');

        // check if command empty
        if (empty($command)) {
            return new Response('command data is empty!', Response::HTTP_OK);
        }

        // escape command
        $command = $this->securityUtil->escapeString($command);

        // load terminal config files
        $blockedCommands = $this->appUtil->loadConfig('terminal-blocked-commands.json');
        $aliases = $this->appUtil->loadConfig('terminal-aliases.json');

        // check if config files are iterable
        if (!is_iterable($blockedCommands) || !is_iterable($aliases)) {
            return new Response('error to load terminal config files', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // check if command is blocked
        foreach ($blockedCommands as $blockedCommand) {
            /** @var string $blockedCommand */
            if (str_starts_with($command ?? '', $blockedCommand)) {
                return new Response('command: ' . $command . ' is not allowed!', Response::HTTP_OK);
            }
        }

        // replace aliases with runnable command
        foreach ($aliases as $index => $value) {
            if ($command == $index) {
                /** @var string $command */
                $command = $value;
            }
        }

        // check if command is empty
        if (!$command) {
            return new Response('command data is empty!', Response::HTTP_OK);
        }

        // get cwd (system get)
        if ($command == 'get_current_path_1181517815187484') {
            // get cwd
            $cwd = getcwd();

            // check is cwd get success
            if (!$cwd) {
                return new Response('error to get current working directory', Response::HTTP_OK);
            } else {
                return new Response($cwd, Response::HTTP_OK);
            }
        }

        // update cwd (system get)
        if (str_starts_with($command, 'cd ')) {
            $newDir = str_replace('cd ', '', $command);

            // check if dir is / root dir
            if (!str_starts_with($newDir, '/')) {
                $finalDir = getcwd() . '/' . $newDir;
            } else {
                $finalDir = $newDir;
            }

            // check if directory exists
            if (file_exists($finalDir)) {
                $this->sessionUtil->setSession('terminal-dir', $finalDir);
                return new Response('', Response::HTTP_OK);
            } else {
                return new Response('error directory: ' . $finalDir . ' not found', Response::HTTP_OK);
            }
        } else {
            // execute command
            exec('sudo ' . $command, $output, $returnCode);

            // check if command run valid
            if ($returnCode !== 0) {
                $this->logManager->log('terminal', $username . ' executed command: ' . $command . ' with error code: ' . $returnCode);
                return new Response('error to execute command: ' . $command, Response::HTTP_OK);
            }

            // log execute action
            $this->logManager->log('terminal', $username . ' executed command: ' . $command);

            // get output
            $output = implode("\n", $output);

            // escape output
            $output = $this->securityUtil->escapeString($output);

            // return output
            return new Response($output, Response::HTTP_OK);
        }
    }
}
