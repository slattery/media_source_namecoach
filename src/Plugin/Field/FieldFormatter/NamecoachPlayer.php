<?php

namespace Drupal\media_source_namecoach\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;

/**
 * Plugin implementation of the 'media_url' formatter.
 *
 * @FieldFormatter(
 *   id = "namecoach_player",
 *   label = @Translation("Namecoach Display"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class NamecoachPlayer extends EntityReferenceFormatterBase {
 
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'namecoach_format' => 'namecoach_player',
    ] + parent::defaultSettings();
  }


  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['namecoach_format'] = [
      '#type' => 'select',
      '#title' => $this->t('NameCoach display options'),
      '#options' => [
        'namecoach_player' => 'Embedded JS Player Icon',
        'namecoach_iframe' => 'HTML iframe with Player Icon',
        'namecoach_page'   => 'Link to participant NameCoach page',
        ],
      '#default_value' => 'namecoach_player',
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $namecoach_format = $this->getSetting('namecoach_format');

    $summary[] = $this->t('NameCoach display: @namecoach_format', ['@namecoach_format' => $namecoach_format]);
    return $summary;
  }


  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements    = [];
    $format      = $this->getSetting('namecoach_format');
    $media_items = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return $elements;
    }

    $formatmap = [
      'namecoach_player' => 'field_media_namecoach_player_txt',
      'namecoach_iframe' => 'field_media_namecoach_iframe_txt',
      'namecoach_page'   => 'field_media_namecoach_page_link',
    ];

    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {
      // Only handle media objects.
      if ($media instanceof MediaInterface) {
        if (($source = $media->getSource()) && $source instanceof \Drupal\media_source_namecoach\Plugin\media\Source\Namecoach ) {
          switch($format){
            case 'namecoach_player':
              $value = $media->field_media_namecoach_player_txt->value;
              break;
            case 'namecoach_iframe':
              $value = $media->field_media_namecoach_iframe_txt->value;
              break;
            case 'namecoach_page':
              $value = $media->field_media_namecoach_page_link->value;
              break;
            default:
              $value = $media->field_media_namecoach_player_txt->value;
              break;
          }

          $elements[$delta] = [
            '#type' => 'inline_template',
            '#template' => '{{ value|raw }}',
            '#context' => [
              'value' => $value,
            ],
          ];
          // Add cacheability of each item in the field.
          // $this->renderer->addCacheableDependency($elements[$delta], $media);
        }
      }
    }

    return $elements;
  }

}
