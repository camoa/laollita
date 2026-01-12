<?php

/**
 * Export untranslated Spanish recipes to JSON for batch translation.
 *
 * Usage: ddev drush php:script scripts/export_untranslated.php
 */

use Drupal\node\Entity\Node;

$query = \Drupal::entityQuery('node')
  ->condition('type', 'recipe')
  ->condition('langcode', 'es')
  ->accessCheck(FALSE);

$nids = $query->execute();

// Filter to only those without English translation
$untranslated = [];
foreach (Node::loadMultiple($nids) as $node) {
  if (!$node->hasTranslation('en')) {
    $recipe = [
      'nid' => (int)$node->id(),
      'id' => $node->get('field_recipe_id')->value ?? 'recipe-' . $node->id(),
      'title' => $node->getTitle(),
      'instructions' => $node->get('field_instructions')->value ?? '',
    ];
    $untranslated[] = $recipe;
  }
}

$output = json_encode(['recipes' => $untranslated], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents('/var/www/html/sites/default/files/to_translate_es_en.json', $output);

echo "Exported " . count($untranslated) . " recipes to sites/default/files/to_translate_es_en.json\n";
