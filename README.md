# AI Prompt Library & Manager

A Laravel + MySQL web application for saving, organising, searching, rating and
improving prompts used with generative-AI tools such as ChatGPT, Gemini and
Claude.

This repository is an academic capstone project. It is a working application with
a manually created database schema, not a static mockup: every listing is backed
by a real query, every write is authorised on the server, and every AI feature
reports failure honestly rather than inventing output.

---

## Table of contents

1. [Key features](#1-key-features)
2. [Technology](#2-technology)
3. [Local setup](#3-local-setup)
4. [Architecture](#4-architecture)
5. [Database schema](#5-database-schema)
6. [Search, ranking and recommendation](#6-search-ranking-and-recommendation)
7. [AI features](#7-ai-features)
8. [Security model](#8-security-model)
9. [Console commands](#9-console-commands)
10. [Verification and testing](#10-verification-and-testing)
11. [Known limitations](#11-known-limitations)

---

## 1. Key features

### Prompt management
- Create, view, edit, archive and hard-delete prompts, with a title, optional
  description, prompt text, category, AI model, language and visibility.
- Private and public visibility. Private prompts are readable only by their owner.
- System/sample prompts (`user_id IS NULL`) are public and read-only for everyone,
  administrators included.
- Owner-only mutation, enforced by a policy rather than by hiding buttons.
- Tag entry as a comma-separated list, normalised to lowercase hyphenated slugs
  and reused rather than duplicated.

### Discovery
- Full-text search over title, description and prompt text using the MySQL
  `FULLTEXT` index, with an automatic parameterised `LIKE` fallback for terms the
  full-text parser cannot serve.
- Tag search through a correlated subquery, so a keyword can match a tag even when
  it appears nowhere in the text.
- Filters for category, AI model, language, and "only my prompts".
- Five sort orders: newest, most copied, highest rated, trending, and relevance.
- Twelve results per page with the query and filters preserved while paging.

### Engagement
- Favourites, idempotent by composite primary key.
- One rating per user per prompt, updated rather than duplicated on re-rating.
- Server-side copy counting that never trusts a client-supplied value.

### AI assistance (Google Gemini)
- **Analyzer** — strengths, weaknesses, missing context, suggestions, and a clarity
  score with an explanation.
- **Improver** — a rewritten prompt plus a list of what changed.
- **Category and tag suggestions** — constrained to the categories that exist, only
  applied when the user accepts them.
- **Version history** — saving an improved draft keeps the text it replaced in
  `prompt_versions`.
- AI results are never fabricated. If the provider fails, the user sees a specific
  error and their text is untouched.

### Ranking and recommendations
- A documented trending score combining copies, favourites, ratings and age.
- A Wilson lower-bound rating score, so a single 5-star rating cannot outrank a
  prompt with many consistently positive ratings.
- Related prompts by shared tags.
- "Users also liked" from co-favourites, with documented fallbacks.
- A duplicate warning while composing, which advises rather than blocks.

### Administration
- Category management with activate/deactivate, and deletion refused while prompts
  still reference the category.
- User management with a safeguard against removing the final administrator.
- Moderation of **public** prompts only, including feature and archive toggles.

### Public site
- A landing page with live counts, featured prompts, category browsing and a
  trending section.
- A library with search, filters, sorting and pagination.
- Prompt detail pages with copy, favourite and rating controls.

---

## 2. Technology

| Layer | Choice |
|---|---|
| Framework | Laravel 12 (PHP 8.2.12) |
| Database | MariaDB 10.4.32, managed through phpMyAdmin (XAMPP) |
| Views | Blade, semantic HTML5 |
| Styling | Hand-written CSS3 in `public/css/app.css`, no framework |
| JavaScript | Vanilla, in `public/js/app.js`, progressive enhancement only |
| AI provider | Google Gemini (text generation and embeddings) |
| Frontend build | **None.** No Vite, no npm step, no Tailwind |

There is deliberately no asset pipeline. PHP 8.2 was already installed, and every
page is server-rendered, so a static stylesheet and a single script file are
sufficient. This also removed a class of failure where a page renders but its
assets were never built.

Laravel 12 was chosen because it is the newest release this PHP version supports;
Laravel 13 requires PHP 8.3.

---

## 3. Local setup

### Prerequisites
- XAMPP with PHP 8.2+ and MariaDB/MySQL
- Composer
- The supplied `MANUAL_DATABASE_SCHEMA.sql`, `DATASET_IMPORT.sql`,
  `sample_prompts_dataset.csv` and `sample_prompt_tags.csv`

### Step 1 — Get the code running

```bash
cd F:\projects\PromptZone
composer install
cp .env.example .env       # only if .env is missing
php artisan key:generate
```

### Step 2 — Create the database by hand

The schema is **not** created by migrations. `database/migrations/` is
intentionally empty.

1. Start **MySQL** in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`.
3. Open the **SQL** tab and run `MANUAL_DATABASE_SCHEMA.sql`.

   This creates the `prompt_library` database, twelve tables and the ten starter
   categories. It uses `CREATE TABLE IF NOT EXISTS` and does not drop anything.
4. Import the sample dataset:
   - Import `sample_prompts_dataset.csv` into `dataset_prompt_import`
     (CSV, comma-separated, fields enclosed in `"`, first row contains column names).
   - Import `sample_prompt_tags.csv` into `prompt_tag_import` the same way.
5. Run `DATASET_IMPORT.sql` in the SQL tab to move the staged rows into
   `prompts`, `tags` and `prompt_tag`.

   Its verification queries should report **200** imported prompts and a non-zero
   tag relationship count.

### Step 3 — Configure the environment

`.env` must point at the manual database, and must **not** use the database drivers
Laravel ships with by default, because those tables do not exist in this schema:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prompt_library
DB_USERNAME=root
DB_PASSWORD=

# The manual schema has no sessions, cache or jobs tables.
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

# AI features are disabled while this is empty; the rest of the app still works.
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash
```

`.env` is git-ignored. Never commit it.

### Step 4 — Run

```bash
php artisan serve
```

Open `http://127.0.0.1:8000`.

> The application is served by Laravel on port 8000. It is **not** reachable
> through XAMPP's Apache on port 80, which serves `htdocs` and knows nothing about
> this project.

### Step 5 — Create the first administrator

Register through the browser, then in phpMyAdmin run:

```sql
-- Check this returns exactly the account you mean before changing anything.
SELECT id, name, email, role FROM users WHERE email = 'your-email@example.com';

UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

Registration always creates a `user`. The `role` column is not mass assignable,
so no form submission can create an administrator.

### Step 6 — Enable semantic search (optional)

```bash
php artisan embeddings:sync
```

This generates the vectors semantic search ranks against. See
[§9](#9-console-commands) for pacing notes.

---

## 4. Architecture

### Controllers — orchestration only

| Controller | Responsibility |
|---|---|
| `PromptController` | Library listing, prompt detail, CRUD, copy counting, version saving, duplicate check |
| `FavoriteController` | Favourites listing and toggle |
| `RatingController` | Rating upsert |
| `DashboardController` | Signed-in user's own summary |
| `ProfileController` | Account details and password change |
| `LandingController` | Public front page |
| `AiController` | AI endpoints (analyze / improve / suggest) |
| `Auth\*` | Registration, login, logout |
| `Admin\CategoryController` | Category CRUD, activate/deactivate |
| `Admin\UserController` | Account list and role changes |
| `Admin\PromptController` | Public-content moderation |

### Services — the logic worth explaining

| Service | Responsibility |
|---|---|
| `GeminiClient` | **The only class that performs HTTP to the AI provider.** API key header, timeouts, retry policy, and translation of provider errors into a safe `AiException`. |
| `AiPromptService` | The three AI features, their prompts, their JSON schemas, and validation of everything the model returns. |
| `EmbeddingService` | Vector generation, storage and staleness detection; cosine similarity; index coverage. |
| `PromptSearchService` | Keyword/full-text search, filters, all five sorts, and the semantic search path. Composes `visibleTo()` first, always. |
| `PromptRankingService` | The trending and Wilson expressions as SQL, plus the cached public trending list. |
| `PromptRecommendationService` | Related prompts by shared tags; co-favourite recommendations with fallbacks. |
| `PromptSimilarityService` | Duplicate detection by word-set overlap, scoped to the author's own prompts. |

### Models and relationships

Defined against the real schema, not Laravel's defaults.

| Model | Table | Notes |
|---|---|---|
| `User` | `users` | `role` is **not** fillable; `isAdmin()` |
| `Category` | `categories` | `is_active` cast to boolean |
| `Prompt` | `prompts` | Owns the shared visibility rule; counters are not fillable |
| `Tag` | `tags` | Looked up by slug, which is unique and case-insensitive |
| `Rating` | `ratings` | One row per user per prompt, enforced by a unique key |
| `PromptVersion` | `prompt_versions` | `UPDATED_AT = null`: the table has no such column |
| `PromptEmbedding` | `prompt_embeddings` | Primary key is `prompt_id`; not auto-incrementing |

Relationships:

```
User      hasMany        Prompt, Rating, PromptVersion
User      belongsToMany  Prompt   through favorites
Prompt    belongsTo      User (nullable), Category
Prompt    belongsToMany  Tag      through prompt_tag
Prompt    belongsToMany  User     through favorites
Prompt    hasMany        Rating, PromptVersion
Prompt    hasOne         PromptEmbedding
```

`favorites` and `prompt_tag` have composite primary keys and no `id` column, so
they exist **only** as many-to-many relationships. Eloquent does not support
composite keys, and a model for either table would be a permanent source of bugs.

**No model uses `SoftDeletes`**: the schema has no `deleted_at` column. Archiving
is `prompts.status = 'archived'`; deletion is a hard delete that cascades through
the foreign keys.

### The visibility rule

`Prompt::isVisibleTo()` (PHP, one row) and `Prompt::scopeVisibleTo()` (SQL, a
query) sit beside each other in the model and encode the same decision:

| Prompt | Readable by |
|---|---|
| public + active | everyone, including guests |
| public + archived | its owner and administrators (so they can restore it) |
| private | its owner only |
| private, owner deleted | nobody |

`PromptPolicy::view()` delegates to the instance method, so the detail page and
every listing cannot drift apart. The `orWhere` clauses are wrapped in closures:
without that grouping the OR would escape the surrounding AND conditions and leak
other users' private prompts through any filter.

### Policies

`PromptPolicy` is the only policy. `update` and `delete` require ownership, and a
`user_id` of `NULL` marks system content that is read-only for everybody. **No
`Gate::before()` is registered anywhere**, which is why an administrator cannot
edit or delete another user's prompt — that ability is deliberately absent rather
than accidentally forgotten.

Administration is protected by the `admin` middleware, which runs after `auth`, so
a guest is redirected to login while a signed-in non-administrator receives 403.

---

## 5. Database schema

Twelve tables, created manually from `MANUAL_DATABASE_SCHEMA.sql`.

| Table | Purpose |
|---|---|
| `users` | Accounts, bcrypt password hash, `role` |
| `password_reset_tokens` | Password reset storage |
| `categories` | Admin-managed classification, with `is_active` |
| `prompts` | Prompt data, ownership, visibility, counters |
| `tags` | Tag dictionary |
| `prompt_tag` | Pivot: prompts ↔ tags |
| `favorites` | Pivot: users ↔ prompts |
| `ratings` | One rating (1–5) per user per prompt |
| `prompt_versions` | Prior prompt text when a new version is saved |
| `prompt_embeddings` | One vector per prompt |
| `dataset_prompt_import` | CSV staging only, not an application table |
| `prompt_tag_import` | CSV staging only, not an application table |

Constraints the application depends on:

- `prompts.category_id` — `ON DELETE RESTRICT`. A category in use cannot be
  deleted; the admin screen refuses and explains, and a driver-code catch (1451)
  covers the race where a prompt is filed between the check and the delete.
- `prompts.user_id` — `ON DELETE SET NULL`. This is why user deletion would need to
  handle their prompts first: an orphaned prompt becomes `user_id IS NULL`, which
  the application treats as system content. The admin screen never deletes users.
- Child tables cascade on prompt deletion, so removing a prompt removes its tag
  links, favourites, ratings, versions and embedding.
- `ratings` is unique on `(user_id, prompt_id)`; `favorites` has that pair as its
  primary key. One rating, one favourite, enforced by the database.
- `tags.name` and `tags.slug` are unique under a **case-insensitive** collation, so
  tag lookup must go through the slug rather than blindly inserting.

---

## 6. Search, ranking and recommendation

### Keyword search

Search covers `title`, `description`, `prompt_text` and tags.

The `ft_prompts_search` index covers `(title, description, prompt_text)`, and
`MATCH(...) AGAINST (... IN BOOLEAN MODE)` is written with exactly that column
list, in that order, or MySQL cannot use the index.

Two properties of the full-text parser are handled explicitly:

- `innodb_ft_min_token_size` is 3, so one- and two-character queries are served by
  a parameterised `LIKE` fallback instead.
- Stopwords are enabled, so `AGAINST('the')` returns nothing. Each term is written
  with a trailing `*` (`the*`), which reaches past the stopword list and also makes
  partial words work: `larav` finds `laravel`.

Tags cannot be indexed by `FULLTEXT`, so a correlated `EXISTS` subquery adds tag
matches on top of the text match. On the sample data this matters: the word
`laravel` appears in one prompt, while the tag appears on six.

`LIKE` patterns have their own wildcards neutralised, so searching for `%` returns
nothing rather than everything.

### The trending score

```
                   1.5·ln(1 + copies) + 2.0·ln(1 + favourites) + rating_component
trend_score  =  ────────────────────────────────────────────────────────────────────
                               pow(2 + age_hours, 0.65)
```

where

```
rating_component = 0                                     if there are no ratings
                 = (average_rating / 5) · ln(1 + n)       otherwise
```

The age divisor is what makes this *trending* rather than *all-time*: a prompt
with the same engagement scores lower every hour, so recent activity outranks
older activity of the same size.

Implemented as a SQL expression, so ordering happens in the database and a ranked
page costs the same number of queries as an unranked one. `COALESCE` around the
rating component is load-bearing: `avg()` over no rows is `NULL`, and `NULL` would
poison the whole sum rather than contributing zero.

Worked example from the verification suite — three prompts of the same age, so the
divisor is 2.9794:

| Engagement | Numerator | Score |
|---|---|---|
| 10 copies | 1.5·ln(11) = 3.5968 | **1.207227** |
| 5 favourites | 2.0·ln(6) = 3.5835 | **1.202755** |
| three 5-star ratings | (5/5)·ln(4) = 1.3863 | **0.465289** |

The 0.0044 gap between the first two confirms the 1.5 and 2.0 weights are actually
applied.

> **Note:** all 200 sample prompts share one `created_at` and have zero
> engagement, so on a fresh import every score is exactly 0 and the sort falls back
> to id order. Copy or favourite a few prompts and the ranking engages immediately.

### The Wilson lower-bound score

A rating counts as a **positive vote when it is at least 4**; every rating is a
vote. With `n` ratings, `p = positive / n`, and `z = 1.96`:

```
              p + z²/(2n) − z·sqrt( (p(1−p) + z²/(4n)) / n )
wilson  =  ───────────────────────────────────────────────────
                            1 + z²/n
```

and `wilson = 0` when `n = 0`.

This is the *pessimistic end* of the confidence interval, not an average. It is a
ranking metric and is **not** displayed as a star rating — the interface shows the
plain average and uses Wilson only for ordering.

Worked example:

| Ratings | Wilson | Raw average |
|---|---|---|
| twenty 4-star ratings | **0.83887** | 4.0 |
| one 5-star rating | **0.20654** | 5.0 |
| none | 0.0 | — |

Ordering by Wilson puts the twenty-vote prompt first. Ordering by raw average
would put the single 5-star prompt first — exactly the behaviour the requirement
forbids.

### Related prompts

Shared-tag overlap, ranked by how many tags two prompts have in common, excluding
the prompt itself and anything the viewer may not read. A prompt with no tags has
no related prompts, and the section is hidden rather than padded with unrelated
content.

### Users also liked

Item-to-item: find the users who favourited this prompt, then recommend the other
prompts those users favourited, excluding anything the viewer has already saved.

Item-to-item rather than user-to-user, so that a guest — who has no favourites of
their own — still gets a sensible answer.

**Fallbacks, applied in order when the interaction data is too sparse:**

1. co-favourites, if any exist
2. otherwise related prompts (shared tags)
3. otherwise the trending list

The view states which source produced the list, so a fallback is never presented
as a personal recommendation.

### Duplicate warning

While composing, the draft is compared with the author's **own** prompts only —
"have I written this before" is a personal check, and searching the whole table
would leak the existence of other people's private prompts through the match list.

Similarity is the Jaccard overlap of two word sets:

```
similarity = |shared words| / |union of words|
```

A draft is reported when the title scores **≥ 0.6** or the body **≥ 0.6**. Those
thresholds were calibrated by measurement: a close paraphrase scores 0.667, while
the nearest non-duplicate scores only 0.208.

The warning is advisory. It never prevents saving, and the check runs on a debounce
as the author types.

---

## 7. AI features

### Provider configuration

The provider is Google Gemini. The key is read from `GEMINI_API_KEY` and is sent
only as the `x-goog-api-key` request header — never in a URL, a log line, an
exception message, or anything reaching the browser.

| Variable | Default | Purpose |
|---|---|---|
| `GEMINI_API_KEY` | *(empty)* | Empty disables AI features; everything else keeps working |
| `GEMINI_MODEL` | `gemini-2.5-flash` | Text generation model |
| `GEMINI_TIMEOUT` | 45 | Whole-request timeout, seconds |
| `GEMINI_CONNECT_TIMEOUT` | 10 | Connection timeout, seconds |
| `GEMINI_MAX_INPUT_LENGTH` | 12000 | Longest prompt text ever sent |
| `GEMINI_RATE_LIMIT` | 10 | AI requests per user per minute |
| `GEMINI_RETRY_ATTEMPTS` | 3 | Attempts for retryable failures |
| `GEMINI_EMBEDDING_MODEL` | `gemini-embedding-001` | Embedding model |
| `GEMINI_EMBEDDING_DIMENSIONS` | 768 | Truncated output width |

**Why `gemini-2.5-flash` and not a `latest` alias.** Measured against this key: the
`gemini-flash-latest` alias resolved to a much newer model that answered in **28
seconds** and intermittently returned HTTP 503 "high demand". `gemini-2.5-flash`
answered in **1.8 seconds**. A form button that waits half a minute is not usable.

**Why retries exist.** A 503 or 429 means the provider was busy and generated
nothing, so repeating the request costs no output. Only retryable conditions are
repeated (429, 5xx, and dropped connections); a 400 or 404 is a configuration fault
that would fail identically and is therefore not retried.

### Structured output

The provider is asked for JSON natively via `responseMimeType` and
`responseSchema`, rather than parsing JSON out of prose. Every field is still
validated and clamped on arrival, because model output is untrusted input:

- Suggested categories are re-checked against the slugs that actually exist, even
  though the schema already offers only those.
- Tag lists are trimmed, length-capped, de-duplicated case-insensitively and
  limited to five.
- The clarity score is clamped to 0–100.
- An empty rewritten prompt is treated as a failure, not as a result.

### Failing honestly

There is no code path that returns invented content. Every failure raises
`AiException` carrying a message safe to display, and the verbose provider
diagnosis goes to the log instead. The verified failure matrix:

| Condition | Attempts | Reported to the user |
|---|---|---|
| HTTP 503 | 3 | "temporarily unavailable… your text has not been changed" |
| HTTP 429 | 3 | "rate limit was reached…" |
| HTTP 400 | 1 | "rejected the request. The API key may be invalid." |
| HTTP 404 | 1 | "model was not found. Check the model setting" |
| 200, non-JSON body | 1 | "a response that could not be read" |
| 200, prose not JSON | 1 | "could not be understood, so nothing was applied" |
| 200, safety block | 1 | "did not return a usable answer… blocked by a safety filter" |
| Provider unreachable | 3 | "could not be reached. Check your connection" |
| No key configured | 0 | "AI features are not configured" |

Every one returns `{"success": false, "error": "…"}` with no `data` key, and no AI
call ever writes to the database.

### Version history

Saving an improved draft writes a `prompt_versions` row containing the text being
**superseded** — matching the schema's intent that it "stores prior prompt text when
a new version is saved" — and then updates `prompts.prompt_text`.

`version_number` and `created_by_id` are deliberately not mass assignable. They are
assigned explicitly, and `version_number` is computed as `MAX + 1` inside a
transaction with the prompt row locked; the unique key
`(prompt_id, version_number)` is the final backstop.

### Provider-agnostic structure

All provider access lives in **`GeminiClient`** and **`AiPromptService`**. No
controller or view knows the request or response shape, so changing provider means
changing those two classes. Swapping to OpenAI would also mean reading a different
response path and using a different structured-output mechanism.

### Embeddings and semantic search

| Property | Value |
|---|---|
| Model | `gemini-embedding-001` |
| Dimensions | 768 (truncated from the 3072 default) |
| Storage | `prompt_embeddings.vector_json`, one row per prompt |
| Freshness | `content_hash` = SHA-256 of the embedded text |
| Similarity | Cosine, computed in PHP |

`text-embedding-004` is not offered to this API key; `gemini-embedding-001` is.

Cosine similarity is scale-invariant, so a truncated vector needs no
re-normalisation. A stored vector whose hash, model or width no longer matches is
treated as stale and regenerated by the sync command.

**How a semantic query is ranked.** The eligible set (visibility, status and the
dropdown filters) is narrowed in SQL first, so no prompt the viewer may not read is
ever considered. Their vectors are loaded in one query, scored in PHP, and the
results are ordered and paginated manually.

**Why the cut-off is relative, not absolute.** The 200 sample prompts are generated
from a single template, so any two of them already score about 0.39 and the best
match for a real query reaches only about 0.58 — the entire range is 0.19 wide.
Measured on one query, an absolute floor of 0.55 kept 1 prompt, 0.50 kept 4 and
0.45 kept 46; the number was choosing a result count, not a relevance level. The
implementation therefore keeps prompts **within 0.85 of the best match's score**,
which adapts per query, with a low absolute floor (0.30) so that a query resembling
nothing returns nothing.

The interface reports how much of the library is indexed, so a partly built index
is never presented as a complete semantic search, and it falls back to keyword
results with an explicit notice if the query cannot be embedded.

---

## 8. Security model

| Concern | Approach |
|---|---|
| Passwords | Laravel's hashing (bcrypt, cost 12). Plaintext never stored |
| Sessions | Native session guard; regenerated on login and on password change |
| CSRF | Enabled on all `POST`/`PUT`/`PATCH`/`DELETE` routes; logout is POST-only |
| SQL injection | Eloquent and bound parameters throughout; the only `whereRaw` calls use expressions built from constants, never user input |
| XSS | Blade escapes by default; model output reaches the DOM via `textContent`, never `innerHTML` |
| Mass assignment | `role`, ownership, counters and moderation flags are **not** fillable and are assigned explicitly by server-side code |
| Authorisation | Ownership by policy; administration by middleware running after `auth` |
| Private data | One shared visibility rule, applied as the first condition of every listing, search, recommendation and moderation query |
| Secrets | Only in `.env`, which is git-ignored. Never in Blade, JavaScript or a committed file |
| Rate limiting | Login attempts (5 per email + IP) and AI endpoints (10 per user per minute) |
| AI input | Only text the signed-in user explicitly submits is sent. No lookup by id, so an AI action cannot target another account's prompt |

Two deliberate choices worth stating:

- **Reading a prompt the viewer may not see returns 404, not 403.** A 403 would
  confirm that a private prompt with that id exists.
- **The detail page and the interaction endpoints share one rule.** An archived
  prompt returns 404 to the public but stays reachable for its owner and for
  administrators, who need it in order to restore it.

---

## 9. Console commands

```bash
# Generate or refresh the embeddings semantic search ranks against.
php artisan embeddings:sync

# Rebuild every vector, for example after changing model or dimensions.
php artisan embeddings:sync --force

# Bound a run.
php artisan embeddings:sync --limit=50
```

### Pacing

`batchEmbedContents` is billed **per text, not per call**, and this key allows
roughly **100 embedding items per minute** (measured: 8 then 50 succeeded, the next
50 returned 429). The command therefore sends 20 prompts every 15 seconds rather
than firing 200 at once, which simply fails. A full sync of 201 prompts takes about
three minutes.

Only prompts whose text has changed are sent, so re-running is cheap; an up-to-date
library reports that there is nothing to do.

---

## 10. Verification and testing

The project was built in ten phases, each verified against the live database before
moving on. Verification mixed HTTP assertions, direct SQL inspection, hand-computed
expected values, and a local stub server returning scripted responses so every
provider failure path could be exercised deterministically.

Highlights that were confirmed rather than assumed:

- Pagination integrity: all 200 prompts returned exactly once across 17 pages, with
  zero duplicates and zero gaps.
- Query counts: a library page costs 4–5 queries regardless of how many cards it
  renders; recommendation strips are constant at 5–6 queries for any number of
  cards. No feature introduced an N+1.
- Private-prompt isolation: a second user and a second administrator both receive
  404 for another account's private prompt, and it appears in neither their search
  results nor their recommendations.
- The trending and Wilson expressions match hand-computed values to five decimal
  places.
- Every AI failure mode returns a specific message, no fabricated content, and
  writes nothing.

Three problems found by that verification are worth recording, because all were
silent:

1. **Dropped aggregate columns.** `select('prompts.*')` *replaces* the select list,
   so the relevance sort silently dropped the `withCount`/`withAvg` columns and
   every card rendered as 0 favourites. No error, just wrong numbers. Fixed by
   adding base columns only when nothing has selected them yet.
2. **An off-screen duplicate warning.** The warning rendered correctly but 276px
   above the field being typed into — entirely outside the viewport on a 1501px
   form. A warning nobody can see is not a warning. Fixed by placing it beneath the
   field and scrolling it into view when it appears.
3. **Assets with no cache validators.** `app.js` and `app.css` were served with no
   `Cache-Control`, `ETag` or `Last-Modified` header and referenced by unversioned
   URLs, so a browser could keep running a stale script after an edit. Asset URLs
   now carry a modification stamp.

The manual demonstration checklist is in
[`docs/VIVA_DEMO_CHECKLIST.md`](docs/VIVA_DEMO_CHECKLIST.md).

---

## 11. Known limitations

These are stated deliberately. They are the honest boundaries of the prototype and
belong in the report rather than being discovered in the viva.

### Semantic search does not scale

**Cosine similarity is computed in PHP over the candidate set, which is a linear
scan — O(n) per query.** For the ~200 prompts here that costs milliseconds, but it
would not survive a library of tens of thousands. A production implementation needs
a dedicated vector index (pgvector, FAISS, Qdrant, or a managed vector service)
capable of approximate nearest-neighbour search, and candidate narrowing would move
into that index rather than being done with an `id IN (...)` list.

The dataset size is also why no vector index was added: at this scale it would add
infrastructure without changing any measured result.

### The sample dataset is synthetic and template-generated

The 200 demonstration prompts were produced from one template. Two consequences:

- Keyword search behaves unusually well, because the vocabulary is narrow.
- Cosine similarities are compressed into a narrow band (§7), which is why the
  semantic cut-off is relative rather than absolute.

It is demonstration data, not a research corpus.

### Retrieval quality is not formally evaluated

No precision/recall figures are claimed for search or recommendations. Producing
them would require a labelled relevance set, which was out of scope. The AI
analyzer and improver were inspected by hand on a small sample; no automated
quality metric is asserted.

### Trending on a fresh import is flat

Every seeded prompt shares one `created_at` and has zero engagement, so all trend
scores are 0 and the order falls back to id. The formula is correct — verified
numerically — but it needs real interaction data to produce a meaningful ranking.

### Other boundaries

- **Single provider.** Only Gemini is integrated. The code is structured so a change
  means editing `GeminiClient` and `AiPromptService`, but no second provider is
  implemented or tested.
- **AI output is unverified by nature.** The interface says so; the analyzer and
  improver can be wrong, and the user is asked to review before applying.
- **The cached trending list is global and public.** It is computed over public,
  active prompts only and is the same for every visitor; the library's trending
  sort is evaluated live and is visibility-aware. That difference is intentional.
- **Password change does not sign other devices out.** `SESSION_DRIVER=file` gives
  no queryable session store to revoke, and the previous session is not destroyed.
  Single-session enforcement would need the database session driver plus
  `AuthenticateSession`.
- **Database sessions, cache and queues are not used**, because the manual schema
  contains no such tables. The application is configured for `file` sessions,
  `file` cache and the `sync` queue.
- **The duplicate check compares word sets.** A paraphrase reusing little
  vocabulary can score below the threshold and go unreported. It is advisory by
  design.
- **Assets carry a `filemtime` version stamp rather than a content hash**, which is
  sufficient here but would be replaced by a build manifest in a compiled setup.

---

## Appendix — project layout

```
app/
  Console/Commands/     SyncEmbeddings
  Exceptions/           AiException
  Http/
    Controllers/        Prompt, Favorite, Rating, Dashboard, Profile, Landing, Ai
      Admin/            Category, User, Prompt (moderation), Dashboard
      Auth/             RegisteredUser, AuthenticatedSession
    Middleware/         EnsureUserIsAdmin
    Requests/           Prompt, Ai, SaveVersion, DuplicateCheck, Profile*, Admin/Category
  Models/               User, Category, Prompt, Tag, Rating, PromptVersion, PromptEmbedding
  Policies/             PromptPolicy
  Providers/            AppServiceProvider  (policy, paginator view, AI rate limiter)
  Services/             GeminiClient, AiPromptService, EmbeddingService,
                        PromptSearchService, PromptRankingService,
                        PromptRecommendationService, PromptSimilarityService
config/
  prompts.php           Prompt vocabularies, input limits, ranking and AI thresholds
  services.php          Gemini and embedding configuration
database/migrations/    Intentionally empty: the schema is created by hand
docs/                   VIVA_DEMO_CHECKLIST.md
public/
  css/app.css           The whole design system, hand-written
  js/app.js             Copy, delete confirmation, AI panel, duplicate warning
resources/views/
  layouts/              app.blade.php
  library/              index, filters partial
  prompts/              create, edit, show, card / form / ai-panel / recommendations partials
  favorites/ profile/ admin/ auth/ errors/ partials/
routes/web.php          Public, guest, authenticated, AI and admin route groups
```
