<?php

namespace Drupal\media_source_namecoach\Plugin\media\Source;


use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaSourceFieldConstraintsInterface;
use Drupal\media\MediaTypeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * Namecoach entity media source.
 *
 * @MediaSource(
 *   id = "namecoach",
 *   label = @Translation("NameCoach"),
 *   allowed_field_types = {"string", "string_long"},
 *   default_name_metadata_attribute = "default_name",
 *   default_thumbnail_filename = "namecoach.png",
 *   description = @Translation("Provides behaviors and metadata for NameCoach."),
 *   forms = {
 *     "media_library_add" = "\Drupal\media_source_namecoach\Form\NamecoachForm"
 *   }
 * )
 */
class Namecoach extends MediaSourceBase implements MediaSourceFieldConstraintsInterface {

   /**
   * Namecoach attributes.
   *
   * @var array
   */
   protected $namecoach;

   /**
    * Config factory interface.
    *
    * @var \Drupal\Core\Config\ConfigFactoryInterface
    */
   protected $configFactory;
 

   /**
    * {@inheritdoc}
    */
   public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory) {
     parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
   }
 
   /**
    * {@inheritdoc}
    */
   public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
     return new static(
       $configuration,
       $plugin_id,
       $plugin_definition,
       $container->get('entity_type.manager'),
       $container->get('entity_field.manager'),
       $container->get('plugin.manager.field.field_type'),
       $container->get('config.factory'),
       $container->get('http_client')
     );
   }
 
  /**
  * {@inheritdoc}
  */
  public function getMetadataAttributes() {
    $attributes = [
      'first_name'          => $this->t('First Name as stored at Name Coach'),
      'last_name'           => $this->t('Last Name as stored at Name Coach'),
      'email'               => $this->t('Email Address as stored at Name Coach, used as key'),
      'recording_uuid'      => $this->t('Taken from recording - for isolation and change tracking'),
      'recording_link'      => $this->t('Link to Name Coach audio file'),
      'phonetic_spelling'   => $this->t('Phonetic spelling as stored at Name Coach'),
      'notes'               => $this->t('Notes as stored at Name Coach'),
      'gender_pronouns'     => $this->t('Preferred Pronouns as stored at Name Coach'),
      'photo'               => $this->t('URL to image as stored at Name Coach'),
      'thumbnail_uri'       => $this->t('URI of the thumbnail - default available'),
      'embed_image'         => $this->t('Generated js and markup from NameCoach'),
      'embed_iframe'        => $this->t('Generated iframe markup from NameCoach'),
      'name_badge_link'     => $this->t('URL to participant page at NameCoach'),
      'default_name'        => $this->t('Media item default name'),
    ];
    return $attributes;
  }

  /**
   * {@inheritdoc}
   */

  public function getMetadata(MediaInterface $media, $attribute_name) {
    $source_field = $this->configuration['source_field'];
    $file_system = \Drupal::service('file_system');
    $keymail = $this->getParticipantEmail($media);
    if ($keymail === FALSE) {
      return FALSE;
    }

    $data = $this->ncSearch($keymail);
    if ($data === FALSE) {
      return FALSE;
    }

    $audio_url = '';
    $audio_uuid = '';

    if ($data['recording_link'] != ''){
      preg_match('/(.+recording_)([a-f0-9]+)(\.mp3).*/', $data['recording_link'], $matches, PREG_UNMATCHED_AS_NULL);
      if (count($matches) > 0){
        $audio_url  = $matches[1] . $matches[2] .  $matches[3];
        $audio_uuid =  $matches[2];
      }

    }
 
    switch ($attribute_name) {
      case 'default_name':
        if ($keymail = $this->getMetadata($media, 'email')) {
          return $keymail;
        }
        return parent::getMetadata($media, 'default_name');

      case 'first_name':
        return $data['first_name'];

      case 'last_name':
        return $data['last_name'];

      case 'email':
        return $data['email'];
 
      case 'recording_uuid':
        return $audio_uuid;
 
      case 'recording_link':
        return $audio_url;
 
      case 'phonetic_spelling':
        return $data['phonetic_spelling'];
 
      case 'notes':
        return $data['notes'];
 
      case 'gender_pronouns':
        if ($data['custom_objects'] and $data['custom_objects']['gender_pronouns']) {
          return $data['custom_objects']['gender_pronouns'];
        } else {
          return null;
        }
 
      case 'photo':
        return $data['photo'];

      case 'thumbnail_uri':
        return 'public://media-icons/generic/namecoach.png';
 
      case 'embed_image':
        return $data['embed_image'];

      case 'embed_iframe':
        return $data['embed_iframe'];
 
      case 'name_badge_link':
        return $data['name_badge_link'];
    }

  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    // ami really looking for config:media.type.nc_remote ?
    $apikey = $this->configFactory->get('media_source_namecoach.settings')->get('nc_apikey');

    $form['nc_apikey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your NameCoach API Key'),
      '#default_value' => $apikey,
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Your NameCoach API key gives you permission to search for participant records.'),

    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('media_source_namecoach.settings');
    $config->set('nc_apikey', $form_state->getValue('nc_apikey'))->save();
    //\Drupal::logger('media_source_namecoach')->notice('Are we being called @yeah?', ['@yeah' => $yeah]);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFormDisplay(MediaTypeInterface $type, EntityFormDisplayInterface $display) {
    parent::prepareFormDisplay($type, $display);
    // some sources anticipate an automatically dervied field, we will use a config entity in yml
    // we just want to remove the name field for now.
    // if we allow local storage this will change.
    $display->removeComponent('name');
    // with name, in Source there is a default_name_metadata_attribute config item... which is
    // default_name which we do account for, if not specify...
  }

  /**
   * Returns the emailkey from the source_field.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return string|bool
   *   The email if from the source_field if found. False otherwise.
   */
  protected function getParticipantEmail(MediaInterface $media) {
    $source_field = $this->getSourceFieldDefinition($media->bundle->entity);
    $field_name = $source_field->getName();
    if ($media->hasField($field_name)) {
      $property_name = $source_field->getFieldStorageDefinition()->getMainPropertyName();
      return $media->{$field_name}->{$property_name};
    }
    return FALSE;
  }


  /**
   * Returns participant data for a Namecoach search.
   *
   * @param string $keymail
   *   The NameCoach key.
   *
   * @return array
   *   An array of namecoach record data.
   */

  protected function ncSearch($keymail) {
    $APIKEY = $this->configFactory->get('media_source_namecoach.settings')->get('nc_apikey');
    $guzzle = new Client(['headers' => ['accept' => 'application/json', 'authorization' => $APIKEY ]]);
    $namecoach_req_url = 'https://www.name-coach.com/api/private/v4/participants/search';

    if (!isset($this->namecoach)) {
      try {
        $response = $guzzle->request('POST', $namecoach_req_url, [
          'form_params' => [
              'include'     => 'embeddables,custom_attributes',
              'icon_code'   =>  '2',
              'email_list'  =>  $keymail 
            ]
        ]);
        $jsonres = Json::decode((string) $response->getBody());
        if ($jsonres['meta'] && $jsonres['meta']['total_count'] > 0){
          $this->namecoach = $jsonres['participants'][0];
        } else {
          $this->namecoach = FALSE;
        }
      }
      catch (ClientException $e) {
        $this->namecoach = FALSE;
      }
    }
    return $this->namecoach;
  }


  public function getSourceFieldConstraints() {
    return [
      'namecoach' => [],
    ];
  }



}
