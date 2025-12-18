<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

final readonly class CORSPolicy
{
    public bool $isEmpty;

    public function __construct(public Set $allowedOrigins = new Set(), public Set $allowedMethods = new Set(), public Set $allowedHeaders = new Set(), public bool $allowCredentials = false)
    {
        $this->isEmpty = $this->allowedOrigins->isEmpty && $this->allowedMethods->isEmpty && $this->allowedHeaders->isEmpty && !$this->allowCredentials;
    }

    public function intersect(Set $allowedMethods, Set $allowedHeaders): self
    {
        return new self($this->allowedOrigins, $this->allowedMethods->intersection($allowedMethods), $this->allowedHeaders->intersection($allowedHeaders), $this->allowCredentials);
    }

    public function allowsOrigin(string $origin): bool
    {
        return $this->allowedOrigins->contains(fn($allowedOrigin) => $allowedOrigin === "*" || $origin === $allowedOrigin);
    }
}
