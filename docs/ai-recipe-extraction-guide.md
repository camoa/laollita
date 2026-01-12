# AI Recipe Extraction Guide

Use this guide to extract recipes from PDFs, images, websites, or handwritten notes and format them for import into the Recetas family recipe database.

## Output Format

Generate a JSON file with this structure:

```json
{
  "recipes": [
    {
      "id": "unique-recipe-id",
      "langcode": "xx",
      "title": "...",
      ...
    }
  ]
}
```

## Field Reference

### Required Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Unique identifier (lowercase, hyphens, no spaces). Example: `pasta-carbonara`, `paella-valenciana-001` |
| `langcode` | string | Language of the original recipe: `en`, `es`, `fr`, or `it` |
| `title` | string | Recipe title **in original language** |
| `ingredients` | array | List of ingredients (see below) |
| `instructions` | string | Cooking steps **in original language**, formatted as HTML `<ol><li>...</li></ol>` |

### Optional Fields

| Field | Type | Description |
|-------|------|-------------|
| `description` | string | Short summary **in original language** |
| `prepTime` | integer | Preparation time in minutes |
| `cookTime` | integer | Cooking time in minutes |
| `servings` | integer | Number of portions |
| `difficulty` | string | **Must be one of:** `easy`, `medium`, `hard` |
| `category` | string | **In English** (see allowed values below) |
| `tags` | array | **In English, lowercase** (see examples below) |
| `source` | string | Attribution (keep original: "Nonna Maria", "Abuela Carmen") |
| `origin_country` | string | **In English** (see allowed values below) |
| `origin_region` | string | **In English** (see allowed values below) |

---

## Language Rules

### Keep in ORIGINAL language:
- `title` - "Pasta alla Carbonara" (not "Carbonara Pasta")
- `description` - "La vera carbonara romana..."
- `instructions` - HTML formatted: `<ol><li>Cuocere la pasta...</li></ol>`
- `ingredients[].ingredient` - "guanciale", "pecorino romano"
- `source` - "Nonna Maria", "Abuela Carmen"

### Always in ENGLISH:
- `category` - "Main Course" (not "Primo Piatto")
- `tags` - ["pasta", "traditional"] (not ["pasta", "tradizionale"])
- `origin_country` - "Italy" (not "Italia")
- `origin_region` - "Tuscany" (not "Toscana")
- `difficulty` - "easy", "medium", "hard"

---

## Allowed Values

### `difficulty` (required: use English machine name)
| Value | When to use |
|-------|-------------|
| `easy` | Simple recipes, few steps, basic techniques |
| `medium` | Moderate complexity, some skill required |
| `hard` | Advanced techniques, many steps, precision needed |

### `category` (use English)
Any category value is accepted - new categories are created automatically.
Examples: `Appetizer`, `Main Course`, `Dessert`, `Soup`, `Salad`, `Bread`, `Beverage`, `Sauce`, `Snack`, `Street Food`, `Stew`, `Casserole`, etc.

### `origin_country` (use English)
Any country value is accepted - new countries are created automatically.
Examples: `Italy`, `Spain`, `France`, `Colombia`, `Mexico`, `Costa Rica`, `Argentina`, `Peru`, etc.

### `origin_region` (use English)
Any region value is accepted - new regions are created automatically.
Use the English name for the region. Examples: `Tuscany`, `Valencia`, `Antioquia`, `Oaxaca`, `Provence`, etc.

### `unit` (universal abbreviations)
| Unit | Use for |
|------|---------|
| `g` | Grams (solid ingredients) |
| `kg` | Kilograms |
| `ml` | Milliliters (liquids) |
| `l` | Liters |
| `dl` | Deciliters |
| `cl` | Centiliters |
| `oz` | Ounces |
| `lb` | Pounds |
| `cup` | Cups |
| `tbsp` | Tablespoons |
| `tsp` | Teaspoons |
| `piece` | Whole items (eggs, onions) |
| `pinch` | Small amount (salt, spices) |
| `bunch` | Herbs, greens |
| `clove` | Garlic |
| `slice` | Bread, cheese, meat |
| `can` | Canned goods |
| `packet` | Packaged items |
| `jar` | Jarred items |
| `` (empty) | When no unit applies |

### `tags` (English, lowercase)
Any tag values are accepted - new tags are created automatically.
Use lowercase, hyphenated for multi-word tags.
Examples: `traditional`, `quick`, `vegetarian`, `comfort-food`, `grilled`, `christmas`, `seafood`, `gluten-free`, etc.

---

## Ingredient Format

Each ingredient is an object with `amount`, `unit`, and `ingredient`:

```json
{
  "amount": "400",
  "unit": "g",
  "ingredient": "spaghetti"
}
```

