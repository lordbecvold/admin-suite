<?php

namespace App\Tests\Controller\Component;

use App\Tests\CustomTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Class FileSystemBrowserControllerTest
 *
 * Test for file system browser controller
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
     * Test load file system list page
     *
     * @return void
     */
    public function testLoadFileSystemBrowserPage(): void
    {
        $this->client->request('GET', '/filesystem');

        // assert response
        $this->assertSelectorTextContains('title', 'Admin suite');
        $this->assertSelectorTextContains('body', 'Filesystem');
        $this->assertSelectorTextContains('body', 'Name');
        $this->assertSelectorTextContains('body', 'Size');
        $this->assertSelectorTextContains('body', 'Permissions');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Test load file system view page
     *
     * @return void
     */
    public function testLoadFileSystemViewPage(): void
    {
        $this->client->request('GET', '/filesystem/view?path=/usr/lib/os-release');

        // assert response
        $this->assertSelectorTextContains('title', 'Admin suite');
        $this->assertSelectorTextContains('body', 'Filesystem');
        $this->assertSelectorTextContains('body', 'os-release');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
