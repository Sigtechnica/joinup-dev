<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\BrowserCapabilityDetectionTrait;
use Drupal\joinup\Traits\MaterialDesignTrait;
use Drupal\joinup\Traits\RdfEntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

/**
 * Behat step definitions for testing owners.
 */
class OwnerContext extends RawDrupalContext {

  use BrowserCapabilityDetectionTrait;
  use MaterialDesignTrait;
  use RdfEntityTrait;
  use SearchTrait;
  use UserTrait;
  use UtilityTrait;

  /**
   * Test owner rdf entities.
   *
   * @var \Drupal\rdf_entity\Entity\Rdf[]
   */
  protected $owners = [];

  /**
   * Creates one or many owners with data provided in a table.
   *
   * Table format:
   * @codingStandardsIgnoreStart
   * | type                        | name        | id                      | state        |
   * | Company,Industry consortium | Acme Inc.   | http://example.com/path | validated    |
   * | Local authority             | Gotham City |                         | needs_update |
   * | Private Individual(s)       | Jane Roe    |                         |              |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $owners_table
   *   The owners table.
   *
   * @throws \Exception;
   *   If a wrong type has been passed or if the author cannot be loaded.
   *
   * @Given (the following )owner(s):
   */
  public function givenOwner(TableNode $owners_table) {
    foreach ($owners_table->getHash() as $row) {


      $values = [
        'field_owner_name' => !empty($row['name']) ? $row['name'] : $this->getRandom()->name(8, TRUE),
      ];

      if (!empty($row['author'])) {
        $values['uid'] = $this->getUserByName($row['author'])->id();
      }

      $owner = $this->createRdfEntity('owner', $values);

      $this->owners[$owner->id()] = $owner;
    }
  }

  /**
   * Checks the number of available owners.
   *
   * @param int $number
   *   The expected number of owners.
   *
   * @throws \Exception
   *   Throws an exception when the expected number is not equal to the given.
   *
   * @Then I should have :number owner/owners
   */
  public function assertOwnerCount(int $number): void {
    $this->assertRdfEntityCount($number, 'owner');
  }

  /**
   * Deletes an owner entity.
   *
   * @param string $name
   *   The name of the owner to delete.
   *
   * @When I delete the :owner owner
   */
  public function deleteOwner($name) {
    $this->getRdfEntityByLabel($name, 'owner')->delete();
  }

  /**
   * Remove any created owner entities.
   *
   * @AfterScenario
   */
  public function cleanOwners() {
    if (empty($this->owners)) {
      return;
    }

    // Since we might be cleaning up many owners, temporarily disable the
    // feature to commit the index after every query.
    $this->disableCommitOnUpdate();

    // Remove any owners that were created.
    foreach ($this->owners as $owner) {
      $owner->skip_notification = TRUE;
      $owner->delete();
    }
    $this->owners = [];
    $this->enableCommitOnUpdate();
  }

  /**
   * Sets type(s) of the owner field.
   *
   * @param string $options
   *   A comma delimited list of options to be checked.
   *
   * @throws \Exception
   *   Thrown when the owner field appears empty in the page.
   *
   * @When I set the Owner type(s) to :option
   */
  public function setOwnerType($options) {
    // Explode into an array.
    $options = $this->explodeCommaSeparatedStepArgument($options);

    /** @var \Behat\Mink\Element\NodeElement $owner_fieldset */
    $owner_fieldset = $this->getSession()->getPage()->find('named', ['fieldset', 'Type']);

    /** @var \Behat\Mink\Element\NodeElement[] $labels */
    $labels = $owner_fieldset->findAll('xpath', '//label//label');

    if (empty($labels)) {
      throw new \Exception('No options found in the owner field.');
    }

    foreach ($labels as $label) {
      $option = $label->getText();
      if (in_array($option, $options)) {
        $this->checkMaterialDesignField($option, $owner_fieldset);
      }
      else {
        $this->uncheckMaterialDesignField($option, $owner_fieldset);
      }
    }
  }

  /**
   * Navigates to the canonical page display of an owner.
   *
   * @param string $owner
   *   The label of the owner.
   *
   * @When I go to (the homepage of )the :owner owner
   * @When I visit (the homepage of )the :owner owner
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function visitOwner($owner) {
    /** @var \Drupal\rdf_entity\Entity\Rdf $entity */
    $entity = $this->getRdfEntityByLabel($owner, 'owner');
    $this->visitPath($entity->toUrl()->toString());
  }

}
