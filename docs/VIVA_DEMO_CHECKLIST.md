# Viva / Demonstration Checklist

A five-minute script for presenting the AI Prompt Library & Manager, plus the
preparation, fallbacks and answers that keep a live demonstration from going wrong.

---

## 0. Fifteen minutes before — preparation

Do these first. A live demo fails on preparation, not on features.

| # | Check | Why |
|---|---|---|
| 1 | **XAMPP: MySQL is running** (Apache is not needed) | No MySQL means nothing renders |
| 2 | `cd F:\projects\PromptZone` then `php artisan serve` | Serves the app on `http://127.0.0.1:8000`. **Not** port 80 |
| 3 | Open `http://127.0.0.1:8000` and confirm the landing page loads | Catches a down server immediately |
| 4 | Confirm the AI panel appears on **Create prompt** | If it says "AI features are not configured", `GEMINI_API_KEY` is missing from `.env` |
| 5 | **Warm the AI up.** Open *Create prompt*, paste any text, press **Analyze**, and wait for a result | The first call of the day is the slowest. Doing it now means the live one is fast |
| 6 | Confirm `prompt_embeddings` is populated: the library shows *"Ranked by meaning against 201 of 201"* when semantic search runs | If it says fewer, run `php artisan embeddings:sync` (takes ~3 minutes) |
| 7 | Have **two accounts ready**: your admin, and one normal user | Registering live costs 30 seconds you may not have |
| 8 | Have a **private prompt** ready on your admin account | To demonstrate that a second user cannot see it |
| 9 | Note one prompt you will copy and rate, and click into it once | Pre-loads it into the browser cache |
| 10 | Close every unrelated tab and notification | Only this app should be visible |

### If you want the trending and "highest rated" sorts to look alive

They are correct but flat on a fresh import: all 200 sample prompts share one
`created_at` and have zero engagement, so every trend score is 0.

Spend two minutes before the demo:

1. Copy three or four prompts from the library.
2. Favourite two of them.
3. Rate one prompt 5 stars from your admin account and 4 stars from your second account.

Then `?sort=trending` and `?sort=highest_rated` both produce a real ranking. If you
skip this, do not open those sorts live — say instead: *"the formula is verified
numerically; on a fresh import every score is zero because there is no engagement
data yet."* That is an acceptable and honest answer.

---

## 1. The five-minute script

Timings assume you keep moving. Beats marked *(optional)* can be dropped if you are
behind.

### Beat 1 — 0:00–0:30 · Guest view and the landing page

**Do:** Open `http://127.0.0.1:8000` in a normal window, **not logged in**.

**Say, pointing at the page:** *"This is the public landing page. Every number here
is counted from the database, not hardcoded — the prompt total, the category count
and the lifetime copy count. Featured prompts come from an administrator moderation
flag, and the trending section is ranked by a documented formula."*

**Then:** Scroll to the categories and click one.

*Optional:* If nothing has been featured, point at the empty state and say *"when
nothing is featured the section explains how to feature a prompt rather than
inventing content."*

---

### Beat 2 — 0:30–1:10 · Search, filters and a privacy guarantee

**Do:** In the library, type `laravel` in the Search box and press **Apply**.

**Say:** *"Search covers the title, description, prompt text **and** tags. This query
finds seven prompts: one because the word appears in its text, six more because they
carry the tag. Full-text search cannot index tags, so a relationship subquery adds
those matches on top."*

**Then:** Add **Language: English** and change **Sort by** to **Most copied**.
Press Apply.

**Say:** *"Filters compose, and the URL carries the whole state, so the page is
shareable and paging keeps it."*

**Then, crucially:** Point out that a private prompt does **not** appear. Say:
*"Everything here passes one shared visibility rule before any filter is applied."*

---

### Beat 3 — 1:10–1:40 · Register and log in *(optional if short on time)*

**Do:** Log out, then **Register** a new account and land on the dashboard.

**Say:** *"Registration hashes the password with bcrypt and cannot create an
administrator — the role column is not mass assignable, so no form submission can
escalate a privilege."*

**Then:** Point at the dashboard stat cards. *"Each count leads somewhere; none of
these are dead numbers."*

---

### Beat 4 — 1:40–2:40 · The AI improver and version history

This is the centrepiece. Do it deliberately.

**Do:** **Create prompt**. Type a deliberately vague prompt, for example:

- Title: `Write about dogs`
- Prompt text: `Write about dogs.`

**Then:** Press **Improve** in the AI panel and wait.

**Say while it works:** *"This sends only the text in the box to Gemini, with a JSON
schema so the reply is structured rather than prose. Nothing is applied
automatically."*

**When the draft appears, point at it:** *"Here is the rewritten prompt, and a list
of what changed. Note it has not touched my form — there are two buttons, and I
choose."*

