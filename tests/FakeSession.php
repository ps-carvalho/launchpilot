<?php

declare(strict_types=1);

namespace Tests;

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

class FakeSession implements SessionInterface
{
    private bool $isStarted = false;
    private array $data = [];
    private string $id = '';
    private FlashBag $flashBag;

    public function __construct()
    {
        $this->flashBag = new FlashBag($this->data);
    }

    public function start(): void
    {
        $this->isStarted = true;
        if ($this->id === '') {
            $this->id = bin2hex(random_bytes(16));
        }
    }

    public bool $started {
        get => $this->isStarted;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function all(): array
    {
        return $this->data;
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->isStarted = false;
        $this->id = '';
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function flash(): FlashBag
    {
        return $this->flashBag;
    }

    public function save(): void
    {
        // No-op for fake session
    }
}
