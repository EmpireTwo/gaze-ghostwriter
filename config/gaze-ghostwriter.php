<?php

declare(strict_types=1);

use App\Models\User;
use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Agents\GhostwriterTranslatorAgent;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | Disabling this short-circuits the inbox processor and most public APIs.
    */

    'enabled' => env('GHOSTWRITER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Gaze (PII boundary) integration
    |--------------------------------------------------------------------------
    | When false, GuardedAgentRunner refuses to run and the host falls back
    | to its own configuration (or returns null in places that allow it).
    | The package provides Empire2\GazeGhostwriter\Features\GhostwriterGaze for
    | feature-flag wiring; this config key is the simple env override.
    */

    'gaze_enabled' => filter_var(env('GHOSTWRITER_GAZE_ENABLED', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Host integration seams
    |--------------------------------------------------------------------------
    | user_model: Eloquent model used for foreign-key relations (`belongsTo`)
    |             and Auth::user() type expectations. Must implement
    |             Illuminate\Contracts\Auth\Authenticatable. The host model
    |             SHOULD also `use HasGhostwriterUserData` so the
    |             `ghostwriterUserData()` relation is available.
    | layout:     Blade layout used by the bundled Livewire admin pages.
    | middleware: Middleware stack the package routes mount under. Add
    |             `role:admin|super-admin` (Spatie Permission) etc. here.
    | route_prefix: URL prefix the package mounts under.
    */

    'user_model' => env('GHOSTWRITER_USER_MODEL', User::class),

    'layout' => env('GHOSTWRITER_LAYOUT', 'components.layouts.app'),

    'middleware' => ['web', 'auth'],

    'route_prefix' => env('GHOSTWRITER_ROUTE_PREFIX', 'ghostwriter'),

    /*
    | Optional host-route names referenced by the bundled views. The package
    | falls back to '#' if the host has not registered these routes.
    */

    'routes' => [
        'customer_show' => env('GHOSTWRITER_ROUTE_CUSTOMER_SHOW', 'admin.customers.show'),
        'ticket_show' => env('GHOSTWRITER_ROUTE_TICKET_SHOW', 'admin.tickets.show'),
        'ticket_board' => env('GHOSTWRITER_ROUTE_TICKET_BOARD', 'admin.tickets.board'),
    ],

    /*
    | Optional Eloquent model class for ticket integration in the smart-actions
    | partial. When set to a fully-qualified class name that exists, ticket
    | links related to the draft / customer are rendered.
    */

    'ticket_model' => env('GHOSTWRITER_TICKET_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Locale / language
    |--------------------------------------------------------------------------
    | Default language for drafts when the customer mail provides no signal.
    */

    'locale' => env('GHOSTWRITER_LOCALE', 'de'),

    /*
    |--------------------------------------------------------------------------
    | Support inboxes
    |--------------------------------------------------------------------------
    | Comma-separated list (matched against To/Cc, case-insensitive).
    */

    'support_addresses' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GHOSTWRITER_SUPPORT_ADDRESSES', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | IMAP inbound (Webklex)
    |--------------------------------------------------------------------------
    | When fetch_without_setting_seen is true (default), BODY.PEEK semantics
    | are used so messages typically remain "unread" on the server.
    */

    'imap' => [
        'host' => env('GHOSTWRITER_IMAP_HOST', ''),
        'port' => (int) env('GHOSTWRITER_IMAP_PORT', 993),
        'encryption' => env('GHOSTWRITER_IMAP_ENCRYPTION', 'ssl'),
        'validate_cert' => filter_var(env('GHOSTWRITER_IMAP_VALIDATE_CERT', true), FILTER_VALIDATE_BOOL),
        'username' => env('GHOSTWRITER_IMAP_USERNAME', ''),
        'password' => env('GHOSTWRITER_IMAP_PASSWORD', ''),
        'folder' => env('GHOSTWRITER_IMAP_FOLDER', 'INBOX'),
        'lookback_days' => (int) env('GHOSTWRITER_IMAP_LOOKBACK_DAYS', 30),
        'fetch_limit' => (int) env('GHOSTWRITER_IMAP_FETCH_LIMIT', 75),
        'timeout' => (int) env('GHOSTWRITER_IMAP_TIMEOUT', 45),
        'fetch_without_setting_seen' => filter_var(
            env('GHOSTWRITER_IMAP_FETCH_WITHOUT_SETTING_SEEN', true),
            FILTER_VALIDATE_BOOL
        ),
        'only_conversation_with_email' => env('GHOSTWRITER_IMAP_ONLY_CONVERSATION_WITH_EMAIL', ''),
        'extra_folders' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GHOSTWRITER_IMAP_EXTRA_FOLDERS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI agents
    |--------------------------------------------------------------------------
    | Class bindings the host can swap out. The default sanitizer agent slot
    | is left as `null` because the bundled Sanitizer class talks to Gaze
    | directly; the host can plug in a custom agent if they want LLM-based
    | sanitization for GitHub issue exports.
    */

    'agents' => [
        'draft' => env('GHOSTWRITER_DRAFT_AGENT', GhostwriterDraftAgent::class),
        'github_sanitizer' => env('GHOSTWRITER_SANITIZER_AGENT'),
        'translator' => env('GHOSTWRITER_TRANSLATOR_AGENT', GhostwriterTranslatorAgent::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt templates
    |--------------------------------------------------------------------------
    | Override the directory containing draft-system.php / draft-user.php.
    | Defaults to the bundled package prompts (publishable via vendor:publish).
    */

    'prompts' => [
        'path' => env('GHOSTWRITER_PROMPTS_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI
    |--------------------------------------------------------------------------
    | chat_model — used as the model passed to the underlying laravel/ai agent.
    | admin_key  — optional, enables monthly OpenAI cost reporting in the UI.
    | monthly_budget — optional cap (USD) used to render budget pct.
    */

    'openai' => [
        'chat_model' => env('GHOSTWRITER_OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'admin_key' => env('OPENAI_ADMIN_KEY', ''),
        'monthly_budget' => env('OPENAI_MONTHLY_BUDGET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG retrieval
    |--------------------------------------------------------------------------
    */

    'rag' => [
        'top_k' => (int) env('GHOSTWRITER_RAG_TOP_K', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound SMTP for reply send
    |--------------------------------------------------------------------------
    | Independent from Laravel's default mailer. driver: smtp (real server)
    | or null (discard mail — useful in tests).
    */

    'smtp' => [
        'driver' => env('GHOSTWRITER_SMTP_DRIVER', 'smtp'),
        'host' => env('GHOSTWRITER_SMTP_HOST', ''),
        'port' => (int) env('GHOSTWRITER_SMTP_PORT', 587),
        'encryption' => env('GHOSTWRITER_SMTP_ENCRYPTION', 'tls'),
        'username' => env('GHOSTWRITER_SMTP_USERNAME', ''),
        'password' => env('GHOSTWRITER_SMTP_PASSWORD', ''),
        'timeout' => (float) env('GHOSTWRITER_SMTP_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound from-address
    |--------------------------------------------------------------------------
    */

    'reply' => [
        'from_address' => env('GHOSTWRITER_REPLY_FROM_ADDRESS', ''),
        'from_name' => env('GHOSTWRITER_REPLY_FROM_NAME', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub issue export
    |--------------------------------------------------------------------------
    | The first label in `labels` is always applied; the rest are optional
    | toggles in the issue modal.
    */

    'github' => [
        'repo' => env('GITHUB_REPO', ''),
        'token' => env('GITHUB_TOKEN', ''),
        'labels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GHOSTWRITER_GITHUB_LABELS', 'support'))
        ))),
        'ai_sanitize_mail_body' => filter_var(
            env('GHOSTWRITER_GITHUB_AI_SANITIZE_MAIL', false),
            FILTER_VALIDATE_BOOL
        ),
        'ai_sanitize_timeout' => (int) env('GHOSTWRITER_GITHUB_AI_SANITIZE_TIMEOUT', 45),
        'ai_sanitize_max_input_chars' => (int) env('GHOSTWRITER_GITHUB_AI_SANITIZE_MAX_INPUT_CHARS', 12000),
    ],

];
