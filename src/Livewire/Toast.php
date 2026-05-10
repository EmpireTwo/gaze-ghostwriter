<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Minimal toast component that listens for `toast` Livewire events
 * dispatched from the package's admin pages.
 *
 * Usage: drop `<livewire:gaze-ghostwriter.toast />` into your layout, or
 * include the bundled view (`resources/views/toast.blade.php`) directly.
 *
 * Hosts using Flux UI or another toast system can ignore this component
 * entirely and replace the dispatched event with their own listener — the
 * package never imports a host-specific toast class anymore.
 */
final class Toast extends Component
{
    public ?string $message = null;

    public ?string $heading = null;

    public string $type = 'success';

    public int $duration = 5000;

    /**
     * @param  array{type?: string, message?: string, heading?: string, duration?: int}  ...$args
     */
    #[On('toast')]
    public function show(...$args): void
    {
        // Livewire's #[On] expands named keys passed via $this->dispatch().
        $payload = isset($args[0]) && is_array($args[0]) ? $args[0] : $args;

        $this->type = (string) ($payload['type'] ?? 'success');
        $this->message = (string) ($payload['message'] ?? '');
        $this->heading = isset($payload['heading']) ? (string) $payload['heading'] : null;
        $this->duration = (int) ($payload['duration'] ?? 5000);
    }

    public function dismiss(): void
    {
        $this->message = null;
        $this->heading = null;
    }

    public function render(): View
    {
        return view('gaze-ghostwriter::toast');
    }
}
