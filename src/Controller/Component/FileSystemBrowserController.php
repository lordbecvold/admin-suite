<?php

namespace App\Controller\Component;

use App\Manager\AuthManager;
use App\Util\FilesystemUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class FileSystemBrowserController
 *
 * This class is responsible for handling file system browser related operations
 *
 * @package App\Controller\Component
 */
class FileSystemBrowserController extends AbstractController
{
    private AuthManager $authManager;
    private FilesystemUtil $filesystemUtil;

    public function __construct(AuthManager $authManager, FilesystemUtil $filesystemUtil)
    {
        $this->authManager = $authManager;
        $this->filesystemUtil = $filesystemUtil;
    }

    /**
     * Returns a list of files and directories in the specified path
     *
     * @param Request $request The request object
     *
     * @return Response The file list view response
     */
    #[Route('/filesystem', methods:['GET'], name: 'app_file_system_browser')]
    public function filesystemList(Request $request): Response
    {
        // check if user has admin permissions
        if (!$this->authManager->isLoggedInUserAdmin()) {
            return $this->render('component/no-permissions.twig', [
                'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
                'userData' => $this->authManager->getLoggedUserRepository(),
            ]);
        }

        // get current page from request query params
        $path = (string) $request->query->get('path', '/');

        // get filesystem list
        $filesystemList = $this->filesystemUtil->getFilesList($path);

        // render the file browser list
        return $this->render('component/file-system/file-system-browser.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // file browser data
            'currentPath' => $path,
            'filesystemList' => $filesystemList
        ]);
    }

    /**
     * Returns the contents of a file
     *
     * @param Request $request The request object
     *
     * @return Response The file browser view response
     */
    #[Route('/filesystem/view', methods:['GET'], name: 'app_file_system_view')]
    public function filesystemView(Request $request): Response
    {
        // check if user has admin permissions
        if (!$this->authManager->isLoggedInUserAdmin()) {
            return $this->render('component/no-permissions.twig', [
                'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
                'userData' => $this->authManager->getLoggedUserRepository(),
            ]);
        }

        // get the browsing path
        $path = (string) $request->query->get('path', '/');

        // default file content value
        $fileContent = null;

        // check if file is executable
        if ($this->filesystemUtil->isFileExecutable($path)) {
            $fileContent = 'You cannot view the content of an binnary executable file';
        } else {
            // get file content
            $fileContent = $this->filesystemUtil->getFileContent($path);
        }

        // render the file browser view
        return $this->render('component/file-system/file-system-view.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // file browser data
            'currentPath' => $path,
            'fileContent' => $fileContent
        ]);
    }
}
