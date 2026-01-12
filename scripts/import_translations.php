<?php

/**
 * Import English translations for existing Spanish recipes.
 *
 * Usage: ddev drush php:script scripts/import_translations.php /path/to/english.json
 *
 * JSON format expected:
 * {
 *   "recipes": [
 *     {
 *       "id": "recipe-id-matching-spanish",
 *       "langcode": "en",
 *       "title": "English Title",
 *       "instructions": "<ol><li>Step 1</li></ol>",
 *       ...
 *     }
 *   ]
 * }
 */

use Drupal\node\Entity\Node;

// Get JSON file path from argument
$json_file = $extra[0] ?? NULL;

if (!$json_file || !file_exists($json_file)) {
  echo "Usage: ddev drush php:script scripts/import_translations.php /path/to/english.json\n";
  echo "File not found: $json_file\n";
  return;
}

$json = file_get_contents($json_file);
$data = json_decode($json, TRUE);

if (!$data || !isset($data['recipes'])) {
  echo "Invalid JSON format. Expected {\"recipes\": [...]}\n";
  return;
}

$target_langcode = 'en'; // Target language for translations
$created = 0;
$skipped = 0;
$errors = 0;

foreach ($data['recipes'] as $recipe) {
  $recipe_id = $recipe['id'] ?? NULL;

  if (!$recipe_id) {
    echo "Skipping recipe without ID\n";
    $errors++;
    continue;
  }

  // Find existing node by recipe_id field
  $query = \Drupal::entityQuery('node')
    ->condition('type', 'recipe')
    ->condition('field_recipe_id', $recipe_id)
    ->accessCheck(FALSE)
    ->range(0, 1);

  $nids = $query->execute();

  if (empty($nids)) {
    echo "No node found for recipe_id: $recipe_id\n";
    $errors++;
    continue;
  }

  $nid = reset($nids);
  $node = Node::load($nid);

  if (!$node) {
    echo "Could not load node $nid\n";
    $errors++;
    continue;
  }

  // Check if translation already exists
  if ($node->hasTranslation($target_langcode)) {
    // Update existing translation
    $translation = $node->getTranslation($target_langcode);
  } else {
    // Create new translation
    $translation = $node->addTranslation($target_langcode);
  }

  // Set translated fields
  if (isset($recipe['title'])) {
    $translation->setTitle($recipe['title']);
  }

  if (isset($recipe['instructions'])) {
    $translation->set('field_recipe_instruction', [
      'value' => $recipe['instructions'],
      'format' => 'basic_html',
    ]);
  }

  if (isset($recipe['description'])) {
    $translation->set('field_summary', $recipe['description']);
  }

  try {
    $translation->save();
    $created++;
    if ($created % 50 == 0) {
      echo "Processed $created translations...\n";
    }
  } catch (\Exception $e) {
    echo "Error saving translation for $recipe_id: " . $e->getMessage() . "\n";
    $errors++;
  }
}

echo "\n=== Import Complete ===\n";
echo "Created/Updated: $created\n";
echo "Errors: $errors\n";
