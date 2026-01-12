# Recipe Translation Progress

## Overview

| Source | Language | Count | → English | → Spanish | → French |
|--------|----------|-------|-----------|-----------|----------|
| Colombia (biblioteca_9 + biblioteca_11) | Spanish | 893 | PENDING | n/a | - |
| Costa Rica | Spanish | ~675 | PENDING | n/a | - |
| Italy | Italian | 34 | PENDING | PENDING | - |

**Total recipes**: 1,602

## Translation Strategy

- **Spanish recipes** (Colombia, Costa Rica): Translate directly to English
- **Italian recipes**: Translate directly to each target language (no chaining)
  - Italian → English (direct)
  - Italian → Spanish (direct)
  - Do NOT chain: Italian → Spanish → English

## Progress Log

### Spanish → English Translation

**Status**: IN PROGRESS

```bash
# Command to check remaining
ddev drush sqlq "SELECT COUNT(*) FROM node_field_data n WHERE n.type = 'recipe' AND n.langcode = 'es' AND NOT EXISTS (SELECT 1 FROM node_field_data e WHERE e.nid = n.nid AND e.langcode = 'en')"

# Command to translate next batch of 50
NIDS=$(ddev drush sqlq "SELECT n.nid FROM node_field_data n WHERE n.type = 'recipe' AND n.langcode = 'es' AND NOT EXISTS (SELECT 1 FROM node_field_data e WHERE e.nid = n.nid AND e.langcode = 'en') ORDER BY n.nid LIMIT 50" | tr '\n' ',' | sed 's/,$//')
ddev drush ai:translate-entity node "$NIDS" es en
```

**Progress**: 230 / 1,568 translated (14.7%)
**Remaining**: 1,338 recipes
**Batches completed**: ~5 / ~32 (50 recipes per batch)

| Batch | Count | Status | Date |
|-------|-------|--------|------|
| 1 | 50 | ✅ Done | 2026-01-12 |
| 2 | 50 | ✅ Done | 2026-01-12 |
| 3 | 50 | ✅ Done | 2026-01-12 |
| 4 | 50 | ✅ Done | 2026-01-12 |
| 5 | ~30 | ✅ Partial | 2026-01-12 |
| 6+ | - | Pending | - |

### Italian → English Translation

**Status**: NOT STARTED (do after Spanish → English)

### Italian → Spanish Translation

**Status**: NOT STARTED (do after Spanish → English)

## Taxonomy Translations

| Vocabulary | Count | Spanish | Italian | French |
|------------|-------|---------|---------|--------|
| units | 81 | ✅ Done | ✅ Done | ✅ Done |
| recipe_category | ? | PENDING | PENDING | PENDING |
| tags | ? | PENDING | PENDING | PENDING |
| origin_country | ? | PENDING | PENDING | PENDING |
| origin_region | ? | PENDING | PENDING | PENDING |

## Notes

- Use batches of 50-100 recipes to avoid timeouts
- Check for errors after each batch
- AI translation costs apply - monitor usage
