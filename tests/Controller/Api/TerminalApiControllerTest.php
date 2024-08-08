<?php

namespace App\Tests\Controller\Api;

use App\Tests\CustomTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Class TerminalApiControllerTest
 *
 * This test verifies that the terminal API works correctly
 *
 * @package App\Tests\Controller\Api
 */
class TerminalApiControllerTest extends CustomTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->simulateLogin($this->client);
    }

    /**
     * Tests that the terminal API returns the expected response when the command is empty
     *
     * @return void
     */
    public function testExecuteTerminalCommandWithEmptyCommand(): void
    {
        $this->client->request('POST', '/api/system/terminal');

        // get response content
        $responseContent = $this->client->getResponse()->getContent();

        // check if response content is empty
        if (!$responseContent) {
            $this->fail('Response content is empty');
        }

        // assert response
        $this->assertSame('command data is empty!', $responseContent);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Tests that the terminal API returns the success response
     *
     * @return void
     */
    public function testExecuteTerminalCommandSuccessfully(): void
    {
        $this->client->request('POST', '/api/system/terminal', [
            'command' => 'ls'
        ]);

        // get response content
        $responseContent = $this->client->getResponse()->getContent();

        // check if response content is empty
        if (!$responseContent) {
            $this->fail('Response content is empty');
        }

        // assert response
        $this->assertNotSame('command data is empty!', $responseContent);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
