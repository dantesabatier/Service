# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims
to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html) from 1.0.0
onward.

## [Unreleased]

Nothing is released yet: `master` is pre-1.0 and its public surface is still
free to change. This section collects what will become the 1.0.0 notes.

### Security

- An upload's filename reached the filesystem unchecked. `appendingPathComponent`
  does not canonicalize, so a name carrying `..` was written outside the
  subdirectory `Uploader` had validated — and where the destination fell inside a
  directory `StaticResourcePolicy` calls public, the file was then served without
  authentication and cached for a year.
- A download validated its location by comparing the prefix of a string it had
  not resolved, so the guard passed a path whose `..` the filesystem resolved
  afterwards. It also bypassed the policy that already protects static reads,
  serving what `ResourceManager` refuses: dotfiles, and without the public and
  protected distinction.
- An upload's temporary path was moved without `is_uploaded_file()`, so a
  `tmp_name` naming some other file on the server was moved into the upload
  directory rather than refused.
- Rate limiting keyed its counter on the username the request claimed, which is
  read before any credential is verified. Anyone could exhaust another subject's
  quota by asserting their name, or escape the address limit entirely by
  inventing a new name per request. Every counter now carries the address, and
  the address counter applies to every request.

### Added

- `FileTransferPolicy`, resolving where an upload may be written and which file a
  download may read — the counterpart of `StaticResourcePolicy` for the transfer
  surface, deferring to it on the read side. Configured through
  `FILE_TRANSFER_DIRECTORIES`, `FILE_TRANSFER_ALLOWED_EXTENSIONS`,
  `FILE_TRANSFER_MAXIMUM_SIZE` and `FILE_TRANSFER_FILE_PERMISSIONS`.
- `FileTransferComponent`, which judges one name of a transfer location. Both
  halves — the subdirectory and the filename — are held to the same shape, and an
  invalid name is refused rather than rewritten.
- `AbstractTool::$isCacheable`, separating "may a repeated call be served from
  the run's cache" from "does this tool only read state". A tool reading
  something that moves on its own is read-only and not cacheable.
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

- Nothing reaches `parse` as an empty body any more, which used to produce a
  turn with no text and no tool calls — the same shape as a model that decided
  to stop — so a failed run was reported as complete. Both doors are closed: a
  failing status, and a success whose body does not decode into one.
- `get_server_time` was cached for the length of a run. It takes no arguments,
  so every call shared one cache key and the first answer stood as the time for
  the rest of the run. Caching now asks `isCacheable`, which a tool reading
  something that moves on its own declines while staying read-only.
- A subagent inherited no time limit, and its own time did not count against
  the parent's. It now inherits what is left of the parent's window.
- An upload that the transport rejected was not detected: a file over
  `upload_max_filesize` arrives with a size of zero and an empty temporary path,
  passed the size check, and failed to move — a `500` where the caller deserved
  `400` and the reason. `$_FILES["error"]` is now read per file.
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

- **Breaking.** `POST /download` still takes the same `url` field, but its path
  must now name exactly one directory and one file — the single level `POST
  /upload` writes to. A deeper or shallower location is refused with `400`, where
  it was previously resolved against the document root. `API.md` had documented a
  path without a scheme, which the endpoint has never accepted: `url` is a full
  URL and only its path is read.
- Extensions that back interchangeable stores — `ext-apcu`, `ext-redis`,
  `ext-memcached` — moved from `require` to `suggest`, and extensions used by
  the sibling libraries are no longer re-declared here. Installing the
  framework no longer requires all three cache backends.
- Development files are excluded from the distributed package through
  `export-ignore`.
