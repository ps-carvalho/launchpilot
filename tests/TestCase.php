<?php

declare(strict_types=1);

namespace Tests;

use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Core\Application;
use Marko\Core\Container\ContainerInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static ?Application $app = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$app === null) {
            $this->setTestEnv();
            self::$app = Application::boot(dirname(__DIR__));
            // Replace session and auth with fakes for testing
            $fakeSession = new FakeSession();
            self::$app->container->instance(\Marko\Session\Contracts\SessionInterface::class, $fakeSession);
            self::$app->container->instance(\Marko\Authentication\AuthManager::class, new FakeAuthManager());

            // Re-create Inertia singleton with fake session
            $config = self::$app->container->get(\Marko\Config\ConfigRepositoryInterface::class);
            $vite = self::$app->container->get(\Marko\Vite\Vite::class);
            $ssrClient = self::$app->container->get(\Marko\Inertia\Ssr\SsrClient::class);
            self::$app->container->instance(\Marko\Inertia\Inertia::class, new \Marko\Inertia\Inertia($config, $vite, $ssrClient, $fakeSession));
        }

        $this->freshDatabase();

        // Clear workspace auth cache between tests
        try {
            $this->container()->get(\App\Dashboard\Authorization\WorkspaceAuthorization::class)->clearCache();
        } catch (\Throwable) {
            // Ignore if not available
        }
    }

    protected function loginAsUser(int $userId): void
    {
        $auth = $this->container()->get(\Marko\Authentication\AuthManager::class);
        if ($auth instanceof FakeAuthManager) {
            $auth->setUserId($userId);
        }
    }

    protected function tearDown(): void
    {
        // Unregister Marko's error handler to prevent PHPUnit warnings
        if (self::$app !== null) {
            try {
                $handler = $this->container()->get(\Marko\Errors\Contracts\ErrorHandlerInterface::class);
                if (method_exists($handler, 'unregister')) {
                    $handler->unregister();
                }
            } catch (\Throwable) {
                // Ignore if handler isn't available
            }

            try {
                $auth = $this->container()->get(\Marko\Authentication\AuthManager::class);
                if ($auth instanceof FakeAuthManager) {
                    $auth->setUserId(0);
                }
            } catch (\Throwable) {
                // Ignore
            }
        }

        parent::tearDown();
    }

    protected function setTestEnv(): void
    {
        putenv('APP_ENV=testing');
        putenv('APP_DEBUG=true');
        putenv('DB_CONNECTION=pgsql');
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=' . (getenv('DB_PORT') ?: '5432'));
        putenv('DB_DATABASE=launchpilot_test');
        putenv('DB_USERNAME=launchpilot');
        putenv('DB_PASSWORD=launchpilot');
        putenv('VITE_USE_DEV_SERVER=false');
        putenv('INERTIA_SSR_ENABLED=false');
        putenv('SESSION_DRIVER=file');
    }

    protected function freshDatabase(): void
    {
        $qb = $this->query()->create();
        $tables = [
            'content_items',
            'agent_sessions',
            'knowledge_chunks',
            'knowledge_documents',
            'campaigns',
            'user_settings',
            'workspace_user',
            'workspaces',
            'users',
            'jobs',
            'failed_jobs',
        ];

        foreach ($tables as $table) {
            $qb->raw("TRUNCATE TABLE {$table} CASCADE");
        }
    }

    protected function container(): ContainerInterface
    {
        return self::$app->container;
    }

    protected function query(): QueryBuilderFactoryInterface
    {
        return $this->container()->get(QueryBuilderFactoryInterface::class);
    }

    protected function createUser(
        string $name = 'Test User',
        string $email = 'test@example.com',
        string $password = 'password123',
    ): int {
        $hasher = $this->container()->get(PasswordHasherInterface::class);

        return $this->query()->create()->table('users')->insert([
            'name' => $name,
            'email' => $email,
            'password' => $hasher->hash($password),
        ]);
    }

    protected function createWorkspace(int $userId, string $name = "Test's Workspace"): int
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name)) . '-' . substr(uniqid(), -6);

        $workspaceId = $this->query()->create()->table('workspaces')->insert([
            'name' => $name,
            'slug' => $slug,
            'owner_id' => $userId,
        ]);

        $this->query()->create()->table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role' => 'owner',
        ]);

        return $workspaceId;
    }

    protected function createCampaign(
        int $workspaceId,
        string $title = 'Test Campaign',
        string $status = 'draft',
    ): int {
        return $this->query()->create()->table('campaigns')->insert([
            'workspace_id' => $workspaceId,
            'title' => $title,
            'status' => $status,
        ]);
    }

    protected function createDocument(
        int $workspaceId,
        string $text = 'Test document content',
        string $name = 'test.txt',
    ): int {
        return $this->query()->create()->table('knowledge_documents')->insert([
            'workspace_id' => $workspaceId,
            'original_name' => $name,
            'raw_text' => $text,
            'metadata' => '{}',
        ]);
    }

    protected function createContentItem(
        int $campaignId,
        string $content = 'Test content',
        string $status = 'draft',
        string $type = 'blog_post',
        ?string $platform = null,
    ): int {
        return $this->query()->create()->table('content_items')->insert([
            'campaign_id' => $campaignId,
            'content' => $content,
            'status' => $status,
            'type' => $type,
            'platform' => $platform,
            'metadata' => '{}',
        ]);
    }
}
