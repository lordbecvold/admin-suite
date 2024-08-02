<?php

namespace App\Tests\Controller\Auth;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class NonAuthRedirectTest
 *
 * Non-auth redirect authenticator test
 * Test all admin routes in the default state when the user is not logged in
 *
 * @package App\Tests\Controller\Auth
 */
class NonAuthRedirectTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * Auth required routes list
     *
     * @return array<array<string>>
     */
    private const ROUTES = [
        'admin_dashboard' => [
            '/admin',
            '/dashboard'
        ],
        'anti_log' => [
            '/13378/antilog'
        ],
        'user_manager' => [
            '/manager/users',
            '/manager/users/ban',
            '/manager/users/delete',
            '/manager/users/register',
            '/manager/users/role/update'
        ],
        'account_settings' => [
            '/account/settings',
            '/manager/users/profile',
            '/account/settings/change/picture',
            '/account/settings/change/username',
            '/account/settings/change/password'
        ],
        'logs_manager' => [
            '/manager/logs',
            '/manager/logs/system',
            '/manager/logs/set/readed',
            '/manager/logs/exception/files'
        ],
        'diagnostic' => [
            '/diagnostic'
        ],
        'action_runner' => [
            '/service/action/runner'
        ],
        'monitoring_manager' => [
            '/manager/monitoring',
            '/manager/monitoring/config'
        ],
        'todo_manager' => [
            '/manager/todo',
            '/manager/todo/edit',
            '/manager/todo/close',
            '/manager/todo/delete'
        ],
        'database_manager' => [
            '/manager/database',
            '/manager/database/add',
            '/manager/database/edit',
            '/manager/database/dump',
            '/manager/database/table',
            '/manager/database/delete',
            '/manager/database/console',
            '/manager/database/truncate'
        ],
        'file_system_browser' => [
            '/filesystem/browser',
            '/filesystem/api/list',
            '/filesystem/api/detail'
        ]
    ];

    /**
     * Auth required routes list
     *
     * @return array<array<string>>
     */
    protected function provideAdminUrls(): array
    {
        $urls = [];
        foreach (self::ROUTES as $category => $routes) {
            foreach ($routes as $route) {
                $urls[] = [$route];
            }
        }
        return $urls;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
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

        // assert response
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertTrue($this->client->getResponse()->isRedirect('/login'));
    }
}
