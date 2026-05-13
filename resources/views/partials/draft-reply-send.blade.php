{{-- $canSendReply, $smtpReady, $msg: gesetzt im einbindenden draft-detail-inner --}}
@if ($canSendReply)
    <section class="border border-art-border rounded-lg p-5 bg-white">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-2">Antwort senden</h2>
        <p class="font-poppins text-xs-plus text-art-text-muted mb-3">
            Versand an <strong class="text-art-black">{{ $msg->from_email }}</strong> per SMTP (Konfiguration <code class="text-2xs bg-art-page px-1 rounded">GHOSTWRITER_SMTP_*</code>).
            Betreff: <strong class="text-art-black">Re:</strong> …; Threading über <code class="text-2xs bg-art-page px-1 rounded">In-Reply-To</code> / <code class="text-2xs bg-art-page px-1 rounded">References</code> zur Original-Mail.
        </p>
        @php
            $isAnonymous = $msg->from_email === \Empire2\GazeGhostwriter\Services\FeedbackIntakeService::ANONYMOUS_SENDER_SENTINEL;
        @endphp
        @if ($isAnonymous)
            <p class="font-poppins text-xs-plus text-amber-800">
                Keine Antwortadresse — anonymes Feedback. Antwort nicht möglich.
            </p>
        @elseif ($smtpReady)
            <flux:button
                type="button"
                variant="primary"
                x-on:click="$store.confirm.open('Antwort jetzt per E-Mail an die Absenderadresse senden?', () => $wire.sendReply())"
            >Antwort senden</flux:button>
        @else
            <p class="font-poppins text-xs-plus text-amber-800">
                SMTP nicht konfiguriert — setze <code class="bg-white px-1 rounded border border-art-border">GHOSTWRITER_SMTP_HOST</code> und
                <code class="bg-white px-1 rounded border border-art-border">GHOSTWRITER_REPLY_FROM_ADDRESS</code> (siehe <code class="bg-white px-1 rounded border border-art-border">.env.example</code>).
            </p>
        @endif
    </section>
@endif
