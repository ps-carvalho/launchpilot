<?php

declare(strict_types=1);

use App\Dashboard\Http\RequestBodyParser;
use Marko\Routing\Http\Request;

beforeEach(function () {
    $this->parser = new RequestBodyParser();
});

describe('get', function () {
    it('returns post value when present', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['message' => 'hello from post'],
            body: '',
        );

        expect($this->parser->get($request, 'message'))->toBe('hello from post');
    });

    it('falls back to json body when post is empty', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            query: [],
            post: [],
            body: json_encode(['message' => 'hello from json']),
        );

        expect($this->parser->get($request, 'message'))->toBe('hello from json');
    });

    it('prefers post over json body', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['message' => 'post value'],
            body: json_encode(['message' => 'json value']),
        );

        expect($this->parser->get($request, 'message'))->toBe('post value');
    });

    it('returns default when key not found', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '{}',
        );

        expect($this->parser->get($request, 'missing'))->toBeNull()
            ->and($this->parser->get($request, 'missing', 'default'))->toBe('default');
    });

    it('returns null value when key exists with null', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['key' => null],
            body: '',
        );

        expect($this->parser->get($request, 'key'))->toBeNull();
    });

    it('handles non-json body gracefully', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: 'not json at all',
        );

        expect($this->parser->get($request, 'key', 'fallback'))->toBe('fallback');
    });
});

describe('all', function () {
    it('returns all post data', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: ['a' => 'post'],
            body: json_encode(['b' => 'json']),
        );

        expect($this->parser->all($request))->toBe(['a' => 'post']);
    });

    it('falls back to json body', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            query: [],
            post: [],
            body: json_encode(['b' => 'json']),
        );

        expect($this->parser->all($request))->toBe(['b' => 'json']);
    });

    it('returns empty array for empty request', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            query: [],
            post: [],
            body: '',
        );

        expect($this->parser->all($request))->toBe([]);
    });
});
