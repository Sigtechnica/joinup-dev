<?php

declare(strict_types = 1);

namespace Drupal\joinup_federation;

use Drupal\sparql_entity_storage\Database\Driver\sparql\ConnectionInterface;
use Drupal\sparql_entity_storage\Entity\Query\Sparql\SparqlArg;
use Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandlerInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface;
use EasyRdf\Sparql\Result;

/**
 * Helper class to generate a hash containing all data of an rdf entity.
 */
class JoinupFederationHashGenerator {

  /**
   * The SPARQL database connection.
   *
   * @var \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface
   */
  protected $connection;

  /**
   * The SPARQL storage field handler service.
   *
   * @var \Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandler
   */
  protected $fieldHandler;

  /**
   * The graph handler service.
   *
   * @var \Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface
   */
  protected $graphHandler;

  /**
   * Constructs a JoinupFederationHashGenerator object.
   *
   * @param \Drupal\sparql_entity_storage\Database\Driver\sparql\ConnectionInterface $connection
   *   The SPARQL database connection.
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandlerInterface $field_handler
   *   The SPARQL storage field handler service.
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface $graph_handler
   *   The graph handler service.
   */
  public function __construct(ConnectionInterface $connection, SparqlEntityStorageFieldHandlerInterface $field_handler, SparqlEntityStorageGraphHandlerInterface $graph_handler) {
    $this->connection = $connection;
    $this->fieldHandler = $field_handler;
    $this->graphHandler = $graph_handler;
  }

  /**
   * Generates the representing hash for a given list of entity ids.
   *
   * The hash is generated by gathering all information related to the entity id
   * ordered by the field predicate and values alphabetically so that the end
   * result cannot vary between same versions.
   *
   * @param string[] $entity_ids
   *   An array of entity ids to generate the hashes for.
   *
   * @return string[]
   *   An associative array of hashed indexed by their related entity id.
   */
  public function generateDataHash(array $entity_ids): array {
    $query = $this->buildEntityQuery($entity_ids);
    $results = $this->connection->query($query);
    $entities_data = $this->parseQueryData($results);
    $return = [];
    foreach ($entities_data as $entity_id => $data) {
      $return[$entity_id] = md5(serialize($data));
    }

    return $return;
  }

  /**
   * Builds an entity query for a given list of entity ids.
   *
   * The query is based on the sparql entity storage class.
   *
   * @param string[] $entity_ids
   *   The entity id.
   *
   * @return string
   *   The SPARQL query.
   *
   * @see \Drupal\sparql_entity_storage\SparqlEntityStorage::loadFromStorage
   */
  protected function buildEntityQuery(array $entity_ids): string {
    $ids_string = SparqlArg::serializeUris($entity_ids, ' ');
    $graphs = array_unique($this->graphHandler->getEntityTypeGraphUrisFlatList('rdf_entity', ['staging']));
    $named_graph = '';
    foreach ($graphs as $graph) {
      $named_graph .= 'FROM ' . SparqlArg::uri($graph) . "\n";
    }

    $query = <<<QUERY
SELECT ?entity_id ?predicate ?field_value
$named_graph
WHERE{
  ?entity_id ?predicate ?field_value .
  VALUES ?entity_id { $ids_string } .
}
QUERY;

    return $query;
  }

  /**
   * Parses the data and returns them as an ordered associative array.
   *
   * Order applies to both the keys and the values.
   *
   * @param \EasyRdf\Sparql\Result $results
   *   The results from the SPARQL query.
   *
   * @return string[]
   *   An associative ids of values indexed by the predicate for each entity.
   */
  protected function parseQueryData(Result $results): array {
    $values_per_entity = [];
    foreach ($results as $result) {
      $entity_id = (string) $result->entity_id;
      $values_per_entity[$entity_id][(string) $result->predicate][] = (string) $result->field_value;
    }

    foreach ($values_per_entity as &$values_by_predicate) {
      foreach ($values_by_predicate as &$values) {
        ksort($values);
      }
      ksort($values_by_predicate);
    }

    return $values_per_entity;
  }

}
