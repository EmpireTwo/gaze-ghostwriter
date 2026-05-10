<div
    x-data="{
        show: @entangle('message').live,
        duration: @entangle('duration').live,
    }"
    x-show="show"
    x-init="$watch('show', value => { if (value) setTimeout(() => $wire.dismiss(), duration) })"
    x-transition.opacity
    style="display:none;"
    class="fixed top-6 right-6 z-50 max-w-sm rounded-lg border bg-white p-4 shadow-lg
        @if($type === 'success') border-emerald-200 @elseif($type === 'danger') border-red-200 @elseif($type === 'warning') border-amber-200 @else border-zinc-200 @endif"
    role="status"
    aria-live="polite"
>
    @if($heading)
        <div class="text-sm font-semibold
            @if($type === 'success') text-emerald-800
            @elseif($type === 'danger') text-red-800
            @elseif($type === 'warning') text-amber-800
            @else text-zinc-800
            @endif">{{ $heading }}</div>
    @endif

    @if($message)
        <div class="mt-1 text-sm
            @if($type === 'success') text-emerald-700
            @elseif($type === 'danger') text-red-700
            @elseif($type === 'warning') text-amber-700
            @else text-zinc-700
            @endif">{{ $message }}</div>
    @endif

    <button type="button" wire:click="dismiss" class="mt-2 text-xs underline opacity-60 hover:opacity-100">
        schließen
    </button>
</div>
