<?php

namespace Drupal\Tests\joinup_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;

/**
 * Tests Joinup migration.
 *
 * Using KernelTestBase instead of BrowserTestBase, with 'joinup' profile,
 * because this is faster.
 *
 * @group joinup
 */
class JoinupMigrateTest extends KernelTestBase implements MigrateMessageInterface {

  use RdfDatabaseConnectionTrait;

  /**
   * Migration messages collector.
   *
   * @var array[]
   */
  protected $messages = [];

  /**
   * Main database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * Legacy database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $legacyDb;

  /**
   * Migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Setup the connections to main, legacy DB and SPARQL backend.
    $this->db = Database::getConnection();
    $this->setUpLegacyDb();
    $this->setUpSparql();

    // Install modules from the 'joinup' profile.
    $this->installModules();

    $this->manager = $this->container->get('plugin.manager.migration');

    // Run test migrations.
    $this->runMigrations();
  }

  /**
   * Tests Joinup migrate suite.
   */
  public function test() {
    // Check that the migration was clean.
    if (!empty($this->messages)) {
      $messages = array_map(function ($message) {
        return "- $message";
      }, $this->messages);
      $this->fail("Error messages received during migrations:\n" . implode("\n", $messages));
    }

    // Assertions for each migrations are defined under assert/ directory.
    foreach (file_scan_directory(__DIR__ . '/assert', '|\.php$|') as $file) {
      require __DIR__ . '/assert/' . $file->filename;
    }

    // Uninstalling 'joinup_migrate' cleans-up the created tables and views.
    \Drupal::service('module_installer')->uninstall(['joinup_migrate']);
  }

  /**
   * Runs all available migrations.
   */
  protected function runMigrations() {
    $settings = Settings::getAll();
    // Ensure we're always in testing mode.
    $settings['joinup_migrate.mode'] = 'test';
    // Set the legacy site webroot.
    if (!$legacy_webroot = getenv('SIMPLETEST_LEGACY_WEBROOT')) {
      throw new \Exception('The legacy site webroot is not set. You must provide a SIMPLETEST_LEGACY_WEBROOT environment variable.');
    }
    $settings['joinup_migrate.source.root'] = $legacy_webroot;
    new Settings($settings);

    foreach ($this->manager->createInstances([]) as $id => $migration) {
      // Force running the migration, even the prior migrations were incomplete.
      $migration->set('requirements', []);
      try {
        (new MigrateExecutable($migration, $this))->import();
      }
      catch (\Exception $e) {
        $class = get_class($e);
        $this->display("$class: {$e->getMessage()} ({$e->getFile()}, {$e->getLine()})", 'error');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function display($message, $type = 'status') {
    $this->messages[] = "$type: $message";
  }

  /**
   * Install all needed modules.
   */
  protected function installModules() {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = $this->container->get('module_installer');

    // Ensure that system.module is installed first.
    drupal_get_filename('module', 'system', 'core/modules/system/system.info.yml');
    drupal_get_filename('profile', 'joinup', 'profiles/joinup/joinup.info.yml');
    drupal_install_system(['parameters' => ['profile' => 'joinup', 'langcode' => 'en']]);

    // Install the profile dependencies.
    $profile_info = Yaml::decode(file_get_contents($this->getDrupalRoot() . '/profiles/joinup/joinup.info.yml'));
    $modules = $profile_info['dependencies'];
    // Finally, add the migrate module.
    $modules[] = 'joinup_migrate';
    $module_installer->install($modules);

    // Configuration from profile, like blocks, depend on themes.
    $themes = $profile_info['themes'];
    $this->container->get('theme_handler')->install($themes);
    $this->container->get('theme.manager')->resetActiveTheme();

    // Install the profile. Should be added to the list first.
    $this->container->get('module_handler')->addProfile('joinup', 'profiles/joinup');
    $module_installer->install(['joinup'], FALSE);
  }

  /**
   * Creates a connection to legacy Drupal 6 database.
   *
   * @throws \Exception
   *   When environment variable SIMPLETEST_LEGACY_DB is not defined.
   */
  protected function setUpLegacyDb() {
    if (!$db_url = getenv('SIMPLETEST_LEGACY_DB')) {
      throw new \Exception('No migrate database connection. You must provide a SIMPLETEST_LEGACY_DB environment variable.');
    }
    $database = Database::convertDbUrlToConnectionInfo($db_url, $this->root);
    // We set the timezone to UTC to force MySQL time functions to correctly
    // convert timestamps into date/time.
    $database['init_commands'] = [
      'set_timezone_to_utc' => "SET time_zone='+00:00';",
    ];
    Database::addConnectionInfo('migrate', 'default', $database);
    $this->legacyDb = Database::getConnection('default', 'migrate');
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    Database::removeConnection('migrate');
    $this->legacyDb = NULL;
    $this->manager = NULL;
    unset($this->messages);
    parent::tearDown();
  }

  /**
   * Asserts that a message has been logged.
   *
   * @param string $migration_id
   *   The migration.
   * @param string $message
   *   The message to be checked.
   * @param string $operator
   *   (optional) The operator to be used for comparision. Defaults to '='.
   */
  protected function assertMessage($migration_id, $message, $operator = '=') {
    $table = "migrate_message_{$migration_id}";
    $found = (bool) $this->db->select($table, 'm')
      ->fields('m')
      ->condition('m.message', $message, $operator)
      ->execute()
      ->fetchAll();
    $this->assertTrue($found);
  }

  /**
   * Asserts total number of items migrated.
   *
   * @param string $migration_id
   *   The migration.
   * @param int $count
   *   The count.
   */
  protected function assertTotalCount($migration_id, $count) {
    $this->countHelper($migration_id, $count);
  }

  /**
   * Asserts that number of items successfully migrated.
   *
   * @param string $migration_id
   *   The migration.
   * @param int $count
   *   The count.
   */
  protected function assertSuccessCount($migration_id, $count) {
    $this->countHelper($migration_id, $count, MigrateIdMapInterface::STATUS_IMPORTED);
  }

  /**
   * Asserts number of migrated items with a given status.
   *
   * @param string $migration_id
   *   The migration.
   * @param int $count
   *   The count.
   * @param int $status
   *   (optional) The migration status. If missed all the items are counted.
   */
  protected function countHelper($migration_id, $count, $status = NULL) {
    $table = "migrate_map_{$migration_id}";
    $query = $this->db->select($table)->fields($table);
    if ($status !== NULL) {
      $query->condition('source_row_status', $status);
    }

    $actual_count = $query
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals($count, $actual_count);
  }

  /**
   * Returns an entity by its label.
   *
   * Being used for testing, this method assumes that, within an entity type,
   * all entities have unique labels.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $label
   *   The entity label.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   *
   * @throws \InvalidArgumentException
   *   When the entity type lacks a label key.
   * @throws \Exception
   *   When the entity with the specified label was not found.
   */
  protected function loadEntityByLabel($entity_type_id, $label) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type = $entity_type_manager->getDefinition($entity_type_id);
    if (!$entity_type->hasKey('label')) {
      throw new \InvalidArgumentException("Entity type '$entity_type_id' doesn't have a label key.");
    }

    $label_key = $entity_type->getKey('label');
    $storage = $entity_type_manager->getStorage($entity_type_id);

    if (!$entities = $storage->loadByProperties([$label_key => $label])) {
      throw new \Exception("No $entity_type_id entity with $label_key '$label' was found.");
    }

    return reset($entities);
  }

}
