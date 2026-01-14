<?php

namespace Drupal\recetas_views\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\ai_translate\TextTranslatorInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Translates a recipe to English using AI Translate.
 */
#[Action(
  id: 'recetas_translate_to_english',
  label: new TranslatableMarkup('Translate recipe to English (AI)'),
  type: 'node'
)]
class TranslateRecipeToEnglish extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The text extractor service.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected $textExtractor;

  /**
   * The text translator service.
   *
   * @var \Drupal\ai_translate\TextTranslatorInterface
   */
  protected $textTranslator;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a TranslateRecipeToEnglish object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai_translate\TextExtractorInterface $text_extractor
   *   The text extractor service.
   * @param \Drupal\ai_translate\TextTranslatorInterface $text_translator
   *   The text translator service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TextExtractorInterface $text_extractor,
    TextTranslatorInterface $text_translator,
    MessengerInterface $messenger,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->textExtractor = $text_extractor;
    $this->textTranslator = $text_translator;
    $this->messenger = $messenger;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_translate.text_extractor'),
      $container->get('ai_translate.text_translator'),
      $container->get('messenger'),
      $container->get('language_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity instanceof NodeInterface) {
      return;
    }

    // Only translate recipe nodes.
    if ($entity->bundle() !== 'recipe') {
      return;
    }

    $source_langcode = $entity->language()->getId();
    $target_langcode = 'en';

    // Skip if already in English.
    if ($source_langcode === $target_langcode) {
      $this->messenger->addWarning($this->t('Recipe "@title" is already in English.', [
        '@title' => $entity->label(),
      ]));
      return;
    }

    // Check if English translation already exists.
    if ($entity->hasTranslation($target_langcode)) {
      $this->messenger->addWarning($this->t('Recipe "@title" already has an English translation.', [
        '@title' => $entity->label(),
      ]));
      return;
    }

    try {
      // Get language names for the translator.
      $langNames = $this->languageManager->getNativeLanguages();

      // Extract text from entity fields.
      $textMetadata = $this->textExtractor->extractTextMetadata($entity);

      // Translate each field.
      foreach ($textMetadata as &$singleField) {
        foreach ($singleField['_columns'] as $column) {
          $singleField['translated'][$column] = '';
          if (!empty($singleField[$column])) {
            $singleField['translated'][$column] = $this->textTranslator->translateContent(
              $singleField[$column],
              $langNames[$target_langcode],
              $langNames[$source_langcode] ?? NULL
            );
          }
        }

        // Decode HTML entities in translation.
        foreach ($singleField['translated'] as &$translated_text_item) {
          $translated_text_item = html_entity_decode($translated_text_item);
        }
      }

      // Create translation.
      $translation = $entity->addTranslation($target_langcode, $entity->toArray());
      $this->textExtractor->insertTextMetadata($translation, $textMetadata);

      // Save the translation.
      $entityStorage = $this->entityTypeManager->getStorage('node');
      $entityStorage->save($translation);

      $this->messenger->addStatus($this->t('Successfully translated "@title" from @source to English.', [
        '@title' => $entity->label(),
        '@source' => $source_langcode,
      ]));
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Failed to translate "@title": @error', [
        '@title' => $entity->label(),
        '@error' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\node\NodeInterface $object */
    $result = $object->access('update', $account, TRUE)
      ->andIf($object->status->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

}
