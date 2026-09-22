# Release Checklist

This checklist separates work that can be completed before the release decision from the edits that require the final version and date. Run it from a clean checkout of the commit intended for the tag.

`1.0.0` was published on 18 September 2026. What follows applies to every release after it.

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
6. Set `CFBundleShortVersionString` in `Info.plist` to the version being tagged — `1.0.1`, not `v1.0.1` — and increment `CFBundleVersion`, which is the build number and belongs to no one else. Nothing derives either from the tag. Confirm the version MCP reports identifies the same release. `Tools/verify-release.sh` in Foundation checks the bundle against what Packagist actually serves.
7. Create the annotated tag from the exact verified commit and publish release notes derived from the finalized changelog entry.

## After publishing

- Repeat the anonymous link check against the public repositories and release assets.
- Install or generate one application from the public release and repeat the smoke tests; do not rely only on the source checkout used to create the tag.
- Confirm the private vulnerability-reporting route in [SECURITY.md](SECURITY.md) is enabled and accepts a new draft report.
- Leave the new `Unreleased` section empty until the next user-visible change lands.
