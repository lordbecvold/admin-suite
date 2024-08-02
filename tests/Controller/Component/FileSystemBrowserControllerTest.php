<?php

namespace App\Tests\Controller\Component;

use App\Tests\CustomTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Class FileSystemBrowserControllerTest
 *
 * This test verifies that the file system browser page loads correctly and displays the expected content
 *
 * @package App\Tests\Controller\Component
 */
class FileSystemBrowserControllerTest extends CustomTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // simulate user authentication
        $this->simulateLogin($this->client);
    }

    /**
     * Tests that the file system browser page loads successfully and contains the expected content
     *
     * @return void
     */
    public function testLoadFileSystemBrowserPage(): void
    {
        $this->client->request('GET', '/filesystem/browser');

        // assert response
        $this->assertSelectorTextContains('title', 'Admin suite');
        $this->assertSelectorTextContains('body', 'Filesystem');
        $this->assertSelectorTextContains('body', 'Path:');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Tests that the file system browser API returns a list of files and directories
     *
     * @return void
     */
    public function testLoadFilesList(): void
    {
        $this->client->request('GET', '/filesystem/api/list');

        // assert response
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Tests that the file system browser API returns a 400 response when the path parameter is empty
     *
     * @return void
     */
    public function testLoadFileDetailsEmptyPath(): void
    {
        $this->client->request('GET', '/filesystem/api/detail');

        // assert response
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
