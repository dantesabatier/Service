# Contributing

Thanks for looking. This is a young project with strong internal conventions,
so the most useful thing you can do before writing code is read the two
documents that explain why it looks the way it does:
[ARCHITECTURE.md](ARCHITECTURE.md) for the design, and [AGENTS.md](AGENTS.md)
for the conventions the source follows without exception.

## Getting set up

The framework needs PHP 8.5 or newer — it uses property hooks and the pipe
operator throughout, so an older runtime will not parse it. It also needs its
two sibling libraries, [Foundation](https://github.com/dantesabatier/Foundation)
and [CoreData](https://github.com/dantesabatier/CoreData), checked out beside
it:

```
Sabatier/
├── Foundation/
├── CoreData/
└── Service/
```

The dev tools are expected to be installed globally through Composer and on
`PATH`; `vendor/bin` is empty by design. Invoke them by bare name:

```bash
phpunit
```

```bash
psalm --show-info=false
```

```bash
rector --dry-run
```

## Before you open a pull request

- **`phpunit` passes in full.** The suite runs in about a second; there is no
  excuse for pushing a red tree. If a change makes an existing test fail, say
  so in the pull request and explain why the old expectation was wrong.
- **`psalm` reports no errors.** Psalm is the project's static-analysis gate.
- **`rector --dry-run` is clean for the files you touched.** It flags a handful
  of pre-existing files; leave those alone rather than folding an unrelated
  sweep into your change.
- **A new behaviour comes with a test that fails without it.** Write the test,
  watch it fail, then make it pass. A test that passes before your change tests
  nothing.

## Conventions that are not negotiable

These are architectural decisions, not preferences, and a pull request that
changes them will be asked to change back:

- **Property hooks for lazy initialization.** `private(set) T $x { get => $this->x ??= … }`
  is the pattern throughout. Do not convert these to constructor injection or
  traditional getters.
- **The `$data` pattern.** A responder provides data and lets the framework
  build the response. Override `$response` only for genuine full control:
  status codes with no body, streaming, PersistentSpace-level behaviour.
- **`Dictionary` returns `null` for a missing key**, so `$dict["key"] ?? null`
  and `?? ""` are always redundant. Read the key directly.
- **Foundation types over native arrays.** `ArrayClass` and `Dictionary` are
  cheap and cut code; prefer `map`/`filter`/`flatMap`/`reduce` over building an
  accumulator in a `foreach`.
- **Double-quoted strings**, and interpolation over concatenation.
- **`declare(strict_types=1)` in every file**, after the file-level comment
  when there is one.
- **Documentation in docblocks, not inline comments.** Explain *why* on the
  class, method or property. A `//` inside a method body is a sign the
  explanation is in the wrong place.

## The shared conventions checklist

The list above is what this project decided for itself. The mechanical
conventions underneath it — file layout, class and property rules, the
collection idioms, when a comment earns its place, what public API has to
document — are shared with Foundation and maintained there, as a checklist
with the shell search that finds each violation:

**[Foundation's CONVENTIONS.md](https://github.com/dantesabatier/Foundation/blob/master/CONVENTIONS.md)**

It is not vendored here on purpose. Four copies of one checklist drift, and
then nobody knows which is authoritative; the link always resolves to the
current version. Read it before opening a pull request — the static analysers
catch almost none of it.

Everything in that document applies to this repository, with two things worth
knowing before you run its searches:

- **Native arrays on the MCP surface are correct, not a lapse.** The
  collections rule prefers `ArrayClass`/`Dictionary` on declared signatures,
  and the tools in `src/MCP/Tools/` follow it where it counts —
  `execute(Dictionary $arguments): ArrayClass`. Their `$inputSchema` and
  `$outputFormat` stay native `array`, because those are JSON Schema literals
  serialized straight to the wire and read only by their known keys. That is
  the typed-shape exception the document already names. Do not "fix" them.
- **Classes that are not `final` are extension points.** `Application`,
  `Emitter`, `Renderer`, `Response`, `View`, `CacheHeaderTransformer` and
  `UnauthorizedException` are meant to be subclassed by the applications built
  on this framework, so the "final unless something extends it" search reports
  them whether or not anything in this tree does.

`src/` currently satisfies every mechanical rule the document can be searched
for: each file declares `declare(strict_types=1)`, no constant is
`UPPER_SNAKE_CASE`, no class is referenced by an inline `\Name`, comment prose
sits on one line, and comments are in English throughout. Keep it that way —
the searches in that document are the cheapest way to check before you push.

## Commit messages

Write a subject line that says what changed, then a body that says why it
needed to. The failure a change prevents is more useful to the next reader
than a restatement of the diff.

Release managers should also follow [RELEASING.md](RELEASING.md); versioned
changelog and support-policy edits happen only after the final tag version and
date are known.

## Reporting bugs

Include the PHP version, the versions or commits of all three libraries, and
the smallest case that reproduces the problem. For anything with a security
dimension, do not open an issue — follow [SECURITY.md](SECURITY.md) instead.