**Then:** Press **Use this draft**. The textarea fills but nothing is saved.

**Show the warning is real:** Press **Improve** once more, or simply point at the
warning text: *"It also tells me AI output can be wrong and that I should review it.
There is no code path that fabricates a result: if the provider fails I get a
specific error and my text is untouched."*

**Then:** Fill in a category and press **Save prompt**, then reopen it and press
**Edit**. Use the AI panel again, then press **Save as new version**.

**Say:** *"The text I replaced is kept in the version history — that is what the
`prompt_versions` table is for — and the prompt now holds the new text."*

*Optional, if asked:* mention that `version_number` is computed as `MAX + 1` inside a
transaction with the row locked, and is not mass assignable, so a client cannot forge
it.

---

### Beat 5 — 2:40–3:20 · Engagement and recommendations

**Do:** Open any prompt from the library.

**Then, in order:**
1. Press **Add to favorites**, reload, and show it is now **Saved to favorites**.
   *"Idempotent: the composite primary key makes a duplicate impossible."*
2. Click **Copy prompt**. *"The clipboard is written first and the server is told
   second, so the counter only counts copies that actually happened. The client
   cannot set the number."*
3. Select 4 stars and press **Save rating**. Then change to 5 and save again.
   *"Still one row — a unique key on user and prompt, and the second save updates
   rather than inserts."*

**Then:** Scroll down. *"Related prompts are by shared-tag overlap; every one of them
also passed the visibility rule."*

---

### Beat 6 — 3:20–4:10 · Semantic search

**Do:** Go to the library. Search `how do I write better emails` with
**Semantic search** unticked. Note the result count.

**Say:** *"Keyword search returns 16 prompts. It found the word 'email' but it has no
idea what I meant."*

**Then:** Tick **Semantic search** and press **Apply**.

**Say:** *"Now it returns 9, ranked by meaning: a professional email, a service
recovery email, an email welcome sequence. None of that came from matching my
words."*

**Then:** Point at the coverage line above the results. *"It states how much of the
library is indexed, so a partly built index is never presented as a complete one."*

**Then:** Run a query that resembles nothing, and show the honest empty result.

*If asked how it works:* *"The query is embedded with `gemini-embedding-001` at 768
dimensions, and compared with each candidate by cosine similarity — computed in PHP,
which is a linear scan and would not scale past a few thousand prompts. A real
deployment needs a vector index."*

---

### Beat 7 — 4:10–5:00 · Administration and the safeguards

**Do:** Log in as your admin and go to **Admin → Moderation**.

**Say:** *"Only public prompts appear here. Another user's private prompt is not
listed and cannot be edited or deleted — no `Gate::before` is registered anywhere,
so an administrator has no implicit superpower."*

**Then:** Press **Feature** on a prompt.

**Then:** Go to **Admin → Categories**

**Say:** *"Watch this."* Press **Delete** on a category that has prompts.

**Say:** *"Refused. The schema declares `ON DELETE RESTRICT`, so the schema itself
prevents it. It tells me to deactivate instead, which is the reversible option."*

**Then:** Press **Deactivate** on the same category and show that prompts are
unaffected.

**Then (if time):** Go to **Admin → Users** and attempt to change your own role to
*User*.

**Say:** *"Blocked — you cannot remove the final administrator, because nobody could
grant the role back."* If there is a second admin, first demote them, then try your
own role to show the block.

**Close with:** *"That is the full stack: public browsing, server-enforced privacy,
AI assistance that never fabricates, and administration with the destructive
operations either refused or reversible."*

---

## 2. When something goes wrong

### The AI call is slow or fails

The provider occasionally returns **503 "high demand"**. The application retries
three times automatically, so a message usually means it is genuinely busy.

**Do not panic and do not apologise for it.** Press the button again; it almost
always succeeds on the next attempt. Then say:

> *"The provider is momentarily busy. The application retried three times and then
> reported it clearly rather than inventing an answer — which is the behaviour I
> wanted. My text is untouched."*

