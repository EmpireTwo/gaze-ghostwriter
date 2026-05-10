<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Livewire\Admin\DraftShow;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftsIndex;
use Empire2\GazeGhostwriter\Livewire\Admin\GazeLog;
use Empire2\GazeGhostwriter\Livewire\Admin\GhostwriterSettings;
use Empire2\GazeGhostwriter\Livewire\Admin\PromptEditor;
use Empire2\GazeGhostwriter\Livewire\Admin\PromptHistory;
use Empire2\GazeGhostwriter\Livewire\Admin\SmartActionsManager;
use Illuminate\Support\Facades\Route;

Route::get('/', DraftsIndex::class)->name('drafts.index');
Route::get('/settings', GhostwriterSettings::class)->name('settings');
Route::get('/prompt-editor', PromptEditor::class)->name('prompt-editor');
Route::get('/smart-actions', SmartActionsManager::class)->name('smart-actions');
Route::get('/prompt-history', PromptHistory::class)->name('prompt-history');
Route::get('/gaze-log', GazeLog::class)->name('gaze-log');
Route::get('/drafts/{draft}', DraftShow::class)->name('drafts.show');
