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
- **`psalm` reports no errors.** Psalm is the authority on static analysis
  here. PHPStan is present because Rector needs it, and the two cannot both be
  satisfied — do not change code to quiet PHPStan.
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

## Commit messages

Write a subject line that says what changed, then a body that says why it
needed to. The failure a change prevents is more useful to the next reader
than a restatement of the diff.

## Reporting bugs

Include the PHP version, the versions or commits of all three libraries, and
the smallest case that reproduces the problem. For anything with a security
dimension, do not open an issue — follow [SECURITY.md](SECURITY.md) instead.
