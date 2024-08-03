<?php

namespace App\Util;

use SplFileInfo;
use App\Manager\LogManager;
use App\Manager\ErrorManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FilesystemUtil
 *
 * This class is responsible for handling filesystem related operations
 *
 * @package App\Util
 */
class FilesystemUtil
{
    private LogManager $logManager;
    private ErrorManager $errorManager;

    public function __construct(LogManager $logManager, ErrorManager $errorManager)
    {
        $this->logManager = $logManager;
        $this->errorManager = $errorManager;
    }

    /**
     * Returns a list of files and directories in the specified path
     *
     * @param string $path The path to list files and directories
     *
     * @return array<array<mixed>> The list of files and directories
     */
    public function getFilesList(string $path): array
    {
        $files = [];

        try {
            $finder = new \Symfony\Component\Finder\Finder();
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
                    'name' => str_replace('/', '', $file->getFilename()),
                    'size' => $file->getSize(),
                    'permissions' => substr(sprintf('%o', $file->getPerms()), -4),
                    'isDir' => $file->isDir(),
                    'path' => $file->getRealPath(),
                ];
            }
        } catch (\Exception $e) {
            // handle the error
            $this->errorManager->handleError(
                message: 'error listing files: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $files;
    }

    /**
     * Checks if the file is executable
     *
     * @param string $path The path to the file
     *
     * @return bool True if the file is executable, false otherwise
     */
    public function isFileExecutable(string $path): bool
    {
        // check file exists
        if (!file_exists($path)) {
            return false;
        }

        // check if path is directory
        if (is_dir($path) || is_link($path)) {
            return false;
        }

        // get file mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        // error to open finfo
        if (!$finfo) {
            // handle the error
            $this->errorManager->handleError(
                message: 'error opening finfo list: ' . $path . ' mime type detection failed',
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );

            return false;
        }

        // get the file mime type
        $mimeType = finfo_file($finfo, $path);

        // close the finfo
        finfo_close($finfo);

        // check mime type is set
        if (!$mimeType) {
            // handle the error
            $this->errorManager->handleError(
                message: 'error get file mime type: ' . $path . ' mime type detection failed',
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
            return false;
        }

        // check if the file type is supported
        if (strpos($mimeType, 'executable')) {
            return true;
        }

        return false;
    }

    /**
     * Returns the contents of a file
     *
     * @param string $path The path to the file
     *
     * @return string|null The file content or null if the file does not exist
     */
    public function getFileContent(string $path): ?string
    {
        // check file exists
        if (!file_exists($path)) {
            // handle the error
            $this->errorManager->handleError(
                message: 'error opening file: ' . $path . ' does not exist',
                code: Response::HTTP_NOT_FOUND
            );

            return null;
        }

        try {
            // check if path is directory
            if (is_dir($path) || is_link($path)) {
                // handle the error
                $this->errorManager->handleError(
                    message: 'error opening file: ' . $path . ' is a directory or a link',
                    code: Response::HTTP_BAD_REQUEST
                );
                return null;
            }

            // get the file content
            $fileContent = file_get_contents($path);

            // check file content is set
            if (!$fileContent) {
                // handle the error
                $this->errorManager->handleError(
                    message: 'error opening file: ' . $path,
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
                return null;
            }

            // log file access
            $this->logManager->log(
                name: 'file-browser',
                message: 'file ' . $path . ' accessed',
                level: 3
            );

            // return the file content
            return $fileContent;
        } catch (\Exception $e) {
            // handle the error
            $this->errorManager->handleError(
                message: 'error opening file: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return null;
    }
}
