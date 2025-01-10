<?php

namespace Drupal\gemini_2_provider\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\gemini_2_provider\GeminiChatMessageIterator;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the Google's Gemini.
 */
#[AiProvider(
  id: 'gemini2',
  label: new TranslatableMarkup('Gemini 2')
)]
class GeminiProvider extends AiProviderClientBase implements ChatInterface {

  /**
   * The Gemini Client.
   *
   * @var \Gemini\Client|null
   */
  protected $client;

    /**
     * Config factory.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected ConfigFactoryInterface $configFactory;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

    /**
     * Model ID.
     *
     * @var string
     */
    protected string $modelId = '';


  /**
   * Run moderation call, before a normal call.
   *
   * @var bool
   */
  protected bool $moderation = TRUE;

  /**
   * If system message is presented, we store here.
   *
   * @var Content
   */
  protected Content|null $systemMessage = NULL;


    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('config.factory')
        );
    }


  /**
   * {@inheritdoc}
   * @param string|null $operation_type
   * @param array $capabilities
   */
  public function getConfiguredModels(string $operation_type = NULL, array $capabilities = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   * @param string|null $operation_type
   * @param array $capabilities
   */
  public function isUsable(string $operation_type = NULL, array $capabilities = []): bool {
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }

    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    // @todo We need to add other operation types here later.
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('gemini_2_provider.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    $definition = Yaml::parseFile(
      $this->moduleHandler->getModule('gemini_2_provider')
        ->getPath() . '/definitions/api_defaults.yml'
    );
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();

    // prepare inputs for gemini
    $chat_input = $input;
    if ($input instanceof ChatInput) {
      $chat_input = [];

      if ($this->systemMessage) {
        $role = Role::from('model');
        $chat_input[] = Content::parse($this->systemMessage, $role);
      }

      foreach ($input->getMessages() as $message) {
        if ($message->getRole() == 'system') {
          $message->setRole('model');
        }

        if (!in_array($message->getRole(), ['model', 'user'])) {
          $error_message = sprintf('The role %s, is not supported by Gemini Provider.', $message->getRole());
          throw new AiResponseErrorException($error_message);
        }
        $role = Role::from($message->getRole());
        $chat_input[] = Content::parse($message->getText(), $role);
      }
    }

    // set configuration
    $config = new GenerationConfig(...$this->getConfiguration());

    // generate response
    $response = $this->client->generativeModel($this->modelId)
      ->withGenerationConfig($config);

    if ($this->streamed) {
      $streamedIterator = $response->streamGenerateContent(...$chat_input);
      $message = new GeminiChatMessageIterator($streamedIterator);
    } else {
      $response = $response->generateContent(...$chat_input);
      $text = '';
      if (!empty($response->parts())) {
        $text = $response->text();
      }

      $message = new ChatMessage('', $text);
    }

    return new ChatOutput($message, $response, []);
  }

  /**
   * Enables moderation response, for all next coming responses.
   */
  public function enableModeration(): void {
    $this->moderation = TRUE;
  }

  /**
   * Disables moderation response, for all next coming responses.
   */
  public function disableModeration(): void {
    $this->moderation = FALSE;
  }

  /**
   * Gets the raw client.
   *
   * @param string $api_key
   *   If the API key should be hot swapped.
   *
   * @return \Gemini\Client
   *   The Gemini client.
   */
  public function getClient(string $api_key = '') {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }

    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the Gemini Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }
        $this->modelId = $this->configFactory->get('gemini_2_provider.settings')->get('model_id');


      $this->client = \Gemini::factory()
        ->withApiKey($this->apiKey)
        ->withHttpClient($this->httpClient)
        ->make();
    }
  }

  /**
   * Load API key from key module.
   *
   * @return string
   *   The API key.
   */
  protected function loadApiKey(): string {
    return $this->keyRepository->getKey($this->getConfig()->get('api_key'))
      ->getKeyValue();
  }

  public function setConfiguration(array $configuration): void {
    parent::setConfiguration($configuration);

    // normalize config for Gemini
    $this->configuration['stopSequences'] = isset($this->configuration['stopSequences'])
      ? explode(',', $this->configuration['stopSequences'])
      : [];

    // unset formatting for now TODO: need to implement later
    unset ($this->configuration['responseSchema']);
    unset ($this->configuration['responseMimeType']);
  }

  public function setChatSystemRole(string|null $message): void {
    if (!empty($message)) {
      $role = Role::from('model');
      $this->systemMessage = Content::parse($message, $role);
    }
  }

}
