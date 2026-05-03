<?php

declare(strict_types=1);

use App\Dashboard\Controller\SettingsController;

beforeEach(function () {
    $this->controller = $this->container()->get(SettingsController::class);
});

describe('index', function () {
    it('returns 200 with settings data', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'GET'],
            query: [],
            post: [],
            body: '',
        );

        $response = $this->controller->index($request);

        expect($response->statusCode())->toBe(200);
    });
});

describe('updateApiKey', function () {
    it('returns 403 for free tier user', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['api_key' => 'sk-test-key'],
            body: '',
        );

        $response = $this->controller->updateApiKey($request);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(403)
            ->and($data['error'])->toContain('Premium feature');
    });

    it('updates api key for pro user', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'pro',
            'daily_runs_used' => 0,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['api_key' => 'sk-pro-key'],
            body: '',
        );

        $response = $this->controller->updateApiKey($request);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($data['success'])->toBeTrue();

        $settings = $this->query()->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();
        expect($settings['openrouter_api_key'])->toBe('sk-pro-key');
    });

    it('clears api key when empty string provided', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'pro',
            'daily_runs_used' => 0,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
            'openrouter_api_key' => 'old-key',
        ]);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['api_key' => ''],
            body: '',
        );

        $response = $this->controller->updateApiKey($request);

        $settings = $this->query()->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();
        expect($settings['openrouter_api_key'])->toBeNull();
    });
});

describe('updateCustomPrompts', function () {
    it('returns 403 for free tier user', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['prompts' => ['social' => 'Be funny']],
            body: '',
        );

        $response = $this->controller->updateCustomPrompts($request);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(403)
            ->and($data['error'])->toContain('Premium feature');
    });

    it('saves custom prompts for pro user', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'pro',
            'daily_runs_used' => 0,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $request = new \Marko\Routing\Http\Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['prompts' => ['social' => 'Be a pirate']],
            body: '',
        );

        $response = $this->controller->updateCustomPrompts($request);
        $data = json_decode($response->body(), true);

        expect($response->statusCode())->toBe(200)
            ->and($data['success'])->toBeTrue();

        $settings = $this->query()->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();
        $prompts = json_decode($settings['custom_prompts'] ?? '{}', true);
        expect($prompts['social'])->toBe('Be a pirate');
    });
});

describe('disconnectGsc', function () {
    it('clears gsc refresh token', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $this->createWorkspace($userId);

        $this->query()->create()->table('user_settings')->insert([
            'user_id' => $userId,
            'tier' => 'free',
            'daily_runs_used' => 0,
            'runs_reset_at' => gmdate('Y-m-d H:i:s'),
            'gsc_refresh_token' => 'token-123',
            'gsc_connected_at' => '2026-01-01 00:00:00',
        ]);

        $response = $this->controller->disconnectGsc();

        expect($response->statusCode())->toBe(302);

        $settings = $this->query()->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();
        expect($settings['gsc_refresh_token'])->toBeNull()
            ->and($settings['gsc_connected_at'])->toBeNull();
    });
});

describe('export', function () {
    it('returns markdown for workspace', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);
        $wsId = $this->createWorkspace($userId);
        $this->createDocument($wsId, 'Doc content', 'doc.txt');

        $response = $this->controller->export();

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toContain('LaunchPilot Knowledge Base Export')
            ->and($response->body())->toContain('Doc content');
    });

    it('redirects when no workspace exists', function () {
        $userId = $this->createUser();
        $this->loginAsUser($userId);

        $response = $this->controller->export();

        expect($response->statusCode())->toBe(302);
    });
});
