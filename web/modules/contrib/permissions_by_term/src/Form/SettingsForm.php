<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\permissions_by_term\Service\AccessStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The settings form for Permissions by Term.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Permission access storage.
   *
   * @var \Drupal\permissions_by_term\Service\AccessStorage
   */
  protected AccessStorage $accessStorage;

  /**
   * Vocabulary storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $vocabularyStorage;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'permissions_by_term_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'permissions_by_term.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->accessStorage = $container->get('permissions_by_term.access_storage');
    $instance->vocabularyStorage = $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['require_all_terms_granted'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Require all terms granted'),
      '#description'   => $this->t('By default users are granted access content, as long they have access to a <strong>single</strong>
related taxonomy term. If the <strong>require all terms granted</strong> option is checked, they must
have access to <strong>all</strong> related taxonomy terms to access an node.'),
      '#default_value' => $this->config('permissions_by_term.settings')->get('require_all_terms_granted'),
    ];

    $form['permission_mode'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Permission mode'),
      '#description'   => $this->t('This mode makes nodes accessible (view and edit) only, if editors have been explicitly granted the permission to them. Users won\'t have access to nodes matching any of the following conditions:
<br />- nodes without any terms
<br />- nodes without any terms which grant them permission'),
      '#default_value' => $this->config('permissions_by_term.settings')->get('permission_mode'),
    ];

    $form['disable_node_access_records'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Disable node access records'),
      '#description'   => $this->t('By disabling node access records, nodes won\'t be hidden in:
<br />- listings made by the Views module (e.g. search result pages)
<br />- menus
<br />- other Drupal core systems, which are based on <a href="https://www.drupal.org/docs/8/modules/permissions-by-term#s-node-access-records" target="_blank" title="Node Access records documentation">Node Access records</a><br />
This setting can be useful, if you just want to restrict nodes on node view and
node edit. Like hiding unpublished nodes from editors during a content
moderation workflow. Disabling node access records will save you some time on
node save and taxonomy save, since the node access records must not be rebuild. Also it will provide major performance
benefit on large, non-cached content listings like the "/admin/content" page. If you want to restrict nodes on your
overall website and you are using a warmed page cache, then it is recommended to leave this setting disabled.'),
      '#default_value' => $this->config('permissions_by_term.settings')->get('disable_node_access_records'),
    ];

    $form['only_parents'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show only parent terms in user form'),
      '#description'   => $this->t('If you check this, the list of terms shown in the user form is limited to only parent terms.'),
      '#default_value' => $this->config('permissions_by_term.settings')->get('only_parents') ?? FALSE,
    ];

    $form['show_terms_in_user_form'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show term selection in the user form'),
      '#description'   => $this->t('When enabled, PbT will show a multi-select field for term assignment on the user edit form.'),
      '#default_value' => $this->config('permissions_by_term.settings')->get('show_terms_in_user_form') ?? TRUE,
    ];

    $form['hide_terms_permissions_info_in_node_form'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Hide terms permissions information in the node form.'),
      '#description'   => $this->t('This is useful when you want to hide terms permissions information in the node form. Saves loading time.'),
      '#default_value' => $this->config('permissions_by_term.settings')->get('hide_terms_permissions_info_in_node_form') ?? FALSE,
    ];

    $form['target_bundles'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Limit by taxonomy vocabularies'),
      '#description'   => $this->t('Whether to limit permissions management and search by selected taxonomy vocabularies. If left empty, all taxonomy vocabularies are allowed. <br/><strong>Warning: Unchecking a vocabulary will remove existing permissions for its terms. Disabling all vocabularies will not remove permissions, since leaving the selection empty means all vocabularies are allowed.</strong>'),
      '#options'       => $this->getTaxonomyVocabularyOptions(),
      '#default_value' => $this->config('permissions_by_term.settings')->get('target_bundles') ?? [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Get list of vocabularies options.
   *
   * @return array
   *   List of options.
   */
  protected function getTaxonomyVocabularyOptions() {
    return array_map(function ($bundle_info) {
      return $bundle_info['label'];
    }, $this->entityTypeBundleInfo->getBundleInfo('taxonomy_term'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->removePermissionsForDisabledBundles($form_state);

    $this->configFactory
      ->getEditable('permissions_by_term.settings')
      ->set('require_all_terms_granted', $form_state->getValue('require_all_terms_granted'))
      ->save();

    $this->configFactory
      ->getEditable('permissions_by_term.settings')
      ->set('permission_mode', $form_state->getValue('permission_mode'))
      ->save();

    if ($form_state->getValue('disable_node_access_records') && !$this->configFactory
      ->getEditable('permissions_by_term.settings')
      ->get('disable_node_access_records')) {
      node_access_rebuild(TRUE);
    }

    if (!$form_state->getValue('disable_node_access_records') && $this->configFactory
      ->getEditable('permissions_by_term.settings')
      ->get('disable_node_access_records')) {
      node_access_rebuild(TRUE);
    }

    $this->configFactory
      ->getEditable('permissions_by_term.settings')
      ->set('disable_node_access_records', $form_state->getValue('disable_node_access_records'))
      ->save();

    $bundles = array_filter($form_state->getValue('target_bundles'));
    $this->configFactory
      ->getEditable('permissions_by_term.settings')
      ->set('target_bundles', array_values($bundles))
      ->save();

    $this->configFactory
      ->getEditable('permissions_by_term.settings')
      ->set('only_parents', $form_state->getValue('only_parents'))
      ->save();

    $this->configFactory
      ->getEditable('permissions_by_term.settings')
      ->set('show_terms_in_user_form', $form_state->getValue('show_terms_in_user_form'))
      ->save();

    $this->configFactory
      ->getEditable('permissions_by_term.settings')
      ->set('hide_terms_permissions_info_in_node_form', $form_state->getValue('hide_terms_permissions_info_in_node_form'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Removes permissions for disabled taxonomy bundles.
   *
   * Compares saved bundles with submitted ones and cleans up permissions for
   * those that were removed.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form, containing submitted configuration values.
   */
  protected function removePermissionsForDisabledBundles(FormStateInterface $form_state): void {
    $original_bundles = $this->config('permissions_by_term.settings')->get('target_bundles');
    $new_bundles = array_filter($form_state->getValue('target_bundles'));

    $all_bundles = $this->getTaxonomyVocabularyOptions();
    if (empty($original_bundles) && $all_bundles) {
      $original_bundles = array_keys($all_bundles);
    }

    // If left empty, all taxonomy vocabularies are allowed.
    if (empty($new_bundles)) {
      return;
    }

    $disabled_bundles = array_diff($original_bundles, $new_bundles);
    if (empty($disabled_bundles)) {
      return;
    }

    foreach ($disabled_bundles as $bundle) {
      $this->accessStorage->deleteAllTermPermissionsByTermBundle($bundle);
    }

    $vocabularies = $this->vocabularyStorage->loadMultiple($disabled_bundles);
    $labels = array_map(fn($vocabulary) => $vocabulary->label(), $vocabularies);

    if ($labels) {
      $this->messenger()->addWarning($this->t('Term permissions have been deleted for the following vocabularies: %label_string.', [
        '%label_string' => implode(', ', $labels),
      ]));
    }
  }

}
