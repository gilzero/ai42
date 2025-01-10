# Events

There are two important events that are currently available in the AI module. One that is triggered before the request is being sent and one that is triggered before the response is given from the AI Provider plugin.

These two makes it possible to change prompts, change responses, log, find bugs etc.

There is also an event that is triggered when an AI provider gets uninstalled/disabled. This is good for 3rd party modules that might rely on a specific provider existing due to 3rd party provider settings.

## Example #1: Pre request.

You have a module where you want to make sure to filter out certain words that are important for your IP, so they don't get sent to OpenAI when using the AI API Explorer.

You do this by creating an event subscriber, something like this.

```php
<?php

namespace Drupal\ai_logging\EventSubscriber;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Check for IP.
 */
class IpCheckSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The pre generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'checkBeforeRequest',
    ];
  }

  /**
   * Change IP before sending.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The event to check.
   */
  public function checkBeforeRequest(PreGenerateResponseEvent $event) {
    // Only do AI API Explorer.
    if (in_array('ai_api_explorer', $event->getTags()) && $event->getOperationType() == 'chat') {
      // Fictional intellectual property.
      $ip = [
        'secret_ip',
        'my_secret_word',
        'do_not_send_this',
      ];
      $messages = $this->getInput();
      foreach ($messages as $key => $message) {
        $messages[$key]->setText(str_replace($ip, 'censored', $message->getText()));
      }
      $event->setInput($messages);
    }
  }

}

```

## Example #2: Post request.

You have a module where you want to log how many images you created in total.

You do this by creating an event subscriber, something like this.

```php
<?php

namespace Drupal\ai_logging\EventSubscriber;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Counts images.
 */
class CountImagesSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The post generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PostGenerateResponseEvent::EVENT_NAME => 'countImages',
    ];
  }

  /**
   * Change IP after sending.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The event to count.
   */
  public function countImages(PreGenerateResponseEvent $event) {
    // Only do AI API Explorer.
    if ($event->getOperationType() == 'text-to-image') {
      $pseudoCounter->countOneMore();
    }
  }

}

```

## Example #3: Provider Disabled.

You have a third party module that is dependent on a provider called dropai.

You need to make changes to your settings when that provider is being uninstalled.


```php
<?php

namespace Drupal\ai_logging\EventSubscriber;

use Drupal\ai\Event\ProviderDisabledEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets new provider when picked provider is disabled.
 */
class SetNewProvider implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The ProviderDisabledEvent event.
   */
  public static function getSubscribedEvents(): array {
    return [
      ProviderDisabledEvent::EVENT_NAME => 'countImages',
    ];
  }

  /**
   * Change setting when the provider you have is disabled..
   *
   * @param \Drupal\ai\Event\ProviderDisabledEvent $event
   *   The event disabled.
   */
  public function countImages(ProviderDisabledEvent $event) {
    if ($event->getProviderId() == 'dropai') {
      // Whatever custom code you need here, just example code.
      $myconfig->provider = 'default';
    }
  }

}

```
