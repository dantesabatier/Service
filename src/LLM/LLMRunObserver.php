<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Receives immutable, provider-neutral events from the agentic loop as they occur. */
interface LLMRunObserver
{
    /**
     * Receives one event at the point where its loop boundary occurs.
     *
     * @param LLMRunEvent $event A lifecycle event carrying scalar snapshots rather than live loop objects.
     */
    public function observe(LLMRunEvent $event): void;
}
