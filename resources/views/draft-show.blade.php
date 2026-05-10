@php
    /** @var \Empire2\GazeGhostwriter\Models\SupportDraft $draft */
    $draft = $this->draft;
@endphp

<div class="p-8 max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('gaze-ghostwriter.drafts.index') }}" wire:navigate class="font-poppins text-xs-plus text-art-violet-deep hover:underline">← Zurück zur Liste</a>
    </div>

    <h1 class="font-poppins text-2xl font-light text-art-black mb-1">Entwurf #{{ $draft->id }}</h1>
    <p class="font-poppins text-sm text-art-text-light mb-4">Status: {{ $draft->status->label() }}</p>

    <div class="sticky top-0 z-10 -mx-8 px-8 py-3 bg-white/95 backdrop-blur-sm border-b border-art-border mb-6">
        @include('gaze-ghostwriter::partials.draft-smart-actions', ['draft' => $draft])
    </div>

    @include('gaze-ghostwriter::partials.draft-detail-inner', ['draft' => $draft])
</div>
