<?php

declare(strict_types=1);

namespace Drupal\gemini_2_provider\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Gemini Provider settings.
 */
final class GeminiConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'gemini_2_provider.settings';

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new AnthropicConfigForm object.
   */
  final public function __construct(AiProviderPluginManager $ai_provider_manager, ModuleHandlerInterface $module_handler) {
    $this->aiProviderManager = $ai_provider_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'gemini_2_provider_gemini_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['gemini_2_provider.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Gemini API Key'),
      '#description' => $this->t('The Gemini API Key.'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['model_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Gemini Model ID'),
        '#description' => $this->t('The Gemini Model to use.'),
        '#default_value' => 'gemini-2.0-flash-exp',
        '#options' => [
            'gemini-2.0-flash-exp' => 'Gemini 2 Flash Experimental',
        ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('gemini_2_provider.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('model_id', $form_state->getValue('model_id'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
