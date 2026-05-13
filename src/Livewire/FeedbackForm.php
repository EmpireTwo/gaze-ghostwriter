<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Livewire;

use Empire2\GazeGhostwriter\DTO\FeedbackIntakeDto;
use Empire2\GazeGhostwriter\Services\FeedbackIntakeService;
use Empire2\GazeGhostwriter\Support\FeedbackSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class FeedbackForm extends Component
{
    public string $message = '';

    public string $subject = '';

    public string $guestEmail = '';

    public string $guestName = '';

    public string $topic = '';

    public string $hp = '';

    public bool $submitted = false;

    protected ?string $sourceUrl = null;

    public function mount(): void
    {
        if (! FeedbackSettings::all()->enabled) {
            abort(404);
        }

        $this->sourceUrl = request()->headers->get('referer');
    }

    public function submit(FeedbackIntakeService $feedbackIntakeService): void
    {
        if ($this->hp !== '') {
            $this->submitted = true;

            return;
        }

        $settings = FeedbackSettings::all();
        $rateKey = 'gw-feedback:'.(string) request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, $settings->rateLimitPerMinute)) {
            $this->dispatch('toast',
                type: 'danger',
                message: 'Zu viele Anfragen — bitte später erneut versuchen.',
                heading: 'Feedback',
            );

            return;
        }

        $validated = $this->validate($this->buildRules($settings));

        $dto = new FeedbackIntakeDto(
            message: $validated['message'],
            subject: $validated['subject'] ?? '',
            guestEmail: $validated['guestEmail'] ?? '',
            guestName: $validated['guestName'] ?? '',
            topic: ($validated['topic'] ?? '') !== '' ? $validated['topic'] : null,
        );

        $feedbackIntakeService->intake($dto, Auth::user(), $this->sourceUrl);

        RateLimiter::hit($rateKey, 60);

        $this->reset(['message', 'subject', 'guestEmail', 'guestName', 'topic', 'hp']);
        $this->submitted = true;
        $this->dispatch('feedback-submitted');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function buildRules(FeedbackSettings $settings): array
    {
        $rules = [
            'message' => ['required', 'string', 'min:3', 'max:10000'],
        ];

        $rules['subject'] = $settings->requireSubject
            ? ['required', 'string', 'max:200']
            : ['nullable', 'string', 'max:200'];

        if (! Auth::check()) {
            $rules['guestEmail'] = $settings->requireEmailForGuests
                ? ['required', 'email', 'max:255']
                : ['nullable', 'email', 'max:255'];
            $rules['guestName'] = ['nullable', 'string', 'max:120'];
        }

        if ($settings->topics !== []) {
            $rules['topic'] = ['nullable', Rule::in($settings->topics)];
        } else {
            $rules['topic'] = ['nullable'];
        }

        return $rules;
    }

    public function render(): View
    {
        return view('gaze-ghostwriter::livewire.feedback-form', [
            'settings' => FeedbackSettings::all(),
            'isAuthenticated' => Auth::check(),
        ]);
    }
}
