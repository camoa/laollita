<?php

/**
 * @file
 * Migration script to convert old field_ingredients to new field_recipe_ingredients.
 *
 * Run with: ddev drush php:script scripts/migrate_ingredients.php
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

// Common unit patterns to match.
$unit_patterns = [
  'tbsp' => 'tbsp',
  'tablespoon' => 'tbsp',
  'tablespoons' => 'tbsp',
  'tsp' => 'tsp',
  'teaspoon' => 'tsp',
  'teaspoons' => 'tsp',
  'cup' => 'cup',
  'cups' => 'cup',
  'ml' => 'ml',
  'l' => 'l',
  'litre' => 'l',
  'litres' => 'l',
  'liter' => 'l',
  'liters' => 'l',
  'g' => 'g',
  'gram' => 'g',
  'grams' => 'g',
  'kg' => 'kg',
  'kilogram' => 'kg',
  'kilograms' => 'kg',
  'oz' => 'oz',
  'ounce' => 'oz',
  'ounces' => 'oz',
  'lb' => 'lb',
  'lbs' => 'lb',
  'pound' => 'lb',
  'pounds' => 'lb',
  'pinch' => 'pinch',
  'bunch' => 'bunch',
  'bunches' => 'bunch',
  'clove' => 'clove',
  'cloves' => 'clove',
  'slice' => 'slice',
  'slices' => 'slice',
  'can' => 'can',
  'cans' => 'can',
  'pack' => 'pack',
  'packs' => 'pack',
  'sachet' => 'sachet',
  'sachets' => 'sachet',
  'piece' => 'piece',
  'pieces' => 'piece',
];

/**
 * Parse an ingredient string into amount, unit, and ingredient name.
 */
function parse_ingredient($string) {
  global $unit_patterns;

  $string = trim($string);
  if (empty($string)) {
    return NULL;
  }

  $amount = NULL;
  $unit = NULL;
  $ingredient = $string;

  // Pattern: "200g flour" or "2 cups milk" or "1/2 tsp salt"
  // Match: number (with optional fraction) + optional unit + ingredient

  // Try to match amount + optional attached unit at the beginning.
  // Handles: "200g flour", "2 cups milk", "1/2 tsp salt", "280ml water"
  // Pattern: number (with optional fraction) + optional unit (attached or separated) + ingredient
  // Unit must be followed by space or end of string (word boundary), not attached to letters
  $combined_pattern = '/^(\d+(?:[.,\/]\d+)?|[¼½¾⅓⅔])\s*(g|kg|ml|l|tbsp|tsp|cup|cups|oz|lb|lbs|pinch|bunch|bunches|clove|cloves|slice|slices|can|cans|pack|packs|sachet|sachets|piece|pieces)?(?=\s|$)/iu';

  if (preg_match($combined_pattern, $string, $matches)) {
    $amount_str = $matches[1];
    $unit = !empty($matches[2]) ? strtolower($matches[2]) : NULL;
    // Lookahead doesn't consume, so we need to calculate the length properly
    $consumed_length = strlen($amount_str) + (isset($matches[2]) ? strlen($matches[2]) : 0);
    $string = trim(substr($string, $consumed_length));

    // Convert fractions to decimals.
    if (strpos($amount_str, '/') !== FALSE) {
      $parts = explode('/', $amount_str);
      $amount = (float) $parts[0] / (float) $parts[1];
    }
    elseif ($amount_str === '¼') {
      $amount = 0.25;
    }
    elseif ($amount_str === '½') {
      $amount = 0.5;
    }
    elseif ($amount_str === '¾') {
      $amount = 0.75;
    }
    elseif ($amount_str === '⅓') {
      $amount = 0.33;
    }
    elseif ($amount_str === '⅔') {
      $amount = 0.67;
    }
    else {
      $amount = (float) str_replace(',', '.', $amount_str);
    }
  }

  // Normalize unit if captured.
  if ($unit) {
    $unit_map = [
      'cups' => 'cup',
      'lbs' => 'lb',
      'bunches' => 'bunch',
      'cloves' => 'clove',
      'slices' => 'slice',
      'cans' => 'can',
      'packs' => 'pack',
      'sachets' => 'sachet',
      'pieces' => 'piece',
    ];
    if (isset($unit_map[$unit])) {
      $unit = $unit_map[$unit];
    }
  }

  // Fallback: try to match unit separately if not already found.
  if (!$unit) {
    foreach ($unit_patterns as $pattern => $normalized) {
      if (preg_match('/^' . preg_quote($pattern, '/') . '\b\s*/i', $string, $matches)) {
        $unit = $normalized;
        $string = trim(substr($string, strlen($matches[0])));
        break;
      }
    }
  }

  // The remaining string is the ingredient name.
  $ingredient = trim($string);

  // Clean up: remove leading "of" or "of the".
  $ingredient = preg_replace('/^of\s+(the\s+)?/i', '', $ingredient);

  return [
    'amount' => $amount,
    'unit' => $unit,
    'ingredient' => $ingredient,
  ];
}

/**
 * Get or create a taxonomy term for a unit.
 */
function get_or_create_unit_term($unit_name) {
  if (empty($unit_name)) {
    return NULL;
  }

  // Look for existing term.
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties([
      'vid' => 'units',
      'name' => $unit_name,
    ]);

  if (!empty($terms)) {
    return reset($terms)->id();
  }

  // Create new term.
  $term = Term::create([
    'vid' => 'units',
    'name' => $unit_name,
  ]);
  $term->save();

  echo "Created unit term: $unit_name\n";
  return $term->id();
}

// Main migration logic.
echo "Starting ingredient migration...\n";

// Load all recipe nodes.
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'recipe')
  ->accessCheck(FALSE)
  ->execute();

echo "Found " . count($nids) . " recipes to migrate.\n";

$migrated = 0;
$skipped = 0;

foreach ($nids as $nid) {
  $node = Node::load($nid);
  if (!$node) {
    continue;
  }

  // Check if already has new ingredients.
  if (!$node->get('field_recipe_ingredients')->isEmpty()) {
    echo "Skipping node {$nid} ({$node->getTitle()}): already has new ingredients.\n";
    $skipped++;
    continue;
  }

  // Get old ingredients.
  $old_ingredients = $node->get('field_ingredients')->getValue();
  if (empty($old_ingredients)) {
    echo "Skipping node {$nid} ({$node->getTitle()}): no old ingredients.\n";
    $skipped++;
    continue;
  }

  $new_ingredients = [];

  foreach ($old_ingredients as $old) {
    $ingredient_string = $old['value'] ?? '';

    // The old field might have multiple ingredients comma-separated.
    // Split by comma but be careful with "200g butter, softened" type strings.
    // For now, treat each value as one ingredient.
    $parsed = parse_ingredient($ingredient_string);

    if ($parsed && !empty($parsed['ingredient'])) {
      $unit_tid = get_or_create_unit_term($parsed['unit']);

      $new_ingredients[] = [
        'amount' => $parsed['amount'],
        'unit' => $unit_tid,
        'ingredient' => $parsed['ingredient'],
      ];
    }
  }

  if (!empty($new_ingredients)) {
    $node->set('field_recipe_ingredients', $new_ingredients);
    $node->save();
    echo "Migrated node {$nid} ({$node->getTitle()}): " . count($new_ingredients) . " ingredients.\n";
    $migrated++;
  }
}

echo "\nMigration complete!\n";
echo "Migrated: $migrated recipes\n";
echo "Skipped: $skipped recipes\n";