### Amount formatting:
- Whole numbers: `"400"`, `"2"`, `"100"`
- Fractions: `"1/2"`, `"1/4"`, `"3/4"`
- Ranges: `"400-500"`, `"2-3"`
- Mixed: `"1 1/2"` (one and a half)
- Empty if "to taste": `""`

### Examples:
```json
{"amount": "400", "unit": "g", "ingredient": "spaghetti"}
{"amount": "4", "unit": "piece", "ingredient": "tuorli d'uovo"}
{"amount": "1/2", "unit": "cup", "ingredient": "pecorino grattugiato"}
{"amount": "", "unit": "pinch", "ingredient": "sale"}
{"amount": "2-3", "unit": "clove", "ingredient": "aglio"}
```

---

## Complete Example

### Input (Italian recipe)
```
PASTA ALLA CARBONARA
Ricetta tradizionale romana

Tempo di preparazione: 15 minuti
Tempo di cottura: 20 minuti
Porzioni: 4
Difficoltà: Media

Ingredienti:
- 400g spaghetti
- 200g guanciale
- 4 tuorli d'uovo
- 100g pecorino romano grattugiato
- Pepe nero q.b.

Preparazione:
1. Cuocere la pasta in abbondante acqua salata.
2. Nel frattempo, tagliare il guanciale a listarelle...
...

Fonte: Nonna Maria, Roma
```

### Output JSON
```json
{
  "recipes": [
    {
      "id": "pasta-carbonara",
      "langcode": "it",
      "title": "Pasta alla Carbonara",
      "description": "Ricetta tradizionale romana",
      "prepTime": 15,
      "cookTime": 20,
      "servings": 4,
      "difficulty": "medium",
      "ingredients": [
        {"amount": "400", "unit": "g", "ingredient": "spaghetti"},
        {"amount": "200", "unit": "g", "ingredient": "guanciale"},
        {"amount": "4", "unit": "piece", "ingredient": "tuorli d'uovo"},
        {"amount": "100", "unit": "g", "ingredient": "pecorino romano grattugiato"},
        {"amount": "", "unit": "pinch", "ingredient": "pepe nero"}
      ],
      "instructions": "<ol><li>Cuocere la pasta in abbondante acqua salata.</li><li>Nel frattempo, tagliare il guanciale a listarelle e rosolare in padella.</li><li>In una ciotola, sbattere i tuorli con il pecorino grattugiato.</li><li>Scolare la pasta e unirla al guanciale, poi aggiungere il composto di uova e pecorino.</li><li>Mescolare velocemente e servire con pepe nero macinato.</li></ol>",
      "category": "Main Course",
      "tags": ["pasta", "traditional", "roman", "quick"],
      "source": "Nonna Maria",
      "origin_country": "Italy",
      "origin_region": "Lazio"
    }
  ]
}
```

---

## Extraction Tips

1. **Detect language first** - Look for language clues to set `langcode`
2. **Keep original ingredient names** - Don't translate "guanciale" to "pork cheek"
3. **Normalize difficulty** - Map any difficulty description to easy/medium/hard
4. **Convert times to minutes** - "1 hour 30 min" → `90`
5. **Infer category from context** - "Primo piatto" → "Main Course"
6. **Generate meaningful ID** - Use recipe name, lowercase, hyphens
7. **Preserve source attribution** - Keep names in original form
8. **Research region if unclear** - Match dishes to their traditional regions
9. **Format instructions as HTML** - Use `<ol><li>Step</li></ol>` for proper display
10. **Extract ONLY the recipe** - Do not include adjacent content, footnotes, or other recipes

---

## Multiple Recipes

One JSON file can contain multiple recipes:

```json
{
  "recipes": [
    {
      "id": "pasta-carbonara",
      "langcode": "it",
      ...
    },
    {
      "id": "paella-valenciana",
      "langcode": "es",
      ...
    },
    {
      "id": "coq-au-vin",
      "langcode": "fr",
      ...
    }
  ]
}
```

---

## Validation Checklist

Before submitting, verify:

- [ ] `id` is unique, lowercase, uses hyphens
- [ ] `langcode` matches the recipe's original language
- [ ] `title`, `description`, `instructions` are in original language
- [ ] `ingredients[].ingredient` is in original language
- [ ] `difficulty` is exactly: `easy`, `medium`, or `hard`
- [ ] `category` is in English
- [ ] `tags` are in English, lowercase
- [ ] `origin_country` and `origin_region` are in English
- [ ] `unit` uses standard abbreviations
- [ ] Times are in minutes (integers)
- [ ] JSON is valid (no trailing commas, proper quotes)
