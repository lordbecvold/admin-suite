<?php

namespace App\Tests\Controller\Component;

use App\Tests\CustomTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Class TerminalApiControllerTest
 *
 * This test verifies that the terminal page loads correctly and displays the expected content
 *
 * @package App\Tests\Controller\Component
 */
class TerminalControllerTest extends CustomTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // simulate user authentication
        $this->simulateLogin($this->client);
    }

    /**
     * Tests that the terminal page loads successfully
     *
     * @return void
     */
    public function testLoadTerminalPage(): void
    {
        $this->client->request('GET', '/terminal');

        // assert response
        $this->assertSelectorTextContains('title', 'Admin suite');
        $this->assertSelectorTextContains('body', 'Terminal');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
