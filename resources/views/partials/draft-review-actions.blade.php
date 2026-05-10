<div class="flex flex-wrap gap-3">
    <flux:button
        variant="outline"
        x-on:click="$store.confirm.open('Einen neuen KI-Entwurf erzeugen? Der aktuelle wird archiviert.', () => $wire.regenerate())"
    >Neu generieren</flux:button>
    <flux:button variant="ghost" x-on:click="$store.confirm.open('Diesen Entwurf als Keine Antwort nötig markieren?', () => $wire.dismiss())">Keine Antwort nötig</flux:button>
</div>
