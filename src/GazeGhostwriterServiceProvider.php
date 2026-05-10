<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter;

use Empire2\GazeGhostwriter\Ai\Contracts\GuardedAgentRunnerContract;
use Empire2\GazeGhostwriter\Ai\GuardedAgentRunner;
use Empire2\GazeGhostwriter\Console\Commands\GhostwriterImapTestCommand;
use Empire2\GazeGhostwriter\Console\Commands\GhostwriterReprocessHtmlBodiesCommand;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftShow;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftsIndex;
use Empire2\GazeGhostwriter\Livewire\Admin\GazeLog;
use Empire2\GazeGhostwriter\Livewire\Admin\GhostwriterSettings;
use Empire2\GazeGhostwriter\Livewire\Admin\PromptEditor;
use Empire2\GazeGhostwriter\Livewire\Admin\PromptHistory;
use Empire2\GazeGhostwriter\Livewire\Admin\SmartActionsManager;
use Empire2\GazeGhostwriter\Livewire\Toast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class GazeGhostwriterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gaze-ghostwriter.php', 'gaze-ghostwriter');

        $this->app->bind(GuardedAgentRunnerContract::class, GuardedAgentRunner::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'gaze-ghostwriter');

        $this->registerRoutes();
        $this->registerLivewireComponents();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/gaze-ghostwriter.php' => config_path('gaze-ghostwriter.php'),
            ], 'gaze-ghostwriter-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'gaze-ghostwriter-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/gaze-ghostwriter'),
            ], 'gaze-ghostwriter-views');

            $this->publishes([
                __DIR__.'/Prompts' => resource_path('prompts/gaze-ghostwriter'),
            ], 'gaze-ghostwriter-prompts');

            $this->commands([
                GhostwriterImapTestCommand::class,
                GhostwriterReprocessHtmlBodiesCommand::class,
            ]);
        }
    }

    private function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('gaze-ghostwriter.route_prefix', 'ghostwriter'),
            'middleware' => config('gaze-ghostwriter.middleware', ['web', 'auth']),
            'as' => 'gaze-ghostwriter.',
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    private function registerLivewireComponents(): void
    {
        Livewire::component('gaze-ghostwriter.drafts-index', DraftsIndex::class);
        Livewire::component('gaze-ghostwriter.draft-show', DraftShow::class);
        Livewire::component('gaze-ghostwriter.settings', GhostwriterSettings::class);
        Livewire::component('gaze-ghostwriter.prompt-editor', PromptEditor::class);
        Livewire::component('gaze-ghostwriter.smart-actions', SmartActionsManager::class);
        Livewire::component('gaze-ghostwriter.prompt-history', PromptHistory::class);
        Livewire::component('gaze-ghostwriter.gaze-log', GazeLog::class);
        Livewire::component('gaze-ghostwriter.toast', Toast::class);
    }
}
