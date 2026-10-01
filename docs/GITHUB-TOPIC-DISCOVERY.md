# GitHub Topic Discovery — Plan

**Status:** Approved. Revised after adversarial peer review (agy) + grill rounds 1–3.
**Author:** Esteban
**Date:** 2026-10
**Scope:** UnrePress-index (crawler + workflow) and UnrePress plugin (settings, search merge, install/update, badge)

---

## 1. Summary

UnrePress plugin/theme search filters static JSON catalogs published by the UnrePress-index repo. This plan adds GitHub topic-tagged repositories (`wp-plugin`, `wordpress-plugin`, `wp-theme`, `wordpress-theme`) as a second, clearly separated source.

Core stance (unchanged): a crawler runs centrally in the index repo on a schedule; output is static JSON in `discovery/`; site search stays a local filter; curated entries rank above GitHub entries; GitHub entries carry a server-rendered "From GitHub" label.

Revision 2 changes (peer review):

- The crawler precomputes `version`, `download_url`, and the full WordPress card shape. **The client never calls api.github.com at runtime.** `GitHubProvider` currently instantiates its client without a token (GitHubProvider.php:20); unauthenticated quota is 60 req/hr/IP — unacceptable per site.
- Catalog entries use the root keys the client already reads: `plugins` / `themes` (UpdatePlugins.php:859, ThemesIndex.php:107).
- Installed GitHub extensions are recorded in a tracking option so catalog drift never orphans updates.
- Zip extraction gains the missing security checks on the `fixSourceDir()` path.
- New: plugin Settings page (§7) for user-supplied provider tokens and discovery options.

## 2. Goals

- Plugin and theme search returns topic-tagged GitHub repos by default (`UNREPRESS_GITHUB_DISCOVERY` default `true`).
- Installed GitHub entries keep receiving update checks, even after catalog drift.
- Zero runtime GitHub API calls from client sites; static JSON is the only runtime dependency.
- Trust boundary visible: curated vs. unvetted labeled and ranked; "Not verified" note on detail.
- Users can supply their own provider tokens via a Settings page.

## 3. Non-goals

- No live GitHub search from the plugin.
- No human approval queue for GitHub entries (crawler filters only).
- No change to the update/checksum contract for curated index items.
- No multisite transient refactor (pre-existing `get_transient` vs `get_site_transient` issue tracked separately).

## 4. Index side (UnrePress-index repo)

### 4.1 New script

`.ci/crawl-github-topics.py`, separate from `rebuild-indexes.py`.

Search queries push quality gates into the API query string so the 1000-result window is not wasted post-fetch:

- Plugins: `topic:wp-plugin fork:false archived:false stars:>=20`, then `topic:wordpress-plugin …`
- Themes: `topic:wp-theme fork:false archived:false stars:>=20`, then `topic:wordpress-theme …`

Sorted by stars desc. `GITHUB_TOKEN` auth, paced with backoff. Description-required filter post-fetch.

Hard constraint: Search API returns max 1000 results per query. Accept the top 1000 by stars; set `truncated: true` when at cap.

### 4.2 Per-repo verification budget

Per repo: 1 `/releases/latest` call → 1 `git/trees/{resolved_tag}` call + up to 3 raw file fetches **at that tag ref**. All verification (§4.3, §4.4, §4.6) runs against the winning tag's tree — never the default branch. Verifying `main` while shipping a tag lets an attacker keep the inspected branch clean and ship malware in the tag (review BLOCKER: verify what you ship). Re-verification skipped when `pushed_at` is unchanged from the previous run (state cached between Actions runs); then only the search pass runs. ~4000 core API calls/day worst case, within the 5000/h token quota.

### 4.3 WP-ness verification

- **Plugins**: from the tag's tree listing, fetch up to 3 candidate root PHP files — `{repo}.php` first, then `plugin.php` / `index.php`, then first root `.php` alphabetically — until one carries a `Plugin Name:` header. Fail → excluded.
- **Themes**: raw fetch root `style.css`; require `Theme Name:` header. Child themes (non-empty `Template:` header) → excluded in v1.
- Root layout only. Subdirectory-layout repos excluded in v1 (`Helpers::fixSourceDir()` assumes the extracted root is the extension root).

