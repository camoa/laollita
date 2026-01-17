<?php

namespace Drupal\recetas_shopping_list\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\ai_agents\Task\Task;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller for generating shopping lists from recipes.
 */
class ShoppingListController extends ControllerBase {

  /**
   * The AI agent plugin manager.
   */
  protected AiAgentManager $agentManager;

  /**
   * The AI provider plugin manager.
   */
  protected AiProviderPluginManager $providerManager;

  /**
   * Constructs a ShoppingListController object.
   */
  public function __construct(
    AiAgentManager $agent_manager,
    AiProviderPluginManager $provider_manager,
    LanguageManagerInterface $language_manager,
    MessengerInterface $messenger
  ) {
    $this->agentManager = $agent_manager;
    $this->providerManager = $provider_manager;
    $this->languageManager = $language_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_agents'),
      $container->get('ai.provider'),
      $container->get('language_manager'),
      $container->get('messenger')
    );
  }

  /**
   * Generate shopping list for a node in the current language.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The shopping list node.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to node view.
   */
  public function generate(NodeInterface $node): RedirectResponse {
    // Validate node type.
    if ($node->bundle() !== 'shopping_list') {
      throw new BadRequestHttpException('Invalid node type. Only shopping_list nodes are supported.');
    }

    // Get current language.
    $current_language = $this->languageManager->getCurrentLanguage()->getId();

    // Get recipes from field_recipes.
    if ($node->get('field_recipes')->isEmpty()) {
      $this->messenger->addError($this->t('Please add at least one recipe before generating the shopping list.'));
      return $this->redirect('entity.node.edit_form', ['node' => $node->id()]);
    }

    $recipes = $node->get('field_recipes')->referencedEntities();

    if (empty($recipes)) {
      $this->messenger->addError($this->t('No valid recipes found.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    // Get desired servings from shopping list.
    $desired_servings = 4;
    if ($node->hasField('field_number_of_servings') && !$node->get('field_number_of_servings')->isEmpty()) {
      $desired_servings = (int) $node->get('field_number_of_servings')->value;
    }

    // Extract ingredients from recipes in current language.
    $all_ingredients = [];
    $recipe_data = [];

    foreach ($recipes as $recipe) {
      // Get translation of recipe for current language.
      if ($recipe->hasTranslation($current_language)) {
        $translated_recipe = $recipe->getTranslation($current_language);
      }
      else {
        $translated_recipe = $recipe;
      }

      // Get recipe's default servings.
      $recipe_servings = 4;
      if ($translated_recipe->hasField('field_number_of_servings') && !$translated_recipe->get('field_number_of_servings')->isEmpty()) {
        $recipe_servings = (int) $translated_recipe->get('field_number_of_servings')->value;
      }

      $recipe_data[] = [
        'title' => $translated_recipe->getTitle(),
        'default_servings' => $recipe_servings,
      ];

      // Extract ingredients.
      if (!$translated_recipe->hasField('field_recipe_ingredients') || $translated_recipe->get('field_recipe_ingredients')->isEmpty()) {
        continue;
      }

      $ingredients = $translated_recipe->get('field_recipe_ingredients')->getValue();

      foreach ($ingredients as $item) {
        // Load unit taxonomy term if it exists.
        $unit_name = '';
        if (!empty($item['unit'])) {
          $unit_term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($item['unit']);
          if ($unit_term) {
            $unit_name = $unit_term->getName();
          }
        }

        $all_ingredients[] = [
          'recipe' => $translated_recipe->getTitle(),
          'recipe_default_servings' => $recipe_servings,
          'amount' => $item['amount'] ?? '',
          'unit' => $unit_name,
          'ingredient' => $item['ingredient'] ?? '',
        ];
      }
    }

    if (empty($all_ingredients)) {
      $this->messenger->addError($this->t('No ingredients found in the selected recipes.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    // Build context for AI agent.
    $context = [
      'language' => $current_language,
      'desired_servings' => $desired_servings,
      'recipes' => $recipe_data,
      'ingredients' => $all_ingredients,
      'total_recipes' => count($recipes),
    ];

    try {
      // Get default AI provider configuration.
      $defaults = $this->providerManager->getDefaultProviderForOperationType('chat_with_complex_json');
      if (empty($defaults)) {
        $this->messenger->addError($this->t('No default AI provider configured for chat operations. Please configure one in AI settings.'));
        return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
      }

      // Create and configure agent.
      $agent = $this->agentManager->createInstance('shopping_list_generator');
      $provider = $this->providerManager->createInstance($defaults['provider_id']);
      $agent->setAiProvider($provider);
      $agent->setModelName($defaults['model_id']);
      $agent->setAiConfiguration([]);
      $agent->setCreateDirectly(TRUE);

      // Pass context to agent via task description.
      $context_json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      $task = new Task("Generate shopping list from " . count($recipes) . " recipes\n\nContext:\n" . $context_json);
      $agent->setTask($task);

      // Execute agent and get result.
      $agent->determineSolvability();
      $solution = $agent->solve();

      if (empty($solution)) {
        $this->messenger->addError($this->t('The AI agent returned an empty response. Please check the agent configuration and try again.'));
        return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
      }

      // Strip markdown code block markers if present.
      $solution = preg_replace('/^```html\s*\n?/i', '', $solution);
      $solution = preg_replace('/\n?```\s*$/i', '', $solution);
      $solution = trim($solution);

    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Failed to generate shopping list: @message', ['@message' => $e->getMessage()]));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    // Save to appropriate language translation.
    if ($node->hasTranslation($current_language)) {
      $translated_node = $node->getTranslation($current_language);
    }
    else {
      $translated_node = $node->addTranslation($current_language, $node->toArray());
    }

    $translated_node->set('field_shopping_list', [
      'value' => $solution,
      'format' => 'full_html',
    ]);
    $translated_node->save();

    $this->messenger->addStatus($this->t('Shopping list generated successfully!'));

    // Redirect to node view in current language.
    $url = Url::fromRoute('entity.node.canonical', [
      'node' => $node->id(),
    ], [
      'language' => $this->languageManager->getLanguage($current_language),
    ]);

    return new RedirectResponse($url->toString());
  }

}
