# Security Policy

## Supported versions

Before the initial public release, security fixes are made on `master`. No
released version is supported until `1.0.0` is published.

| Version              | Supported |
|----------------------|-----------|
| `master` (pre-1.0)   | yes       |

When `1.0.0` is tagged, this table will be updated in the release commit and
support will move to the latest `1.0.x` release. Versions not listed in this
table do not receive security fixes.

## Reporting a vulnerability

**Do not open a public issue for a security problem.** Report it privately, by
either route:

- **GitHub** — [Report a vulnerability](https://github.com/dantesabatier/Service/security/advisories/new)
  through the repository's private advisory form.
- **Email** — `dantesabatier@me.com`, with `SECURITY` in the subject.

Include as much of the following as is available:

- The affected version or commit and, if known, the SDK layer involved.
- The deployment assumptions and steps required to reproduce the issue.
- The security impact and what an attacker can gain.
- A minimal proof of concept, relevant logs or traces, with secrets and
  personal data removed.

A proof of concept helps, but do not delay a report to build one. Do not send
credentials, private keys, production data or other secrets.

## Response process

You can expect an acknowledgement within 5 calendar days and an assessment
with a planned fix date within 14 calendar days. The maintainer will coordinate
the fix, release and public advisory with the reporter. Credit is included in
the advisory unless the reporter prefers to remain anonymous.

Please allow the fix to ship before disclosing the issue publicly. If a report
receives no acknowledgement within 30 days, the reporter may disclose it.

## Scope

Service sits between untrusted requests and an application's data, so the
following are in scope and treated as vulnerabilities rather than
misconfiguration:

- **Authentication and tokens** — forging or replaying a JWT, bypassing
  signature verification, confusing one signing algorithm for another, or
  defeating the Basic/Bearer/Digest strategies.
- **Authorization** — reaching data outside the roles or scopes a subject
  holds, or past the evaluator chain, including via the `/refresh` action.
- **Field- and resource-level security** — reading a field or a row that
  `#[Readable]` excludes, or writing one that `#[Writable]` denies, by any
  route: `PersistentSpace`, a custom responder, or an MCP tool call.
- **Ownership** — mutating or deleting a row owned by another subject, whether
  `#[Owner]` is enforced by `OwnershipService` or by a rule declaring
  `AuthorizationScope::own`.
- **Request handling** — path traversal through the static-resource or upload
  paths, injection through a predicate, key path or `FetchRequest` accepted
  from a request, or bypassing rate limiting or idempotency.
- **Response pipeline** — leaking internal headers, another subject's cached
  response, or a security header the policy said to send.

Out of scope: vulnerabilities in an application built on the framework rather
than in the framework itself, findings that require an already-compromised
server or a modified deployment, denial of service through sheer request
volume, and reports produced solely by a scanner with no demonstrated impact.

## Cross-project reports

[Foundation](https://github.com/dantesabatier/Foundation) and
[CoreData](https://github.com/dantesabatier/CoreData) have their own
repositories. Report a vulnerability against the layer that owns it when that
is clear. If an issue crosses SDK layers, or its owner is uncertain, use either
private Service reporting route above; it will be triaged and routed without
requiring a second report.
