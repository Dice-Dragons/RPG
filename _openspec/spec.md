# OpenSpec: [DD] RPG Suite (`cv6/RPG`)

## 1. Metadata & Architecture
- **Add-on ID**: `cv6/RPG`
- **Title**: `[DD] RPG Suite`
- **Version**: `1.0.0 Beta 1` (`1000031`)
- **Public Route**: `rpg/`
- **CLI Prefix**: `dd-rpg:*`
- **Dependencies**:
  - XenForo `2.3.0+` (`2030010`)
  - PHP `8.2.0+`
  - `cv6/Core` `1.6.3+` (`1060370`)
- **Downstream Dependents**:
  - `cv6/CharEdit` (`[DD] Character Database`)
  - `cv6/DiceRoller` (`[DD] Dice Roller`)
  - `cv6/BattleMaps` (`[DD] BattleMap Generator`)
  - `cv6/Handout` (`[DD] Handout Generator`)

## 2. Database Schema (`Setup.php`)
- `xf_cv6_genre`: Central RPG genre management (`genre_id`, `class`, `generator_type`, `property_id`, `active`, `display_order`, `setting_count`, `map_count`).
- `xf_cv6_publisher`: Central RPG publisher directory (`publisher_id`, `title`, `url_slug`, `description`, `logo_url`, `url`, `license_notice`, `active`, `display_order`).
- `xf_cv6_rpg_campaign_content`: M:N campaign resource link table (`link_id`, `campaign_id`, `content_type`, `content_id`, `visibility`, `title_override`, `notes`, `display_order`, `added_user_id`, `added_date`).
- Conditional bridge columns on `xf_cv6_setting` (`cv6_rpg_wireset`), `xf_cv6_character` (`cv6_rpg_dice_set`), `xf_cv6_character_field` (`cv6_rpg_rollable`, `cv6_rpg_roll_config`), and `xf_cv6_rolls` (`cv6_character_id`).
