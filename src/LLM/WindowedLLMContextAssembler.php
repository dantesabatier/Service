<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Closure;
use InvalidArgumentException;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use UnexpectedValueException;

/** Truncates complete conversation turns while preserving system messages and the latest turn. */
final readonly class WindowedLLMContextAssembler implements LLMContextAssembler
{
    private const string summaryPrefix = "Summary of earlier conversation context:\n\n";

    /**
     * @param int|null $maximumMessages Maximum messages sent on one turn, or `null` for no message limit.
     * @param int|null $maximumSize Maximum measured context size, or `null` for no size limit. The default measurement is approximate UTF-8 bytes; a custom measure may use provider tokens instead.
     * @param Closure(ArrayClass<LLMMessage>): ?string|null $compressor Summarizes omitted messages. The summary is inserted as a user message only when it also fits.
     * @param Closure(ArrayClass<LLMMessage>, ArrayClass<ToolDescriptor>, ?string): int|null $measure Returns the context size in the same unit as `$maximumSize`, or `null` for the default byte estimate.
     */
    public function __construct(private ?int $maximumMessages = null, private ?int $maximumSize = null, private ?Closure $compressor = null, private ?Closure $measure = null)
    {
        if ($maximumMessages !== null && $maximumMessages < 0) {
            throw new InvalidArgumentException("maximumMessages cannot be negative.");
        }
        if ($maximumSize !== null && $maximumSize < 0) {
            throw new InvalidArgumentException("maximumSize cannot be negative.");
        }
    }

    #[Override]
    public function assemble(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMContext
    {
        /** @var ArrayClass<ArrayClass<LLMMessage>> $segments */
        $segments = new ArrayClass();
        /** @var ArrayClass<LLMMessage> $current */
        $current = new ArrayClass();
        foreach ($messages as $message) {
            if (!$current->isEmpty && ($message->role === LLMMessageRole::user || $message->role === LLMMessageRole::system)) {
                $segments->append($current);
                /** @var ArrayClass<LLMMessage> $current */
                $current = new ArrayClass();
            }
            $current->append($message);
        }
        if (!$current->isEmpty) {
            $segments->append($current);
        }

        $retained = clone $segments;
        /** @var ArrayClass<LLMMessage> $omitted */
        $omitted = new ArrayClass();
        $assembled = $this->flatten($retained);
        while ($this->exceedsLimit($assembled, $tools, $systemPrompt)) {
            $index = $retained->dropLast(1)->firstIndex(fn(ArrayClass $segment): bool => !$segment->contains(fn(LLMMessage $message): bool => $message->role === LLMMessageRole::system));
            if ($index === null) {
                break;
            }
            /** @var ArrayClass<LLMMessage> $removed */
            $removed = $retained->removeAt($index);
            $omitted->appendContentsOf($removed);
            $assembled = $this->flatten($retained);
        }

        $wasCompacted = false;
        if (!$omitted->isEmpty && $this->compressor !== null) {
            $summary = trim((string)($this->compressor)($omitted));
            if ($summary !== "") {
                $candidate = clone $assembled;
                $index = $candidate->firstIndex(fn(LLMMessage $message): bool => $message->role !== LLMMessageRole::system) ?? $candidate->endIndex;
                $candidate->insertAt(new LLMMessage(LLMMessageRole::user, self::summaryPrefix . $summary), $index);
                if (!$this->exceedsLimit($candidate, $tools, $systemPrompt)) {
                    $assembled = $candidate;
                    $wasCompacted = true;
                }
            }
        }

        return new LLMContext($assembled, $systemPrompt, $omitted->count, $wasCompacted, !$this->exceedsLimit($assembled, $tools, $systemPrompt));
    }

    /**
     * @param ArrayClass<ArrayClass<LLMMessage>> $segments
     * @return ArrayClass<LLMMessage>
     */
    private function flatten(ArrayClass $segments): ArrayClass
    {
        return $segments->flatMap(fn(ArrayClass $segment): ArrayClass => $segment);
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    private function exceedsLimit(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt): bool
    {
        if ($this->maximumMessages !== null && $messages->count > $this->maximumMessages) {
            return true;
        }
        if ($this->maximumSize === null) {
            return false;
        }
        if ($this->measure !== null) {
            $size = ($this->measure)($messages, $tools, $systemPrompt);
            if ($size < 0) {
                throw new UnexpectedValueException("The context measure cannot return a negative size.");
            }
            return $size > $this->maximumSize;
        }
        $size = $tools->reduce(strlen((string)$systemPrompt), fn(int &$size, ToolDescriptor $tool): int => $size += strlen($tool->name) + strlen($tool->description) + strlen((string)json_encode($tool->inputSchema)));
        $size = $messages->reduce($size, fn(int &$size, LLMMessage $message): int => $size += strlen($message->role->value) + strlen((string)$message->content) + strlen((string)$message->toolCallId) + strlen((string)$message->reasoningContent) + strlen((string)json_encode($message->toolCalls?->array)) + strlen((string)json_encode($message->images?->array)) + strlen((string)json_encode($message->thinkingBlocks?->array)));
        return $size > $this->maximumSize;
    }
}