That turns a hiccup into a demonstration of correct failure handling. Keep the
[§7 failure matrix](../README.md#7-ai-features) in mind: every failure mode has a
specific message and no fabricated content.

### The AI says "not configured"

`GEMINI_API_KEY` is missing or empty in `.env`. Fix it and reload. Everything else
still works, which is itself worth saying: *"the application degrades to plain CRUD
and search."*

### Trending or highest-rated looks arbitrary

Expected on an untouched database — every score is 0. See the preparation section
above. Either pre-load some engagement, or explain it as stated there.

### Semantic search returns nothing

The index may be empty. The page says so explicitly: *"Run `php artisan
embeddings:sync` to index them."* Either run it (about three minutes) or say: *"the
mechanism is verified; the index simply has not been built on this copy."*

### A 419 "Page Expired"

The session or CSRF token expired. Reload the page and repeat the action.

### You are asked to show a page that is not working

Stay with what works and say what you would check. Never claim a feature works
without showing it.

---

## 3. Likely questions and prepared answers

**"Where is the database schema defined?"**
In `MANUAL_DATABASE_SCHEMA.sql`, applied by hand through phpMyAdmin. There are no
migrations — `database/migrations/` is deliberately empty — and the schema is the
source of truth the models were written against.

**"Why is there no React or Vue?"**
The requirement was Blade with small amounts of vanilla JavaScript. Every page is
server-rendered, so JavaScript is used only where it genuinely helps: the clipboard,
confirmation dialogs, the AI panel and the duplicate warning. All of it is
progressive enhancement; every control works with JavaScript disabled.

**"What happens if the AI provider goes down?"**
A specific error is shown and nothing is written. There are nine tested failure
paths, each with its own message, and no code path returns invented content. Retries
happen only for conditions where the provider generated nothing, so a retry costs no
tokens.

**"How do you stop one user seeing another's private prompt?"**
One rule, in one place: `Prompt::scopeVisibleTo()` for queries and
`Prompt::isVisibleTo()` for a single row, with the policy delegating to the latter
so the detail page and listings cannot diverge. It is the first condition of every
listing, search, recommendation and moderation query. Reading a prompt you may not
see returns 404 rather than 403, because 403 would confirm the id exists.

**"Can an administrator read a user's private prompt?"**
No. No `Gate::before` is registered, so the standard CRUD routes refuse an
administrator exactly as they refuse anyone else, and the moderation screen lists
public prompts only. That was verified with a second administrator account.

**"How does the trending formula work?"**
`(1.5·ln(1+copies) + 2.0·ln(1+favourites) + rating_component) / pow(2 + age_hours, 0.65)`,
where the rating component is `(average/5)·ln(1+rating_count)` or 0 with no ratings.
The age divisor is what makes it trending rather than all-time. It is implemented as
a SQL expression so ordering happens in the database, and verified against
hand-computed values to five decimal places.

**"Why Wilson and not the average rating?"**
The lower bound is the pessimistic end of the confidence interval, so one 5-star
rating cannot outrank twenty consistent 4-star ratings. Measured: Wilson scores
those 0.839 and 0.207; a raw average would order them the wrong way round.

**"Does semantic search scale?"**
No, and the README says so. Cosine similarity is computed in PHP over the candidate
set, which is a linear scan. At 200 prompts that is milliseconds; at tens of
thousands it needs a real vector index such as pgvector or Qdrant.

**"What was the hardest bug?"**
Three worth naming, all of which were silent:

1. `select('prompts.*')` **replaces** the select list, so the relevance sort had
   silently dropped the `withCount`/`withAvg` columns and every card showed 0
   favourites. No error — just wrong numbers.
2. The duplicate warning rendered correctly but 276px above the field being typed
   into, entirely off-screen. A warning nobody can see is not a warning.
3. Assets had no cache validators, so a browser could keep running a stale script
   after an edit and a new feature appeared to be missing.

**"What would you do with more time?"**
Add automated feature tests against a separate test database, move semantic search
to a vector index, formally evaluate retrieval quality with a labelled relevance
set, and add the AI improve workspace as its own page rather than a panel on the
form.

---

## 4. After the demo

- Log out of the admin account.
- Delete any throwaway prompts you created — they are private by default, so they
  were never visible to anyone else.
- If you want the database back to a clean state:
  ```sql
  DELETE FROM favorites;
  DELETE FROM ratings;
  UPDATE prompts SET copy_count = 0, view_count = 0, is_featured = 0;
  UPDATE prompts SET status = 'active';
  ```
  Everything else you created is additive and harmless.

---

## 5. One-page quick reference

| What to show | Where | What it proves |
|---|---|---|
| Live counts, featured, trending | `/` | Database-backed, nothing hardcoded |
| `laravel` search → 7 results | `/library` | Full-text **plus** tag search |
| Private prompt absent | `/library` | Shared visibility rule |
| Register → dashboard | `/register` | Hashed passwords, no role escalation |
| Improve a vague prompt | Create/Edit | Real AI, structured output |
| "Nothing applied yet" | AI panel | User consent, no silent overwrite |
| Save as new version | Edit → Save as new version | Version history, explicit assignment |
| Favorite twice, rate twice | Prompt detail | Idempotent; unique keys |
| Copy prompt | Prompt detail | Server-side counter |
| Related prompts | Prompt detail | Shared-tag overlap, visibility-scoped |
| Semantic search on/off | `/library` | Cosine similarity vs keywords |
| Delete a used category | Admin → Categories | `ON DELETE RESTRICT` |
| Demote the last admin | Admin → Users | Last-admin safeguard |
| Admin cannot edit a private prompt | Admin → Moderation | No implicit superpower |
