<?php

namespace Drupal\media_source_namecoach\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\media_source_namecoach\Plugin\media\Source\Namecoach;
use Drupal\Core\Config\ConfigFactoryInterface;

class NamecoachConstraintValidator extends ConstraintValidator {
   
   /**
    * {@inheritdoc}
    */
    public function validate($entity, Constraint $constraint) {

        /** @var \Drupal\media\MediaInterface $media */
        $media = $entity->getEntity();
        /** @var Drupal\media_source_namecoach\Plugin\media\Source $source */
        $source = $media->getSource();
        if (!($source instanceof Namecoach)) {
            throw new \LogicException('Media source must implement Namecoach');
        }

        $emailkey = $source->getSourceFieldValue($media);
        // The URL may be NULL if the source field is empty, which is invalid input.
        if (empty($emailkey)) {
            $this->context->addViolation($constraint->noKeyMessage);
            return;
        }

        $recording = $source->getMetadata($media, 'recording_link');
        dvm($recording);
        if (empty($recording)) {
            $this->context->addViolation($constraint->noRecordingMessage, [
                '@emailkey' => $emailkey,
              ]);
            return;
        }      
   }
}

// close?  $fields[$media_field_name]->addConstraint('custom_media_constraint', ['key' => $key]);
