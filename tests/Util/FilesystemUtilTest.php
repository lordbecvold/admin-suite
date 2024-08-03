<?php

namespace App\Tests\Util;

use App\Manager\LogManager;
use App\Util\FilesystemUtil;
use App\Manager\ErrorManager;
use PHPUnit\Framework\TestCase;

/**
 * Class FilesystemUtilTest
 *
 * This class tests the FilesystemUtil class
 *
 * @package App\Tests\Util
 */
class FilesystemUtilTest extends TestCase
{
    /** @var LogManager */
    private LogManager $logManager;

    /** @var ErrorManager */
    private ErrorManager $errorManager;

    /** @var FilesystemUtil */
    private FilesystemUtil $filesystemUtil;

    protected function setUp(): void
    {
        $this->logManager = $this->createMock(LogManager::class);
        $this->errorManager = $this->createMock(ErrorManager::class);
        $this->filesystemUtil = new FilesystemUtil($this->logManager, $this->errorManager);
    }

    /**
     * Test the getFilesList method
     *
     * @return void
     */
    public function testGetFilesListSuccess(): void
    {
        $list = $this->filesystemUtil->getFilesList('/');

        // check result array
        $this->assertIsArray($list);
        $this->assertArrayHasKey('name', $list[0]);
        $this->assertArrayHasKey('size', $list[0]);
        $this->assertArrayHasKey('permissions', $list[0]);
        $this->assertArrayHasKey('isDir', $list[0]);
        $this->assertArrayHasKey('path', $list[0]);
    }

    /**
     * Test the isFileExecutable method
     *
     * @return void
     */
    public function testIsFileExecutableSuccess(): void
    {
        $result = $this->filesystemUtil->isFileExecutable('/var/www/balbla.txt');

        // assert result is bool
        $this->assertIsBool($result);
    }

    /**
     * Test the getFileContent method
     *
     * @return void
     */
    public function testGetFileContentSuccess(): void
    {
        $result = $this->filesystemUtil->getFileContent('/usr/lib/os-release');

        // assert result is string
        $this->assertIsString($result);
    }
}