### 4.4 Vendor check (composer repos)

If the tree contains a root `composer.json`:

- `vendor/` must also be present and tracked **in the tag's tree**. Missing → **excluded** (raw git archives ship no vendor/; the install fatals on `vendor/autoload.php`). Vendor committed on `main` but absent from the tag → excluded.
- No `composer.json` → tag archive is fine as-is.

### 4.5 Tag, release, and version resolution

- `/releases/latest` (1 call) doubles as the tag-existence check and yields the release asset when present.
- Repos with zero tags/releases → excluded (no versioned download).
- Version = semver extracted from the release/tag (`\d+(\.\d+)+`), semver-sorted, computed centrally. Tag strings like `release-1.2.0` normalize cleanly.
- `download_url` = release asset zip when present (`build: "release-asset"`), else codeload tarball/zipball of the winning tag (`build: "tag-archive"`).

### 4.6 Static checks

Every candidate passes, before indexing:

- `php -l` on the detected main file (and optionally all root PHP files).
- Dangerous-sink grep: `eval(base64_decode`, `system(`, `exec(`, `shell_exec(`, `passthru(`, base64 blobs > 4 KB in the main file. Hit → excluded, logged.

Quality gates, not security guarantees — but cheap and they catch the laziest malware.

### 4.7 Dedup

By `full_name` across the two queries per type; union of topics kept. Also crawler-side dedup against the curated catalog: the crawler reads curated per-item JSONs locally, builds the repo-URL set, drops GitHub repos whose URL matches. Curated wins, once, centrally — the discovery index carries no repo URLs, so client-side dedup is impossible.

### 4.8 Output schema

```
discovery/github-plugins.json   →  { "schema_version": 1, "source": "github-topic", "generated_at": "...", "truncated": false, "plugins": [ … ] }
discovery/github-themes.json    →  { …, "themes": [ … ] }
```

