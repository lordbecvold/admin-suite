<?php

namespace App\Tests\Controller\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class LogApiControllerTest
 *
 * Test the external log API
 *
 * @package App\Tests\Controller
 */
class LogApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Test the external log without token
     *
     * @return void
     */
    public function testExternalLogWithoutToken(): void
    {
        $this->client->request('GET', '/api/external/log');

        // get response content
        $responseContent = $this->client->getResponse()->getContent();

        // check if response content is empty
        if (!$responseContent) {
            $this->fail('Response content is empty');
        }

        /** @var array<string> $responseData */
        $responseData = json_decode($responseContent, true);

        // assert response
        $this->assertEquals('Access token is not set', $responseData['error']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test the external log with invalid token
     *
     * @return void
     */
    public function testExternalLogWithInvalidToken(): void
    {
        $this->client->request('GET', '/api/external/log', [
            'token' => 'invalid'
        ]);

        // get response content
        $responseContent = $this->client->getResponse()->getContent();

        // check if response content is empty
        if (!$responseContent) {
            $this->fail('Response content is empty');
        }

        /** @var array<string> $responseData */
        $responseData = json_decode($responseContent, true);

        // assert response
        $this->assertEquals('Access token is invalid', $responseData['error']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test the external log without parameters
     *
     * @return void
     */
    public function testExternalLogWithoutParameters(): void
    {
        $this->client->request('GET', '/api/external/log', [
            'token' => $_ENV['EXTERNAL_API_LOG_TOKEN']
        ]);

        // get response content
        $responseContent = $this->client->getResponse()->getContent();

        // check if response content is empty
        if (!$responseContent) {
            $this->fail('Response content is empty');
        }

        /** @var array<string> $responseData */
        $responseData = json_decode($responseContent, true);

        // assert response
        $this->assertEquals('Parameters name, message and level are required', $responseData['error']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test the external log with valid parameters
     *
     * @return void
     */
    public function testExternalLogWithValidParameters(): void
    {
        $this->client->request('GET', '/api/external/log', [
            'token' => $_ENV['EXTERNAL_API_LOG_TOKEN'],
            'name' => 'external-log',
            'message' => 'test message',
            'level' => 1
        ]);

        // get response content
        $responseContent = $this->client->getResponse()->getContent();

        // check if response content is empty
        if (!$responseContent) {
            $this->fail('Response content is empty');
        }

        /** @var array<string> $responseData */
        $responseData = json_decode($responseContent, true);

        // assert response
        $this->assertEquals('Log message has been logged', $responseData['success']);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
