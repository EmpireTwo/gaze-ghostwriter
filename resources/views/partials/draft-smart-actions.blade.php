@php
    use Illuminate\Support\Facades\Route;
    /** @var \Empire2\GazeGhostwriter\Models\SupportDraft $draft */
    $msg = $draft->message;
    $customer = \Empire2\GazeGhostwriter\Support\SmartActionCustomerResolver::resolve($msg->from_email);

    $tagActions = [];
    if (is_array($draft->smart_action_tags) && $draft->smart_action_tags !== []) {
        $configuredActions = \Empire2\GazeGhostwriter\Models\GhostwriterSmartAction::allActive()->keyBy('marker');
        foreach ($draft->smart_action_tags as $tag) {
            $action = $configuredActions->get($tag);
            if ($action !== null) {
                $replacements = ['draftId' => $draft->id];
                if ($customer !== null) {
                    $replacements['customerId'] = $customer->id;
                }
                $tagActions[] = [
                    'label' => $action->label,
                    'url' => $action->resolveRoute($replacements),
                    'marker' => $action->marker,
                ];
            }
        }
    }

    $entityActions = [];
    if ($customer !== null && is_array($draft->mentioned_entities) && $draft->mentioned_entities !== []) {
        $entityActions = \Empire2\GazeGhostwriter\Support\MentionedEntityResolver::resolve($customer, $draft->mentioned_entities);
    }

    $githubReady = filled(config('gaze-ghostwriter.github.repo')) && filled(config('gaze-ghostwriter.github.token'));

    $ghAllLabels = config('gaze-ghostwriter.github.labels', []);
    $ghAllLabels = is_array($ghAllLabels) ? array_values(array_filter($ghAllLabels, fn ($l): bool => is_string($l) && $l !== '')) : [];
    $ghRequiredLabel = $ghAllLabels[0] ?? null;
    $ghOptionalLabels = array_slice($ghAllLabels, 1);

    // Ticketing integration is host-specific. The package ships without a Ticket
    // model — set $ticketModel in your host config or service container if you
    // want this section to surface ticket links.
    $ticketModel = config('gaze-ghostwriter.ticket_model');
    $draftTickets = collect();
    $customerTickets = collect();

    if (is_string($ticketModel) && class_exists($ticketModel)) {
        $draftTickets = $ticketModel::query()
            ->where('source_type', 'support_draft')
            ->where('source_id', $draft->id)
            ->with('status')
            ->latest()
            ->get();

        if ($customer !== null) {
            $customerTickets = $ticketModel::query()
                ->where('customer_id', $customer->id)
                ->where(fn ($q) => $q->where('source_type', '!=', 'support_draft')
                    ->orWhere('source_id', '!=', $draft->id)
                    ->orWhereNull('source_type'))
                ->with('status')
                ->latest()
                ->limit(10)
                ->get();
        }
    }

    $totalTickets = $draftTickets->count() + $customerTickets->count();

    // Resolves host-specific routes safely so the package does not crash when the
    // host has not registered them. Override `customer_route` / `ticket_show_route`
    // / `ticket_board_route` in config to point at your own routes.
    $customerRoute = config('gaze-ghostwriter.routes.customer_show', 'admin.customers.show');
    $ticketShowRoute = config('gaze-ghostwriter.routes.ticket_show', 'admin.tickets.show');
    $ticketBoardRoute = config('gaze-ghostwriter.routes.ticket_board', 'admin.tickets.board');

    $hasAnything = true;
@endphp

