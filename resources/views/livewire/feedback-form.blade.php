<div class="gw-feedback-form">
    <h2 class="text-base font-semibold">Dein Feedback</h2>

    @if ($submitted)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
            {{ $slot ?? '' }}
            @isset($success)
                {{ $success }}
            @else
                <p class="text-sm font-medium">Danke für dein Feedback — wir melden uns.</p>
            @endisset
        </div>
    @else
        @isset($header)
            <div class="mb-3">{{ $header }}</div>
        @endisset

        <form wire:submit.prevent="submit" class="space-y-4">
            <div style="position:absolute;left:-9999px;height:0;overflow:hidden;" aria-hidden="true">
                <label>Website
                    <input type="text" wire:model.live="hp" tabindex="-1" autocomplete="off">
                </label>
            </div>

            @if ($settings->requireSubject || $settings->topics !== [])
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if ($settings->requireSubject)
                        <label class="block text-sm">
                            <span class="block text-art-text-muted">Betreff</span>
                            <input type="text" wire:model.defer="subject"
                                   class="mt-1 block w-full rounded border px-3 py-2" maxlength="200" />
                            @error('subject') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @endif

                    @if ($settings->topics !== [])
                        <label class="block text-sm">
                            <span class="block text-art-text-muted">Thema</span>
                            <select wire:model.defer="topic" class="mt-1 block w-full rounded border px-3 py-2">
                                <option value="">— bitte wählen —</option>
                                @foreach ($settings->topics as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                            @error('topic') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @endif
                </div>
            @endif

            @if (! $isAuthenticated)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="block text-sm">
                        <span class="block text-art-text-muted">
                            E-Mail @if ($settings->requireEmailForGuests)<span class="text-red-600">*</span>@endif
                        </span>
                        <input type="email" wire:model.defer="guestEmail"
                               class="mt-1 block w-full rounded border px-3 py-2" maxlength="255" />
                        @error('guestEmail') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="block text-art-text-muted">Name (optional)</span>
                        <input type="text" wire:model.defer="guestName"
                               class="mt-1 block w-full rounded border px-3 py-2" maxlength="120" />
                        @error('guestName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>
            @endif

            <label class="block text-sm">
                <span class="block text-art-text-muted">Nachricht <span class="text-red-600">*</span></span>
                <textarea wire:model.defer="message" rows="6"
                          class="mt-1 block w-full rounded border px-3 py-2" maxlength="10000"></textarea>
                @error('message') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <button type="submit" wire:loading.attr="disabled"
                    class="rounded bg-art-black px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">
                Feedback senden
            </button>
        </form>
    @endif
</div>
