# Render preparation verification — October 19, 2026

**PASS — local deployment preparation.** Live Render deployment is intentionally
not performed. No paid resource, credit consumption, push, commit, Azure change,
CES change, or production SQL change was made.

## Source and scope

- Worktree: `/home/whis/Projects/3a-trackpro-render-demo`
- Branch: `deploy/render-trackpro-demo`
- Exact baseline: `6d9f6576306e143e3518d37b8b7320d1e08bc49c`
- Original checkout remains on `deploy/azure-trackpro-demo`; its four tracked
  modifications and four untracked status entries are preserved.
- All changes are new Render deployment/docs/rehearsal files. Tracked source and
  index remain identical to the baseline. Business logic, migrations, authentication
  configuration and existing tests were not edited.

## Final results

| Check | Actual result |
| --- | --- |
| Production Docker build | PASS, `trackpro-render-demo:20261019` |
| Runtime image ID | `sha256:23d9c903b62e37d54d6e68914c4dfbb7737fac35b750a65155b86a74c6e6066d` |
| Locked Laravel / runtime | Laravel 13.30.1, PHP 8.4.26, MySQL 8.4.11 |
| Composer production install/platform requirements | PASS, no dev dependencies |
| Vite production assets | PASS, including vendor pagination templates used by Tailwind |
| PHP extensions | BCMath, PDO MySQL, mbstring, intl, zip, OpenSSL, fileinfo, ctype, tokenizer, DOM, XML present |
| PHPUnit (network disabled, SQLite in memory) | **34 tests, 239 assertions; no failures or warnings** |
| Render PORT / health | `/up` returns 200 on custom port 18080; Docker health observed healthy |
| Startup safety | No schema automatically created; invalid production config refuses startup |
| MySQL transport | TLS required; hostname and CA verified; nonempty TLS cipher |
| Negative TLS checks | Wrong hostname and wrong CA both refused by PHP/Laravel |
| Supervised local setup | All existing migrations and interactive create-admin command succeed on new isolated MySQL |
| HTTPS/auth smoke | HTTPS form URLs; Secure, HttpOnly, SameSite=Lax cookies; real Admin login succeeds |
| Authenticated pages | Dashboard, POS, products, purchase orders, reports and user management return 200 |
| Public boundary | Assets return 200; environment, Composer, deployment, storage and arbitrary PHP paths blocked |
| Replacement/persistence | Graceful shutdown exit checked; same login, file cache and private file survive replacement container |
| Database persistence | Admin/data remain usable after MySQL restart with persistent volume |
| Image/context hygiene | Context exported and inspected; no Azure deployment files, `.env`, private keys, certificates, SQL/SQLite dumps or detected Azure credential markers |
| Production image hygiene | No Git history, Azure deployment artifacts, test suite, `.env`, Composer or Node executable |
| Syntax/baseline | Bash syntax, Python AST and clean tracked/index baseline checks pass |

These are deployment/authentication smoke checks, not a new full business workflow
or concurrency certification. No existing development database was accessed.

## Evidence and rehearsal resources

Final logs are outside the repository (no credentials printed):

- `/tmp/trackpro-render-stop-fix-build.log` — final production build
- `/tmp/trackpro-render-test-final-build.log` — final test image build
- `/tmp/trackpro-render-phpunit-final.log` — clean PHPUnit result
- `/tmp/trackpro-render-runtime-final.log` — complete final runtime rehearsal, exit 0
- `/tmp/trackpro-render-context-verified.log` — corrected context export

The final rehearsal used resource prefix `trackpro-render-check-6de1d16a`.
Its original web container, replacement web container and MySQL container are stopped.
The private Docker network and two new volumes are retained. Earlier rehearsal
resources with prefixes `trackpro-render-check-b6c3b3ac` and
`trackpro-render-check-f376764e` are also retained with containers stopped.
Test credentials/certificates reside only in private `/tmp/trackpro-render-check-*`
directories and container environments; do not upload these as production secrets.
No existing containers or volumes were deleted or modified.

Earlier evidence is preserved as historical, not counted as passes: an initial
Artisan test invocation lacked a test key (23 failures), a keyed Artisan wrapper run
produced 30 missing-`.env` warnings, and a socket-only MySQL readiness probe raced
the official image's temporary initialization server (connection refused).
The test image now generates an ephemeral key and invokes PHPUnit directly; its
readiness probe requires an authenticated TLS TCP query. Initial container shutdown
also exposed the FPM base image's SIGQUIT mismatch with the Bash supervisor. The
runtime now declares SIGTERM explicitly, and final replacement checks reject forced
exit 137. No application tests/assertions were weakened to address these issues.

## Remaining deployment gates

1. Review and authorize a Git commit/push separately; Render cannot build unpushed files.
2. Authorize the paid services separately; confirm the dashboard quote and region.
3. Provision private credentials, stable APP_KEY, and hostname-specific MySQL TLS
   certificates using the deployment guide. Check server key readability and final
   MySQL readiness; never bypass verification on a TLS error.
4. Perform the one supervised live migration/admin setup and the browser smoke
   test before sharing the demo URL for October 22.

See [dashboard guide](render-trackpro.md) for exact settings and commands.
