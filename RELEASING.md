# Release Checklist

This checklist separates work that can be completed before the release decision from the edits that require the final version and date. Run it from a clean checkout of the commit intended for the tag.

The current target is the `1.0.0` release before the end of 2026. This is a planning window, not the release date: `CHANGELOG.md` remains under `[Unreleased]` and `SECURITY.md` continues to describe the pre-1.0 branch until the final tag date is known.

## Before choosing the release version

- Confirm the documented public surface matches the source: environment keys, HTTP response shapes, tool names, protocol revisions and lifecycle timing.
- Run the full PHPUnit suite, Psalm and Rector as documented by the project. A warning or deprecation in the supported PHP runtime is release work, even when the test process exits successfully.
- Generate a fresh application through Singularity and verify that it boots, serves its home response and exposes one model entity. Test the generated contract rather than hand-built scaffolding or an existing development checkout.
- Exercise one authenticated REST read and write, an MCP `initialize` → `tools/list` → `tools/call` session, and one CLI job against the release candidate.
- Verify every public GitHub link without repository credentials. Foundation, CoreData, Service, private security reporting and any generator named by the documentation must be reachable when the release is announced.
- Review `.env.example` as a deployment contract. Every key read by the framework must be represented, and every default or required extension must match runtime behavior.
- Reconfirm the MCP compatibility statement against the current official protocol revision. A newer revision does not silently become supported because clients know it.

## When version and date are final

1. Rename `## [Unreleased]` in `CHANGELOG.md` to `## [<version>] - <YYYY-MM-DD>`.
2. Remove the pre-release paragraph below that heading and edit the notes as a user-facing release history, not a development diary.
3. Add a new empty `## [Unreleased]` section above the released version.
4. Add comparison links at the bottom of `CHANGELOG.md`: `Unreleased` compares the release tag with `HEAD`; the released version links to its tag for the first release and to a tag comparison thereafter.
5. Replace the pre-1.0 `master` row in `SECURITY.md` with the actual supported release line. State the branch policy separately if `master` continues receiving fixes.
6. Ensure the server version reported by MCP and the bundle/package version identify the same release according to the project's versioning policy.
7. Create the annotated tag from the exact verified commit and publish release notes derived from the finalized changelog entry.

## After publishing

- Repeat the anonymous link check against the public repositories and release assets.
- Install or generate one application from the public release and repeat the smoke tests; do not rely only on the source checkout used to create the tag.
- Confirm the private vulnerability-reporting route in [SECURITY.md](SECURITY.md) is enabled and accepts a new draft report.
- Leave the new `Unreleased` section empty until the next user-visible change lands.