Root keys match what the client already reads. Per entry (full WP card shape — core's install grid reads these directly, PHP 8.3 fatals on missing keys):

```json
{
  "slug": "<owner>--<repo>",
  "name": "…",
  "version": "1.2.0",
  "author": "<a href=\"https://github.com/owner\">owner</a>",
  "author_profile": "https://github.com/owner",
  "requires": "6.0",
  "tested": "6.7",
  "requires_php": "7.4",
  "short_description": "…",
  "description": "…",
  "download_url": "https://…",
  "homepage": "https://github.com/owner/repo",
  "icons": { "default": "…" },
  "last_updated": "2026-05-09 14:00:00",
  "tags": ["wp-plugin", "seo"],
  "topics": ["wp-plugin"],
  "stars": 123,
  "pushed_at": "…",
  "provider": "github",
  "build": "release-asset | tag-archive",
  "default_branch": "main"
}
```

Slugs are `<owner>--<repo>` (double hyphen: GitHub forbids consecutive hyphens in usernames, so the mapping is unambiguous — `my--x` vs `my-x--y` never collide). Deterministic, collision-free with curated slugs; install folder name doubles as the update-check key. `requires`/`tested` defaults come from main-file/style headers when parsed, else sane constants.

### 4.9 Scheduling and commit

- GitHub Actions daily cron + `workflow_dispatch`. Concurrency-limited, 250 ms sleep between core API calls, ETag caching where cheap.
- Workflow hardening (review finding): job `permissions: contents: read` (commit job gets `contents: write` only); `persist-credentials: false`; repo names/branches from API output are never interpolated into `run:` shell strings — written to files, read with `jq`; no `pull_request_target`; `GITHUB_TOKEN`-pushed commits don't retrigger CI (no loop).
- Router: `index.json` gains `github_index` under both the `plugins` and `themes` blocks.
- Commit: `index update` (existing convention). A failed crawl keeps the previous JSON — never publish an empty catalog.

## 5. Plugin side (UnrePress repo)

### 5.1 Search merge

`UpdatePlugins::searchPlugins()` / `ThemesIndex::searchThemes()`:

1. Fetch the GitHub catalog via the main-index `github_index` URL. Transient 3h, `'timeout' => 15` on the `wp_remote_get`.
2. Filter with the existing substring logic over both catalogs.
3. Order: curated matches first, GitHub matches after. GitHub entries keep `"source": "github"`, `"stars"`, `"build"`.
4. Pagination over the combined list.
5. Dedup is already done crawler-side; no client-side dedup.
6. Failed/absent GitHub catalog → degrade to curated-only, logged. Never blocks curated search.

### 5.2 Detail and install for GitHub entries

- `plugin_information` / `theme_information` resolve `owner--repo` slugs against the cached catalog. All card fields come from the entry — no runtime GitHub calls.
- `download_url` comes straight from the catalog.
- `build: "tag-archive"` entries show a notice in the detail view: raw source archive, vendor shipped as committed.
- `build` field and "Not verified by the UnrePress index" note rendered server-side.

### 5.3 Zip hardening (fixes existing gap)

`Helpers::fixSourceDir()` currently renames the root and deletes `.git`/`.github` with no security checks (verified, Helpers.php:406–483). Before moving any archive — GitHub or curated:

- Reject symlinks (`is_link()`) and non-regular files.
- Reject dangerous filenames (`wp-config.php`, `.htaccess`, `php.ini`).
- Enforce extracted-size ceiling (align with spec §5 50 MB).
- Route through `SecureFileOperations` so curated and GitHub installs share one guard.

### 5.4 Updates for installed GitHub entries

- On install, record the extension: option `unrepress_tracked_repos` = `{ "owner--repo": { provider, repo_url, installed_version } }`.
- Update check order in `checkForPluginUpdate()` (and theme equivalent): curated per-item JSON → tracked-repos option → (cache refresh) catalog. Tracked entries resolve version from catalog `version` while present, and fall back to the stored `repo_url` when the entry drops out — a repo leaving the top-1000 never orphans an installed site.
- Version comparison uses the precomputed semver. No client-side tag sorting.

### 5.5 UI

- Badge = injected DOM, core layout untouched. Inline data blob (`slug → stars, build`) via `wp_add_inline_script()` with `wp_json_encode(…, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`. Cards matched via `.install-now[data-slug]` → `closest('.plugin-card')` — core card divs carry `plugin-card-{slug}` classes, not `data-slug`; use `CSS.escape()`. Badge text via `textContent` only.
- Detail header: server-side "From GitHub · ★N", "Not verified by the UnrePress index", `build` notice where applicable.
- All GitHub-catalog strings pass `sanitize_text_field()` / `wp_kses_post()` before render. No `innerHTML` from catalog data, ever.
- `UNREPRESS_GITHUB_DISCOVERY` constant, default `true`, disables the merged source (Settings page toggle mirrors it).

## 6. No new runtime GitHub calls

Consequence of the precomputed schema: `GitProviderWrapper` stays out of the search/install/update hot path for GitHub entries. Runtime provider calls remain only for curated flows that already use them, now authenticated via §7 tokens.

## 7. Settings page (added in revision 2)

New `Admin/Settings.php` + `views/admin/settings.php`. Submenu under the existing UnrePress updater page.

Fields (single option `unrepress_settings`, array):

| Field | Type | Purpose |
|---|---|---|
| `github_token` | password-style input | User's GitHub PAT; authenticates any runtime provider use |
| `gitlab_token` | password-style input | GitLab PAT |
| `bitbucket_token` | password-style input | Bitbucket app password |
| `github_discovery` | checkbox | Mirrors `UNREPRESS_GITHUB_DISCOVERY` (constant wins when defined) |
| `clear_cache` | button | Calls `Helpers::clearUpdateTransients()` + flushes index transients |

Rules:

- Capability `manage_options`; nonce + `SecurityMiddleware` on save; `register_setting` with a sanitize callback built on `InputValidator`.
- Token resolution order: constant (`UNREPRESS_TOKEN_GITHUB` etc.) → saved option → `unrepress_github_token` filter → null. Constants stay the host-level override.
- Tokens stored as-is in the options table (DB plaintext — documented in the settings UI). UI shows masked value (provider + last 4 chars). Tokens are never echoed in full, never logged; Debugger redacts.
- On provider instantiation, resolve token and call the existing `authenticate()` (GitHubProvider.php:86) when non-null.
- `clear_cache` is a Settings-API form POST (admin-post or options.php flow) with nonce + `manage_options` + `SecurityMiddleware` — not a bare link.
- The plugin ships `uninstall.php` purging: `unrepress_settings` (plaintext PATs), `unrepress_tracked_repos`, all `unrepress_*` transients. Token-bearing options must not outlive the plugin (review finding).

## 8. Rollout

1. **P1 — crawler, full schema**: in-query filters, verification + vendor check + static checks, semver + `download_url` precompute, crawler-side dedup, Actions workflow, router keys. Manual first run, commit catalogs.
2. **P2 — install/update/tracking + settings**: tracked-repos option, `plugin_information` resolution, zip hardening (§5.3), Settings page + token wiring, `uninstall.php`. Pest tests alongside: slug map, version compare, zip guard, token resolution. Fixtures = real output of the P1 manual crawl, not handwritten JSON.
3. **P3 — search merge + badge**: catalog fetch/merge, degrade path, inline badge script with escaping. Pest: merge order, `source` flagging, degrade.
4. **P4 — E2E on docker stack** (`./devenv start`): search a GitHub-only term → badge + ranking; install a `release-asset` entry and a `tag-archive` entry; bump a mock repo tag → update check resolves; `UNREPRESS_GITHUB_DISCOVERY=false` → curated behavior identical.

Search stays disabled for GitHub entries until P3; install/update machinery ships tested first.

## 9. Risks

- **1000-result cap**: top-by-stars only. Accepted.
- **Catalog size**: full WP shape ≈ 600–800 bytes/entry → ~800 KB/file at 1000 entries. Watch first crawl; gzip on raw CDN mitigates; client timeout 15 s.
- **Bot-star squatting**: 20-star floor + static checks are quality gates, not vetting. The "From GitHub" + "Not verified" labels are the real gate. Default-on is a product decision (D2) — accepted with eyes open.
- **License ignored**: unlicensed (non-distributable) repos may enter. Accepted for v1.
- **Actions secondary limits**: pushed_at re-verify cache + pacing. Failed run keeps previous JSON.
- **Tag-archive builds**: composer repos without committed vendor/ are excluded (§4.4), but tag archives can lag release assets in freshness. Surfaced via `build` field.
- **Tokens in DB**: options-table plaintext. Documented in UI; constants remain the recommended path for high-security hosts. Purged on uninstall (§7).
- **Catalog transients are ~800 KB**: object-cache sites hold them out of the options table; on options-backed sites keep them short-TTL and never autoloaded (WP 6.6+ autoload balancing helps; plugin floor is 6.5 — verify `autoload=no` on first store).

## 10. Success criteria

- Fresh `./devenv start` stack: search a GitHub-only term → entry shows, badged, below curated hits.
- Install both build types; site makes **zero** api.github.com calls during search/install (verify via Debugger log).
- Repo with clean `main` but malicious/unverifiable tag → excluded by the tag-ref verification (bypass test).
- Tag bump on a mock repo → update check offers the new version within one transient flush.
- Repo falling out of the top-1000 → tracked install still receives updates.
- `UNREPRESS_GITHUB_DISCOVERY=false` (constant or Settings) → curated behavior byte-identical.
- Settings page saves/masks tokens; constant override beats saved value; `php -l` clean.
