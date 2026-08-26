# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims
to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html) from 1.0.0
onward.

## [Unreleased]

Nothing is released yet: `master` is pre-1.0 and its public surface is still
free to change. This section collects what will become the 1.0.0 notes.

### Added

- `LLMProviderException`, distinguishing a provider that failed to answer from
  a fault in the code that talks to it. It carries whether the failure is worth
  retrying, and the transport error when the request never arrived.
- `LLMRun::$isRetryable`, answering whether running the same thing again is
  worth the tokens — a question `$stopReason` cannot express on its own.
- `LLMRun::$stopReason` and `LLMRunStopReason`, telling apart a run the model
  concluded from one stopped by the iteration cap, the time limit, or a
  provider failure.
- `LLMRun::$toolCallResults`, rejoining every tool call with the result that
  came back for it.
- A time limit on an agentic run, bounding the wall clock that the iteration
  cap cannot: a turn waiting out a provider's backoff costs time without
  costing an iteration.
- Retry of transient provider failures in `LLMClient`, with exponential
  backoff that honours a numeric `Retry-After`.
- `AbstractTool::$isReadOnly`, declaring which MCP tools may have a repeated
  call served from the run's cache.
- `SECURITY.md`, `CONTRIBUTING.md`, `CHANGELOG.md` and `.env.example`.

### Fixed

- A provider failure no longer reaches `parse` as an empty body, which used to
  produce a turn with no text and no tool calls — the same shape as a model
  that decided to stop — so a failed run was reported as complete.
- `StandardLLMClient` read `input_tokens`/`output_tokens` from a Chat
  Completions body, which reports `prompt_tokens`/`completion_tokens`. Every
  run through that client counted zero tokens.
- `AnthropicClient` assigned each `text` block instead of concatenating, so a
  response carrying several blocks kept only the last.
- `AnthropicClient` ignored `extraBody`, leaving no way to send `temperature`
  or `thinking` through that client.
- A failed tool result reached OpenAI- and Ollama-shaped providers
  indistinguishable from a successful one, so the model took the error message
  for the answer instead of correcting the call.
- The agentic run cached calls to every tool, so two identical `create` calls
  the model made on purpose became one and a write was silently dropped.

### Changed

- Extensions that back interchangeable stores — `ext-apcu`, `ext-redis`,
  `ext-memcached` — moved from `require` to `suggest`, and extensions used by
  the sibling libraries are no longer re-declared here. Installing the
  framework no longer requires all three cache backends.
- Development files are excluded from the distributed package through
  `export-ignore`.
