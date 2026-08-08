# Refactoring & Component Migration Guide: `local_aacura_core`

This guide documents the complete architectural refactoring and component rename of the AACURA Core Engine plugin from legacy **`local_geniai`** to **`local_aacura_core`**.

---

## 1. Overview of Changes

To align with AAC-RERC branding and standard Moodle frankenstyle naming conventions, the core backend engine plugin has been renamed and modularized.

| Entity | Legacy Reference (`v1.x`) | Updated Reference (`v2.0+`) |
| :--- | :--- | :--- |
| **Plugin Directory** | `local/geniai/` | `local/aacura_core/` |
| **Frankenstyle Component** | `local_geniai` | `local_aacura_core` |
| **PHP Namespace Root** | `namespace local_geniai\...` | `namespace local_aacura_core\...` |
| **Settings Section ID** | `local_geniaisetting` | `local_aacura_coresetting` |
| **Language String File** | `lang/en/local_geniai.php` | `lang/en/local_aacura_core.php` |
| **Database Table Prefix** | `mdl_local_geniai_*` | `mdl_local_aacura_core_*` |

---

## 2. Namespace Mapping Table

All classes have been migrated under the `local_aacura_core` namespace:

| Legacy Class / File | Refactored Class / File |
| :--- | :--- |
| `\local_geniai\bot_engine` | `\local_aacura_core\bot_engine` |
| `\local_geniai\scenario_provider` | `\local_aacura_core\scenario_provider` |
| `\local_geniai\ai_strategy_factory` | `\local_aacura_core\ai_strategy_factory` |
| `\local_geniai\strategy\gemini_strategy` | `\local_aacura_core\strategy\gemini_strategy` |
| `\local_geniai\strategy\moodle_core_ai_strategy` | `\local_aacura_core\strategy\moodle_core_ai_strategy` |
| `\local_geniai\output\renderer` | `\local_aacura_core\output\renderer` |

---

## 3. Database Schema Migration

The underlying database tables tracking active dialogue states, scenario profiles, and evaluation logs have been migrated:

```sql
-- Table Renames
RENAME TABLE mdl_local_geniai_sessions TO mdl_local_aacura_core_sessions;
RENAME TABLE mdl_local_geniai_scenarios TO mdl_local_aacura_core_scenarios;
RENAME TABLE mdl_local_geniai_evaluations TO mdl_local_aacura_core_evaluations;

-- Settings Migration
UPDATE mdl_config_plugins SET plugin = 'local_aacura_core' WHERE plugin = 'local_geniai';
```

---

## 4. Automated Upgrades for Existing Moodle Sites

For Moodle sites that had installed legacy versions (`local_geniai`), an automated CLI migration script is provided in this repository at:

```
local/aacura_core/cli/migrate_geniai_to_aacura.php
```

### Running the Migration Script

Run the CLI script from your Moodle server root:

```bash
php local/aacura_core/cli/migrate_geniai_to_aacura.php
```

The script will automatically detect legacy `local_geniai` tables and plugin configurations, migrate them to `local_aacura_core`, update Moodle system records, and purge all site caches.
