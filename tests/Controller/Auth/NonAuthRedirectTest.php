<?php

namespace App\Tests\Controller\Auth;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class NonAuthRedirectTest
 *
 * Non-auth redirect authenticator test.
 * Test all admin routes in the default state when the user is not logged in
 *
 * @package App\Tests\Controller\Auth
 */
class NonAuthRedirectTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        parent::setUp();
    }

    /**
     * Auth required routes list
     *
     * @return array<array<string>>
     */
    public function provideAdminUrls(): array
    {
        return [
            ['/admin'],
            ['/dashboard']
        ];
    }

    /**
     * Test non-authenticated admin redirect
     *
     * @dataProvider provideAdminUrls
     *
     * @param string $url The admin route URL
     *
     * @return void
     */
    public function testNonAuthAdminRedirect(string $url): void
    {
        $this->client->request('GET', $url);

        // assert
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertTrue($this->client->getResponse()->isRedirect('/login'));
    }
}