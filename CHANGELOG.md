# Changelog

All notable changes to `empire2/gaze-ghostwriter` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- IMAP inbound mail sync (Webklex) with conversation-partner filter.
- RAG-augmented draft generation via Laravel AI agents (`GhostwriterDraftAgent`).
- PII-safe LLM calls through `empiretwo/gaze-laravel` (clean / restore around every prompt).
- Translation pipeline for non-German customer mails (`GhostwriterTranslatorAgent`).
- Smart Actions tagging + admin manager.
- Per-user and global additional prompt rules with prompt history + token cost tracking.
- SMTP outbound for support replies + GitHub issue export.
- Livewire admin UI: drafts list, draft show, settings, prompt editor, prompt history,
  smart actions, gaze pipeline log.
- Console commands `ghostwriter:imap-test`, `ghostwriter:reprocess-html-bodies`.
