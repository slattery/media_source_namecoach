<?php

namespace Drupal\media_source_namecoach\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Drupal\media_source_namecoach\Plugin\media\Source\Namecoach;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsPreconfigurationInterface;


/**
 * Delete entity action with default confirmation form.
 *
 * @Action(
 *   id = "media_source_namecoach_associate_record_entity",
 *   label = @Translation("Fetch and associate a Namecoach record to entity"),
 *   pass_context = TRUE,
 *   type = "node",
 *   confirm = TRUE,
 * )
 */

class AssociateNamecoachRecordAction extends ViewsBulkOperationsActionBase implements ViewsBulkOperationsPreconfigurationInterface {

  use StringTranslationTrait;

   /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {

    $nc_skipdate = $this->configuration['nc_skipdate'];
    $nc_existing = NULL;

    if (!isset($this->context['sandbox']['success'])) {
      $this->context['sandbox']['success'] = 0;
    }

    if ($entity instanceof Node) {
      $authorid = $entity->getOwnerId();
      // TODO: in future, config for ref field
      if ($entity->hasField('field_namecoach_mref')) {

        
        // TODO: in future, config for text or introspect to find an email field type?
        $keymail = $entity->get('field_contact_email')->getValue();
        $contact = $entity->get('field_contact_email')->getString();

        if (!$contact){
          $msg = $this->t('Profile has no key to use for a Namecoach lookup', []);
          \Drupal::logger('nc_remote')->notice($msg);
          return $msg;
        }

        // TODO: in future, introspect to find a ref field that uses nc_remote?
        $entity_mrefs = $entity->get('field_namecoach_mref')->referencedEntities();
        
        // check to see if we have an existing record
        if ($entity_mrefs and $entity_mrefs[0]) {
          if( $entity_mrefs[0] instanceof \Drupal\media\Entity\Media) {
            $nc_existing = $entity_mrefs[0];
            /** @var Drupal\media_source_namecoach\Plugin\media\Source\Namecoach $source */
            if ($nc_existing->bundle() == 'nc_remote') {
              if ($nc_skipdate == 'nc_skipdate_skip'){
                $msg = $this->t('Existing record found for :keymail, skipping...', [':keymail' => $contact]);
                \Drupal::logger('nc_remote')->notice($msg);
                return $msg;
              }
            } else {
              $msg = $this->t('Field has stored a non Namecoach object for :keymail.', [':keymail' => $contact]);
              \Drupal::logger('nc_remote')->notice($msg);
              return $msg;
            }
          } else {
            $msg = $this->t('Field has stored a non-Media for :keymail.', [':keymail' => $contact]);
            \Drupal::logger('nc_remote')->notice($msg);
            return $msg;
          }
        }

        //try to make a Namecoach entry

        // Create the new media item. ($keymail is in an array from getValue)
        /** @var Drupal\media_source_namecoach\Plugin\media\Source\Namecoach $source */
        $nc_remote = \Drupal\media\Entity\Media::create([
          'bundle' => 'nc_remote',
          'uid' => $authorid,
          'langcode' => \Drupal::languageManager()->getDefaultLanguage()->getId(),
          'field_media_namecoach_emailkey'  => $keymail,
        ]);
        $source = $nc_remote->getSource();
        $recording = $source->getMetadata($nc_remote, 'recording_link');

        if (empty($recording)){
          $msg = $this->t('Media entry invalid for :keymail.', [':keymail' => $contact]);
          \Drupal::logger('nc_remote')->notice($msg);
          return $msg;
        } else {

          try {
       
            $nc_remote->save();
    
            $entity->set('field_namecoach_mref', $nc_remote);
            $entity->save();
            $this->context['sandbox']['success']++;
            return $this->t('A Namecoach record was added to the profile for :keymail', [':keymail' => $contact]);

            }
            catch (Exception $e) {
              return $e->getMessage();
            }

        }

      }
      else {
        return $this->t('Node %type does not have a the namecoach reference field.', ['%type' => $entity->bundle()]);
      }
    }

    return 'Action can only be performed on nodes.';
  }

        /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = $object->access('update', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state) : array {

    $form['nc_skipdate'] = [
      '#type' => 'select',
      '#title' => 'Skip or update when we find a Namecoach record reference on a node?',
      '#options' => [
        'nc_skipdate_skip' => 'Skip existing records',
        'nc_skipdate_update' => 'Update/replace existing records',
        ],
      '#default_value' => isset($values['nc_skipdate']) ? $values['nc_skipdate'] : 'nc_skipdate_skip',
    ];

    return $form;
  }


}



