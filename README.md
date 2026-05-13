# gaze-ghostwriter

AI-assisted support-mail ghostwriter for Laravel — IMAP inbound, RAG-augmented draft generation, and PII-safe LLM calls through `empiretwo/gaze-laravel`.

[![CI](https://github.com/EmpireTwo/gaze-ghostwriter/actions/workflows/ci.yml/badge.svg)](https://github.com/EmpireTwo/gaze-ghostwriter/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/empire2/gaze-ghostwriter.svg)](https://packagist.org/packages/empire2/gaze-ghostwriter)
[![License](https://img.shields.io/github/license/EmpireTwo/gaze-ghostwriter.svg)](LICENSE)

`gaze-ghostwriter` watches a support inbox, generates structured draft replies through the Laravel AI agent contract, and persists every prompt + response so you can review, edit, send (SMTP), or escalate to GitHub. Every outbound LLM call goes through a single boundary (`GuardedAgentRunner`) that runs `gaze clean` / `gaze restore` around the model invocation — placeholder tokens never leak into stored fields.

## Requirements

- PHP `^8.3` (`laravel/ai` requires PHP 8.3+)
- Laravel `^12.0` (`laravel/ai` requires Laravel 12+)
- Livewire `^4.0`
- `empiretwo/gaze-laravel` (auto-installed)
- `laravel/ai` provider configured in the host (`config/ai.php` keys `default` and `default_for_embeddings`)

## Install

```bash
composer require empire2/gaze-ghostwriter

php artisan vendor:publish --tag=gaze-ghostwriter-config
php artisan vendor:publish --tag=gaze-ghostwriter-migrations
php artisan migrate
```

Composer will pull `empiretwo/gaze-laravel` automatically; the gaze CLI binary is downloaded into `vendor/bin/gaze` by its bundled installer plugin (Composer asks you to trust the plugin once).

Optional publishes:

```bash
php artisan vendor:publish --tag=gaze-ghostwriter-views
php artisan vendor:publish --tag=gaze-ghostwriter-prompts
```

## Configuration

Edit `config/gaze-ghostwriter.php`. The most important keys:

```php
'enabled'      => env('GHOSTWRITER_ENABLED', true),
'gaze_enabled' => env('GHOSTWRITER_GAZE_ENABLED', false), // PII boundary on/off
'user_model'   => \App\Models\User::class,                // Host User model
'layout'       => 'components.layouts.app',                // Blade layout for admin pages
'middleware'   => ['web', 'auth'],                         // Add 'role:admin' etc. here
'route_prefix' => 'ghostwriter',
```

Environment variables (subset):

| Variable | Purpose |
|---|---|
| `GHOSTWRITER_ENABLED` | Master switch |
| `GHOSTWRITER_GAZE_ENABLED` | Turn on the Gaze PII boundary |
| `GHOSTWRITER_LOCALE` | Fallback language (`de` default) |
| `GHOSTWRITER_SUPPORT_ADDRESSES` | Comma-separated `support@example.com,help@example.com` |
| `GHOSTWRITER_IMAP_HOST` / `_PORT` / `_USERNAME` / `_PASSWORD` | Webklex IMAP credentials |
| `GHOSTWRITER_IMAP_FOLDER` / `_EXTRA_FOLDERS` | Inbox + extra folders to sync (e.g. `Sent`) |
| `GHOSTWRITER_IMAP_LOOKBACK_DAYS` / `_FETCH_LIMIT` | Sync window |
| `GHOSTWRITER_IMAP_ONLY_CONVERSATION_WITH_EMAIL` | Filter to a single counterparty |
| `GHOSTWRITER_OPENAI_CHAT_MODEL` | Default `gpt-4o-mini` |
| `OPENAI_ADMIN_KEY` / `OPENAI_MONTHLY_BUDGET` | Optional cost reporting in the admin UI |
| `GITHUB_REPO` / `GITHUB_TOKEN` / `GHOSTWRITER_GITHUB_LABELS` | GitHub issue export |
| `GHOSTWRITER_SMTP_HOST` / `_PORT` / `_USERNAME` / `_PASSWORD` / `_DRIVER` | Outbound SMTP for replies |
| `GHOSTWRITER_REPLY_FROM_ADDRESS` / `_FROM_NAME` | From address |

## Host integration

### User model

Add the bundled trait so the package can resolve the user's signing name and reply signatures:

```php
use Empire2\GazeGhostwriter\Concerns\HasGhostwriterUserData;

class User extends Authenticatable
{
    use HasGhostwriterUserData;
    // ...
}
```

The trait declares `ghostwriterUserData(): HasOne` against the package's
`GhostwriterUserData` model. The relation name is fixed because the package
calls it directly.

### Authorization

The package routes default to `['web', 'auth']`. Lock them down with role middleware (e.g. Spatie Permission):

```php
// config/gaze-ghostwriter.php
'middleware' => ['web', 'auth', 'role:admin|super-admin'],
```

### Layout override

Replace the layout used by the bundled Livewire admin pages:

```php
'layout' => 'layouts.admin',
```

### Toast UI

The Livewire components dispatch a `toast` event with `type`, `message`, `heading`, and `duration`. You can either:

1. Use the bundled minimal toast component — drop `<livewire:gaze-ghostwriter.toast />` into your layout. Tailwind utility classes only.
2. Replace it with your own listener (e.g. Flux UI, Filament). The dispatched event is fully data — the package never imports a host-specific class.

### Customer / ticket integration

The bundled `partials/draft-smart-actions.blade.php` view contains optional links into a customer detail page and a ticket system. Both are guarded by config:

```php
'routes' => [
    'customer_show' => 'admin.customers.show',
    'ticket_show'   => 'admin.tickets.show',
    'ticket_board'  => 'admin.tickets.board',
],
'ticket_model' => \App\Models\Ticket::class,
```

If the configured route name is not registered or the model class does not exist, the link falls back to `#` and the ticket section stays empty — the package never crashes when these are absent.

## Web feedback channel

In addition to the IMAP/SMTP support inbox, the package ships a drop-in Livewire form so that logged-in users or guests can send feedback directly from your frontend. Submissions land in the same Drafts overview, marked with a `WWW` pill (vs. `MAIL` for email-sourced messages).

### Enable

1. Open the Ghostwriter admin → **Settings** → **Feedback-Kanal**.
2. Toggle **Feedback-Formular aktivieren**.
3. Optionally configure:
   - **Betreff-Feld einblenden und verpflichten** — show + require a subject input.
   - **E-Mail bei Gast-Feedback verlangen** — when off, guests can submit without an email (those submissions become reply-orphans; the Reply button is disabled for them).
   - **Themen** — optional dropdown values (e.g. `Bug`, `Feature`, `Billing`).
   - **Rate-Limit pro Minute / IP** — per-IP submissions per minute (default `5`).

### Embed

Drop the component anywhere in your host Blade:

```blade
<livewire:gaze-ghostwriter-feedback-form />
```

That's it — no JavaScript, no extra route, no config file changes. The component:

- resolves `Auth::user()` automatically and captures `id`, `email`, `name` snapshot (visible in the draft detail panel),
- includes a hidden honeypot field and a per-IP rate limiter,
- writes a `SupportMailMessage` row with `channel='web'` and dispatches an immediate draft job,
- surfaces the new draft in the existing overview with a teal `WWW` pill.

Replies go out through the same SMTP path as email-sourced drafts.

## Quick start

```php
use Empire2\GazeGhostwriter\Jobs\ProcessGhostwriterInboxJob;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;

// 1. Pull new mail and generate drafts (production: enqueue on a schedule).
ProcessGhostwriterInboxJob::dispatch();

// 2. Generate a draft for a single message ad-hoc.
$service = app(DraftGeneratorService::class);
$draft   = $service->generateForMessage(SupportMailMessage::find($id));
```

The `GuardedAgentRunner` runs Gaze around the LLM call. With `GHOSTWRITER_GAZE_ENABLED=true` the prompt is sanitized via `gaze clean`, the model only sees redacted text, and every string in the structured response is restored before persistence. With `GHOSTWRITER_GAZE_ENABLED=false` the runner short-circuits with `GazeDisabledException` — there is no bypass branch.

## Privacy boundaries

This package routes every text prompt and structured LLM response through
the [`empiretwo/gaze-laravel`](https://packagist.org/packages/empiretwo/gaze-laravel)
boundary. With `gaze_enabled=true`, prompts are passed through `gaze clean`
before they reach the model, and the `restore` step puts placeholder tokens
back into the model output before persistence.

**Image attachments are NOT redacted.** Gaze is a text-only boundary.
When a ticket / draft includes screenshots or other image attachments, they
are sent to the configured AI provider as-is. Treat image upload as
out-of-band PII exposure and disable image attachments if your compliance
posture forbids it.

**Embeddings** are sent through `Gaze::clean()` only (no restore — the
vectors are stored, not shown back to the user). When the boundary is off,
the embedding path is skipped entirely (fail-closed). Both the per-chunk
indexing path (`ChunkEmbeddingService`) and the per-query RAG retrieval
path (`DraftGeneratorService`) follow this rule — RAG recall degrades
rather than leaks PII.

**GitHub issue export** runs the inbound mail body through the same
`Sanitizer` (`Gaze::clean()` only). When the boundary is off, the host's
own heuristics take over (or the export is skipped depending on the
`ai_sanitize_mail_body` flag).

**Outbound SMTP** sends the agent-restored draft text. The draft body
persisted in `support_drafts.draft_body` is the post-`Gaze::restore()`
string; SMTP transmits whatever the human reviewer (or the agent) produced
after the restore step. There is no second redaction pass before send —
review the draft before clicking send.

## Console commands

```bash
php artisan ghostwriter:imap-test            # verify IMAP credentials, list folders
php artisan ghostwriter:reprocess-html-bodies # rebuild plain text from HTML bodies
```

## Admin UI routes

Mounted under the configured `route_prefix` (default `/ghostwriter`):

| Path | Component |
|---|---|
| `/` | Drafts list |
| `/drafts/{draft}` | Draft detail |
| `/settings` | Signing profile, IMAP/SMTP diagnostics, scheduler pause |
| `/prompt-editor` | Global + per-user additional prompt rules |
| `/prompt-history` | Token + cost history |
| `/smart-actions` | Smart-action marker manager |
| `/gaze-log` | Per-draft Gaze invocation log |

The bundled views use `flux:` UI primitives (Flux UI by Livewire). Hosts not on Flux UI can publish the views and replace the components with their own primitives — the Livewire backing classes don't depend on Flux.

## Testing

```bash
composer test
composer analyse
composer format
```

Some bundled tests reference host-specific fixtures (User factory, Customer / Artist / Release / Ticket models, `App\Features\GhostwriterGaze`) — these are marked with a `GHOSTWRITER-TODO` comment at the top and will not pass until you provide local fixtures. Tests without such markers (e.g. `CosineSimilarityTest`, `MailReplyHistorySplitterTest`, `PlaceholderSentinelTest`, etc.) are pure utility tests that should pass out of the box once `composer install` finishes.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT — see [LICENSE](LICENSE).
