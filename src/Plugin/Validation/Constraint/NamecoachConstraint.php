<?php

namespace Drupal\media_source_namecoach\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
* Constraint for the Media fields in the Namecoach record.
*
* @Constraint(
*      id = "namecoach",
*      label = @Translation("Namecoach Constraint", context = "Validation"),
* )
*/

class NamecoachConstraint extends Constraint {


  public $noKeyMessage = 'No key provided for Namecoach lookup.';

  public $noParticipantMessage = 'No Namecoach participant record found for @emailkey.';

  public $noRecordingMessage = 'No Namecoach recording found for @emailkey.';


}

