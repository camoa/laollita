# AI Translation Guide

This project uses the **AI Translate** module for translating content using LLMs.

## Requirements

- AI Core module configured with a provider (e.g., OpenAI, Anthropic)
- Content Translation module enabled
- AI Translate module enabled and configured at `/admin/config/ai/ai-translate`

## Drush Commands

### Translate Entities

Translate content entities (nodes, taxonomy terms, etc.) using AI:

```bash
ddev drush ai:translate-entity <entityType> <entityIds> <langFrom> <langTo>
```

**Arguments:**
- `entityType`: Entity type machine name (e.g., `node`, `taxonomy_term`)
- `entityIds`: Comma-separated entity IDs (e.g., `16,18,20,21`)
- `langFrom`: Source language code (e.g., `en`, `es`, `it`)
- `langTo`: Target language code (e.g., `es`, `fr`, `it`)

**Examples:**

```bash
# Translate a single node from English to Spanish
ddev drush ai:translate-entity node 42 en es

# Translate multiple taxonomy terms from English to Spanish
ddev drush ai:translate-entity taxonomy_term 40,41,42,43 en es

# Translate recipe nodes from Spanish to English
ddev drush ai:translate-entity node 100,101,102 es en
```

### Translate Text

Translate a simple text string:

```bash
ddev drush ai:translate-text "<text>" <langFrom> <langTo>
```

**Example:**

```bash
ddev drush ai:translate-text "Hello world" en es
```

## Batch Translation Scripts

### Translate All Terms in a Vocabulary

To translate all terms in a vocabulary (e.g., units) to a target language:

```bash
# Get all term IDs from a vocabulary
TIDS=$(ddev drush sqlq "SELECT tid FROM taxonomy_term_field_data WHERE vid = 'units' AND langcode = 'en'" | tr '\n' ',')

# Translate to Spanish
ddev drush ai:translate-entity taxonomy_term "$TIDS" en es

# Translate to Italian
ddev drush ai:translate-entity taxonomy_term "$TIDS" en it

# Translate to French
ddev drush ai:translate-entity taxonomy_term "$TIDS" en fr
```

### Translate Recipes by Language

```bash
# Get Spanish recipe IDs and translate to English
NIDS=$(ddev drush sqlq "SELECT nid FROM node_field_data WHERE type = 'recipe' AND langcode = 'es' LIMIT 100" | tr '\n' ',')
ddev drush ai:translate-entity node "$NIDS" es en
```

## Notes

- Translations skip entities that already have a translation in the target language
- Always review AI-generated translations for accuracy
- Configure language-specific prompts at `/admin/config/ai/ai-translate` for better results
- The module respects Drupal's content translation settings for each entity type
