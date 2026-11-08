# OpenSpec: [DD] RPG Suite (`cv6/RPG`)

## 1. Metadata & Architecture
- **Add-on ID**: `cv6/RPG`
- **Title**: `[DD] RPG Suite`
- **Version**: `1.0.0 Beta 1` (`1000031`)
- **Public Route**: `rpg/`
- **Admin Routes**: `rpg/manager`, `rpg/genre`, `rpg/publisher`
- **CLI Prefix**: `dd-rpg:*` (`dd-rpg:sync-schema`)
- **Dependencies**:
  - XenForo `2.3.0+` (`2030010`)
  - PHP `8.2.0+`
  - `cv6/Core` `1.6.3+` (`1060370`)
- **Downstream Dependents (Optional Modules)**:
  - `cv6/CharEdit` (`[DD] Character Database`)
  - `cv6/DiceRoller` (`[DD] Dice Roller`)
  - `cv6/BattleMaps` (`[DD] BattleMap Generator`)
  - `cv6/Handout` (`[DD] Handout Generator`)

## 2. Database Schema (`Setup.php`)
- `xf_cv6_genre`: Central RPG genre management (`genre_id`, `class`, `icon`, `property_id`, `active`, `display_order`, `setting_count`, `map_count`, `wireset_count`).
- `xf_cv6_publisher`: Central RPG publisher directory (`publisher_id`, `title`, `url_slug`, `description`, `logo_url`, `url`, `license_notice`, `active`, `display_order`).
- `xf_cv6_rpg_campaign_content`: M:N campaign resource link table (`link_id`, `campaign_id`, `content_type`, `content_id`, `visibility`, `title_override`, `notes`, `display_order`, `added_user_id`, `added_date`).
- Conditional bridge columns (synced non-destructively via `Setup::syncBridgeSchema()` and `addon_post_install`/`addon_post_upgrade`/`addon_post_rebuild` listeners):
  - `xf_cv6_setting.cv6_rpg_wireset` (`varchar(25)`)
  - `xf_cv6_character.cv6_rpg_dice_set` (`varchar(50)`, nullable)
  - `xf_cv6_character_field.cv6_rpg_rollable` (`bool`), `xf_cv6_character_field.cv6_rpg_roll_config` (`mediumblob`, nullable)
  - `xf_cv6_rolls.cv6_character_id` (`int`)
  - `xf_cv6_wiresets.genre_id` (`int unsigned`)

## 3. Core Entities, Services & Controllers
- **Entities**:
  - `cv6\RPG\Entity\Genre`: Manages `xf_cv6_genre`, master phrases (`cv6_ce_genre_title_<id>`, `cv6_ce_genre_desc_<id>`), style property link (`Property`), and counters (`setting_count`, `map_count`, `wireset_count`, `total_count`).
  - `cv6\RPG\Entity\Publisher`: Manages `xf_cv6_publisher`, logo asset cleanup, and setting reassignment on deletion.
  - `cv6\RPG\Entity\CampaignContent`: Manages campaign-linked BattleMaps and Handouts with `gm_only` and `players` visibility levels.
- **Services**:
  - `cv6\RPG\Service\Genre\PropertyService`: Creates, updates, rebuilds, and deletes `cv6Genre_<class>` style properties in the `cv6Genre` group and maintains the `genreMap` simpleCache.
  - `cv6\RPG\Service\Export\Publisher` & `cv6\RPG\Service\Import\PublisherImport`: JSON and ZIP (with logo images) export and import for publishers.
- **Admin Controllers**:
  - `cv6\RPG\Admin\Controller\ManagerController`: Ecosystem & add-on status overview (`admin:cv6_rpg_manager`) with direct enable action.
  - `cv6\RPG\Admin\Controller\GenreController`: Full CRUD, toggle, manual/alphabetical sorting, and style property sync (`admin:cv6_rpg_genre_*`).
  - `cv6\RPG\Admin\Controller\PublisherController`: Full CRUD, toggle, manual/alphabetical sorting, and JSON/ZIP export/import (`admin:cv6_rpg_publisher_*`).
- **Public Controllers & Widgets**:
  - `cv6\RPG\Pub\Controller\IndexController`: Public RPG Suite Dashboard (`public:cv6_rpg_dashboard`) at `/rpg/` with `genre_id` context filtering.
  - `cv6\RPG\Widget\Genres`: Genre filter widget (`cv6_rpg_genres`) supporting `sidenav`, `grid`, and `pills` layouts and `total_count`/`setting_count`/`map_count`/`wireset_count` badges.

## 4. Frontend Assets & Class Extensions
- **JavaScript**: `src/addons/cv6/RPG/_files/js/cv6/rpg/suite.js` (`XF.cv6RpgRollCharacter` and `XF.cv6RpgQuickRollClick`, minified via `build.json`).
- **Styles**: `public:cv6_rpg.less` (Genre grid/pills/color dots/labels via `Listener::preRenderCssLayouts`, companion bar, and skill chips).
