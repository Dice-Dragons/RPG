# Changelog

All notable changes to **[DD] RPG Suite** (`cv6/RPG`) are documented in this file.

## [1.0.0 Beta 1] - 2026-11-08

### Added
- Standalone RPG domain platform for Genres (`xf_cv6_genre`) and Publishers (`xf_cv6_publisher`) with AdminCP management, sorting, and publisher JSON/ZIP export & import.
- Automatic style property management (`cv6Genre` group and `cv6Genre_<class>` properties) for genre pills, badges, and filters.
- AdminCP RPG Suite Ecosystem & Add-on Overview (`admin.php?rpg/manager`) displaying installed, disabled, and available RPG Suite modules with direct enable toggles and XFRM links.
- Public RPG Suite Dashboard (`index.php?rpg/`) with left-sidebar Genre filter across all genre-aware widgets (Settings, Campaigns, Characters, BattleMaps, Handouts, and Recent Dice Rolls).
- Modular bridge connecting `[DD] Character Database`, `[DD] Dice Roller`, `[DD] BattleMap Generator`, and `[DD] Handout Generator` (Setting primary wiresets, Campaign Forum wireset provisioning, rollable character attributes/skills, character-specific dice sets, campaign content linking, and In-Thread RPG Companion bar).
- CLI command `php cmd.php dd-rpg:sync-schema` and automatic add-on lifecycle listeners for non-destructive schema synchronization.
