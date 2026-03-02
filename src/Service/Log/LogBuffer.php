<?php

namespace App\Service\Log;

/**
 * Buffers logs and notifications in-memory during a request 
 * to be flushed at once at the very end (KernelEvents::TERMINATE).
 */
class LogBuffer
{
    private array $items = [];

    public function push(object $entity): void
    {
        $this->items[] = $entity;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function clear(): void
    {
        $this->items = [];
    }
}
