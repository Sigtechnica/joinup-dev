<?php

namespace Drupal\collection\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\TimestampFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;

/**
 * Provides a dedicated formatter for collection last update time.
 *
 * The requirement is to render a "time ago" as output of this formatter but
 * because the value would change very often (every second), we will not be able
 * to cache it and, as an effect, the whole page will become uncacheable. For
 * this reason, we'll not return a "time ago" value from server. Instead we
 * display a formatted last update time. Then, in JS enabled browsers, we
 * replace the formatted date/time with a "time ago" expression generated by the
 * timeago jquery plugin (http://timeago.yarp.com). The value rendered by the JS
 * plugin is refreshed each minute. This is degrading nice in browsers with JS
 * disabled where users will be able to see the formatted last updated time.
 *
 * @see http://timeago.yarp.com
 *
 * @FieldFormatter(
 *   id = "last_update_timestamp",
 *   label = @Translation("Joinup Last Update"),
 *   field_types = {
 *     "timestamp",
 *   }
 * )
 */
class LastUpdateFormatter extends TimestampFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'tooltip_format' => 'long',
      'tooltip_format_custom' => '',
      'timeago_settings' => [
        'strings' => [
          'seconds' => 'few seconds',
        ],
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $date_formats = [];
    foreach ($this->dateFormatStorage->loadMultiple() as $machine_name => $value) {
      $date_formats[$machine_name] = $this->t('@name format: @date', [
        '@name' => $value->label(),
        '@date' => $this->dateFormatter->format(\Drupal::time()->getRequestTime(), $machine_name),
      ]);
    }
    $date_formats['custom'] = $this->t('Custom');

    $form['tooltip_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Tooltip format'),
      '#description' => $this->t('Select the date/time format to be used for the tooltip.'),
      '#options' => $date_formats,
      '#default_value' => $this->getSetting('tooltip_format'),
    ];
    $form['tooltip_format_custom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tooltip custom format'),
      '#description' => $this->t('See <a href="http://php.net/manual/function.date.php" target="_blank">the documentation for PHP date formats</a>.'),
      '#default_value' => $this->getSetting('tooltip_format_custom'),
      '#states' => [
        'visible' => [
          [
            ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][tooltip_format]"]' => [
              'value' => 'custom',
            ],
          ],
        ],
      ],
    ];
    $form['timeago_settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('A Yaml representation of settings to be passed to the timeago plugin'),
      '#desciption' => $this->t('Values passed here will be merged into the timeago plugin defaults. See <a href="http://timeago.yarp.com">timeago.yarp.com</a> for the settings structure.'),
      '#default_value' => Yaml::encode($this->getSetting('timeago_settings')),
      '#element_validate' => [[static::class, 'decodeTimeagoSettings']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $date_format = $this->getSetting('date_format');
    $tooltip_format = $this->getSetting('tooltip_format');
    $custom_date_format = $tooltip_format_custom = '';
    $timezone = $this->getSetting('timezone') ?: NULL;
    $langcode = $tooltip_langcode = NULL;

    // If an RFC2822 date format is requested, then the month and day have to
    // be in English. @see http://www.faqs.org/rfcs/rfc2822.html
    if ($date_format === 'custom' && ($custom_date_format = $this->getSetting('custom_date_format')) === 'r') {
      $langcode = 'en';
    }
    if ($tooltip_format === 'custom' && ($tooltip_format_custom = $this->getSetting('tooltip_format_custom')) === 'r') {
      $tooltip_langcode = 'en';
    }

    /** @var \Drupal\rdf_entity\RdfInterface $collection */
    $collection = $items->getEntity();

    // Add all the node group content once.
    $tags = Cache::buildTags('og-group-content', $collection->getCacheTagsToInvalidate());
    // Unfortunately, this doesn't include the solutions.
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $solutions */
    $solutions = $collection->get('field_ar_affiliates');
    /** @var \Drupal\rdf_entity\RdfInterface $solution */
    foreach ($solutions->referencedEntities() as $solution) {
      $tags = Cache::mergeTags($tags, $solution->getCacheTagsToInvalidate());
    }
    // Add the collection tags.
    $tags = Cache::mergeTags($tags, $collection->getCacheTagsToInvalidate());

    return [
      [
        '#theme' => 'time',
        '#attributes' => [
          // This attribute is used by the jquery plugin to get the time.
          'datetime' => $this->dateFormatter->format($items[0]->value, 'html_datetime', $timezone, $langcode),
          'class' => [
            'js-collection-last-update',
          ],
          // Show a tooltip on mouse hover so the user can read the exact date.
          'title' => $this->dateFormatter->format($items[0]->value, $tooltip_format, $tooltip_format_custom, $timezone, $tooltip_langcode),
        ],
        // Non-JS browsers will display this formatted date/time but most of the
        // browsers will replace this inner text with a "time ago" string
        // generated by the timeago jquery plugin (http://timeago.yarp.com).
        // @see web/modules/custom/collection/js/last_update.js
        // @see http://timeago.yarp.com.
        '#text' => $this->dateFormatter->format($items[0]->value, $date_format, $custom_date_format, $timezone, $langcode),
      ],
      '#attached' => [
        'library' => [
          'collection/timeago',
          'collection/last_update',
        ],
        'drupalSettings' => [
          'collection' => [
            // Site builders are able to configure the plugin settings.
            'timeagoSettings' => $this->getSetting('timeago_settings'),
          ],
        ],
      ],
      '#cache' => [
        'tags' => $tags,
        'contexts' => [
          'timezone',
        ],
      ],
    ];
  }

  /**
   * Provides an element validation callback for 'timeago_settings'.
   *
   * We use this element validation callback to decode the timeago settings Yaml
   * in order to store it as an array.
   *
   * @param array $element
   *   The element render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $complete_form
   *   The full form render array.
   */
  public static function decodeTimeagoSettings(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $trail = [
      'fields',
      'last_update',
      'settings_edit_form',
      'settings',
      'timeago_settings',
    ];
    $timeago_settings = [];
    if ($yaml = trim($form_state->getValue($trail))) {
      try {
        $timeago_settings = Yaml::decode($yaml) ?: [];
      }
      catch (\Exception $exception) {
        // Nothing to do.
      }
    }
    $form_state->setValue($trail, $timeago_settings);
  }

}
