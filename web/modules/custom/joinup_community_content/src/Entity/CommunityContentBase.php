<?php

declare(strict_types = 1);

namespace Drupal\joinup_community_content\Entity;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\node\Entity\Node;
use Drupal\rdf_entity\RdfInterface;

/**
 * Base class for community content entities.
 */
class CommunityContentBase extends Node implements CommunityContentInterface {

  use JoinupBundleClassFieldAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function getCollection(): CollectionInterface {
    $group = $this->getGroup();
    if (!$group instanceof CollectionInterface) {
      return $group->getCollection();
    }
    /** @var \Drupal\collection\Entity\CollectionInterface $group */
    return $group;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup(): RdfInterface {
    /** @var \Drupal\og\Plugin\Field\FieldType\OgStandardReferenceItem $audience_field */
    $audience_field = $this->getFirstItem('og_audience');
    if (!$audience_field || $audience_field->isEmpty()) {
      throw new MissingGroupException();
    }
    /** @var \Drupal\collection\Entity\CollectionInterface|\Drupal\solution\Entity\SolutionInterface $group */
    $group = $audience_field->entity;

    return $group;
  }

}