# Security Policy

## Supported versions

Until 1.0 is tagged, only `master` receives security fixes. Once released, the
latest minor of the current major is supported.

| Version  | Supported |
|----------|-----------|
| `master` | yes       |

## Reporting a vulnerability

**Do not open a public issue for a security problem.** Report it privately, by
either route:

- **GitHub** — [Report a vulnerability](https://github.com/dantesabatier/Service/security/advisories/new)
  through the repository's private advisory form.
- **Email** — `dantesabatier@me.com`, with `SECURITY` in the subject.

Please include what you have: affected version or commit, the component
involved, the steps that reproduce it, and what an attacker gains. A proof of
concept helps, but do not delay a report to build one.

You can expect an acknowledgement within 5 days, an assessment with a planned
fix date within 14, and credit in the advisory unless you would rather stay
anonymous. Please give the fix a chance to ship before disclosing publicly; if
a report goes unanswered for 30 days, treat that as consent to disclose.

## Scope

This framework's job is to sit between untrusted requests and an application's
data, so the following are in scope and treated as vulnerabilities rather than
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

The sibling libraries [Foundation](https://github.com/dantesabatier/Foundation)
and [CoreData](https://github.com/dantesabatier/CoreData) have their own
repositories; report an issue in either against the one it belongs to, or here
if you are unsure which.
