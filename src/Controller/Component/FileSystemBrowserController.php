<?php

namespace App\Controller\Component;

use SplFileInfo;
use App\Manager\LogManager;
use App\Manager\AuthManager;
use App\Manager\ErrorManager;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class FileSystemBrowserController
 *
 * This controller is responsible for handling file system browser requests
 *
 * @package App\Controller\Component
 */
class FileSystemBrowserController extends AbstractController
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
     * Renders the file system browser
     *
     * @param Request $request The request object
     *
     * @return Response The fielsystem browser view response
     */
    #[Route('/filesystem/browser', methods: ['GET'], name: 'app_file_system_browser')]
    public function fileSystemManager(Request $request): Response
    {
        // check user permissions
        if (!$this->authManager->isLoggedInUserAdmin()) {
            return $this->render('component/no-permissions.twig', [
                'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
                'userData' => $this->authManager->getLoggedUserRepository(),
            ]);
        }

        // get the browsing path
        $path = (string) $request->query->get('path', '/');

        // render the file browser view
        return $this->render('component/file-system/file-system.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // filesystem browser data
            'currentPath' => $path,
        ]);
    }

    /**
     * Returns a list of files and directories in the specified path
     *
     * @param Request $request The request object
     *
     * @return JsonResponse The file list as json
     */
    #[Route('/filesystem/api/list', methods: ['GET'], name: 'app_file_system_list')]
    public function listFileSystem(Request $request): JsonResponse
    {
        // check user permissions
        if (!$this->authManager->isLoggedInUserAdmin()) {
            return $this->json([
                'error' => 'you are not allowed to access this page'
            ], Response::HTTP_FORBIDDEN);
        }

        // get the browsing path
        $path = (string) $request->query->get('path', '/');

        try {
            $files = [];
            $finder = new Finder();
            $finder->in($path)->depth('== 0');

            // get file list as array
            $list = iterator_to_array($finder, false);

            // sort file list
            usort($list, function (SplFileInfo $a, SplFileInfo $b) {
                // sort directories first
                if ($a->isDir() && !$b->isDir()) {
                    return -1;
                } elseif (!$a->isDir() && $b->isDir()) {
                    return 1;
                }

                // sort by filename
                return strcasecmp($a->getFilename(), $b->getFilename());
            });

            // loop through the files and directories
            foreach ($list as $file) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'permissions' => substr(sprintf('%o', $file->getPerms()), -4),
                    'owner' => $file->getOwner(),
                    'is_dir' => $file->isDir(),
                    'path' => $file->getRealPath(),
                ];
            }
        } catch (\Exception $e) {
            // handle the error
            $this->errorManager->handleError(
                message: 'error listing files: ' . $e->getMessage(),
                code: Response::HTTP_BAD_REQUEST
            );

            // return a 400 response
            return $this->json([
                'error' => 'error listing files'
            ], Response::HTTP_BAD_REQUEST);
        }

        // return the file list as json
        return $this->json($files, Response::HTTP_OK);
    }

    /**
     * Returns the details of a file
     *
     * @param Request $request The request object
     *
     * @return JsonResponse The file details as json
     */
    #[Route('/filesystem/api/detail', methods: ['GET'], name: 'app_file_system_detail')]
    public function fileDetail(Request $request): JsonResponse
    {
        // check user permissions
        if (!$this->authManager->isLoggedInUserAdmin()) {
            return $this->json([
                'error' => 'you are not allowed to access this page'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            // get the file path
            $path = (string) $request->query->get('path');

            // check if path is empty
            if (empty($path)) {
                // return a 400 response
                return $this->json([
                    'error' => 'path parameter is empty'
                ], Response::HTTP_BAD_REQUEST);
            }

            // set the maximum file to open size to 30 MB
            $maxFileSize = 30 * 1024 * 1024;

            // check if the file exists
            if (!file_exists($path)) {
                // return a 404 response
                return $this->json([
                    'error' => 'file not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // get the file size
            $fileSize = filesize($path);

            // check if the file size is too large
            if ($fileSize > $maxFileSize) {
                // return a 400 response
                return $this->json([
                    'error' => 'file is too large to be opened'
                ], Response::HTTP_BAD_REQUEST);
            }

            // get the file mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            // check if the file info object is valid
            if (!$finfo) {
                // return a 400 response
                return $this->json([
                    'error' => 'error opening file'
                ], Response::HTTP_BAD_REQUEST);
            }

            $mimeType = (string) finfo_file($finfo, $path);
            finfo_close($finfo);

            // check if the file type is supported
            if (strpos($mimeType, 'executable')) {
                // return a 400 response
                return $this->json([
                    'error' => 'unsupported file type'
                ], Response::HTTP_BAD_REQUEST);
            }

            // get the file content
            $fileInfo = [
                'name' => basename($path),
                'size' => $fileSize,
                'permissions' => substr(sprintf('%o', fileperms($path)), -4),
                'content' => file_get_contents($path),
            ];
        } catch (\Exception $e) {
            // handle the error
            $this->errorManager->handleError(
                message: 'error opening file: ' . $e->getMessage(),
                code: Response::HTTP_BAD_REQUEST
            );

            // return a 400 response
            return $this->json([
                'error' => 'error opening file'
            ], Response::HTTP_BAD_REQUEST);
        }

        // log file access
        $this->logManager->log(
            name: 'file-browser',
            message: 'file ' . $path . ' accessed',
            level: 3
        );

        return $this->json($fileInfo, Response::HTTP_OK);
    }
}