@if ($hasAnything)
    <div class="flex flex-wrap gap-2">
        @if ($customer !== null)
            <a
                href="{{ Route::has($customerRoute) ? route($customerRoute, $customer) : '#' }}"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-art-border bg-art-page text-xs-plus font-poppins text-art-black hover:border-art-violet-deep hover:text-art-violet-deep transition-colors no-underline"
            >
                <flux:icon.user class="size-3.5" />
                <span>Kunde: {{ $customer->fullname }}</span>
            </a>
        @endif

        @foreach ($tagActions as $ta)
            <a
                href="{{ $ta['url'] }}"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-art-border bg-art-page text-xs-plus font-poppins text-art-black hover:border-art-violet-deep hover:text-art-violet-deep transition-colors no-underline"
            >
                <flux:icon.bolt class="size-3.5" />
                <span>{{ $ta['label'] }}</span>
            </a>
        @endforeach

        @foreach ($entityActions as $ea)
            <a
                href="{{ $ea['url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-art-border bg-art-page text-xs-plus font-poppins text-art-black hover:border-art-violet-deep hover:text-art-violet-deep transition-colors no-underline"
            >
                @if ($ea['type'] === 'artist')
                    <flux:icon.microphone class="size-3.5" />
                    <span>Artist: {{ $ea['name'] }}</span>
                @elseif ($ea['type'] === 'release')
                    <flux:icon.musical-note class="size-3.5" />
                    <span>Release: {{ $ea['name'] }}</span>
                @else
                    <flux:icon.link class="size-3.5" />
                    <span>{{ $ea['name'] }}</span>
                @endif
            </a>
        @endforeach

        {{-- Tickets --}}
        <div class="relative" x-data="{ ticketPopover: false }">
            <button
                type="button"
                @click="ticketPopover = !ticketPopover"
                @click.away="ticketPopover = false"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-md border {{ $totalTickets > 0 ? 'border-amber-300 bg-amber-50 text-amber-800 hover:border-amber-500' : 'border-art-border bg-art-page text-art-black hover:border-art-violet-deep hover:text-art-violet-deep' }} text-xs-plus font-poppins transition-colors cursor-pointer"
            >
                <flux:icon.ticket class="size-3.5" />
                @if ($totalTickets > 0)
                    <span>Tickets ({{ $totalTickets }})</span>
                @else
                    <span>Ticket erstellen</span>
                @endif
            </button>

            {{-- Popover --}}
            <div
                x-show="ticketPopover"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute left-0 top-full mt-1.5 z-20 w-72 bg-white rounded-lg shadow-lg border border-art-border overflow-hidden"
            >
                @if ($draftTickets->isNotEmpty())
                    <div class="px-3 pt-2.5 pb-1">
                        <span class="text-2xs font-semibold text-art-text-muted uppercase">Tickets zu diesem Entwurf</span>
                    </div>
                    @foreach ($draftTickets as $t)
                        <a href="{{ Route::has($ticketShowRoute) ? route($ticketShowRoute, $t) : '#' }}" wire:navigate @click="ticketPopover = false"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs-plus no-underline hover:bg-art-page transition-colors">
                            <span class="size-2 rounded-full bg-{{ $t->status->color }}-500 shrink-0"></span>
                            <span class="font-medium text-art-black truncate">{{ $t->ticket_number }}</span>
                            <span class="text-art-text-muted truncate">{{ \Illuminate\Support\Str::limit($t->title, 30) }}</span>
                            <span class="ml-auto text-2xs text-art-text-muted shrink-0">{{ $t->status->name }}</span>
                        </a>
                    @endforeach
                @endif

                @if ($customerTickets->isNotEmpty())
                    <div class="px-3 pt-2.5 pb-1 {{ $draftTickets->isNotEmpty() ? 'border-t border-art-border' : '' }}">
                        <span class="text-2xs font-semibold text-art-text-muted uppercase">Tickets zu {{ $customer->fullname }}</span>
                    </div>
                    @foreach ($customerTickets as $t)
                        <a href="{{ Route::has($ticketShowRoute) ? route($ticketShowRoute, $t) : '#' }}" wire:navigate @click="ticketPopover = false"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs-plus no-underline hover:bg-art-page transition-colors">
                            <span class="size-2 rounded-full bg-{{ $t->status->color }}-500 shrink-0"></span>
                            <span class="font-medium text-art-black truncate">{{ $t->ticket_number }}</span>
                            <span class="text-art-text-muted truncate">{{ \Illuminate\Support\Str::limit($t->title, 30) }}</span>
                            <span class="ml-auto text-2xs text-art-text-muted shrink-0">{{ $t->status->name }}</span>
                        </a>
                    @endforeach
                @endif

                {{-- Neues Ticket erstellen --}}
                <div class="border-t border-art-border p-2">
                    @if (method_exists($this, 'openTicketPanel'))
                        <button
                            type="button"
                            wire:click="openTicketPanel({{ $draft->id }})"
                            @click="ticketPopover = false"
                            class="flex items-center gap-1.5 w-full px-2 py-1.5 text-xs-plus font-medium font-poppins text-art-violet-deep hover:bg-violet-50 rounded transition-colors cursor-pointer"
                        >
                            <flux:icon.plus class="size-3.5" />
                            Neues Ticket erstellen
                        </button>
                    @else
                        <a
                            href="{{ Route::has($ticketBoardRoute) ? route($ticketBoardRoute, ['source_type' => 'support_draft', 'source_id' => $draft->id, 'prefill' => 1]) : '#' }}"
                            wire:navigate
                            class="flex items-center gap-1.5 w-full px-2 py-1.5 text-xs-plus font-medium font-poppins text-art-violet-deep hover:bg-violet-50 rounded transition-colors no-underline"
                        >
                            <flux:icon.plus class="size-3.5" />
                            Neues Ticket erstellen
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if (filled($draft->github_issue_url))
            <a
                href="{{ $draft->github_issue_url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-emerald-300 bg-emerald-50 text-xs-plus font-poppins text-emerald-800 hover:border-emerald-500 transition-colors no-underline"
            >
                <flux:icon.check-circle class="size-3.5" />
                <span>GitHub Issue</span>
            </a>
        @elseif ($githubReady)
            <button
                type="button"
                wire:click="openGithubIssueModal"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-art-border bg-art-page text-xs-plus font-poppins text-art-black hover:border-art-violet-deep hover:text-art-violet-deep transition-colors cursor-pointer"
            >
                <flux:icon.code-bracket class="size-3.5" />
                <span>GitHub Issue</span>
            </button>
        @endif
    </div>
@endif

@include('gaze-ghostwriter::partials.draft-github-issue-modal', ['draft' => $draft])
