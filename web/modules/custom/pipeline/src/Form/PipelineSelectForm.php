<?php

namespace Drupal\pipeline\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Plugin\PipelinePipelinePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pipeline selector form.
 */
class PipelineSelectForm extends FormBase {

  /**
   * The pipeline plugin manager service.
   *
   * @var \Drupal\pipeline\Plugin\PipelinePipelinePluginManager
   */
  protected $pipelinePluginManager;

  /**
   * Constructs a new form class.
   *
   * @param \Drupal\pipeline\Plugin\PipelinePipelinePluginManager $pipeline_plugin_manager
   *   The pipeline plugin manager service.
   */
  public function __construct(PipelinePipelinePluginManager $pipeline_plugin_manager) {
    $this->pipelinePluginManager = $pipeline_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.pipeline_pipeline'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pipeline_select_pipeline';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $pipelines = [];
    foreach ($this->pipelinePluginManager->getDefinitions() as $plugin_id => $definition) {
      if ($this->currentUser()->hasPermission("execute $plugin_id pipeline")) {
        $pipelines[$plugin_id] = $definition['label'];
      }
    }

    $form['pipeline'] = [
      '#type' => 'select',
      '#title' => $this->t('Data pipeline'),
      '#options' => $pipelines,
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('pipeline.execute_pipeline', ['pipeline' => $form_state->getValue('pipeline')]);
  }

}
