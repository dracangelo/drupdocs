<?php

declare(strict_types=1);

namespace Drupal\Tests\permissions_by_term\Behat\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Class PermissionsByTermContext.
 *
 * @package PermissionsByTerm
 */
class PermissionsByTermDrupalSubContext extends RawDrupalContext {

  private const MAX_DURATION_SECONDS = 1200;

  private const MAX_SHORT_DURATION_SECONDS = 20;

  /**
   * Creates one or more terms on an existing vocabulary.
   *
   * Provide term data in the following format:
   *
   * | name  | parent | description | weight | taxonomy_field_image | access_user | access_role |
   * | Snook | Fish   | Marine fish | 10     | snook-123.jpg        | Bob         | editor      |
   * | ...   | ...    | ...         | ...    | ...                  | ...         | ...         |
   *
   * Only the 'name' field is required.
   *
   * @Given restricted :vocabulary terms:
   */
  public function createTerms($vocabulary, TableNode $termsTable) {
    foreach ($termsTable->getHash() as $termsHash) {
      $term = (object) $termsHash;
      $term->vocabulary_machine_name = $vocabulary;
      $this->termCreate($term);

      $accessStorage = \Drupal::service('permissions_by_term.access_storage');
      if (!empty($termsHash['access_user'])) {
        $userNames = explode(', ', $termsHash['access_user']);
        foreach ($userNames as $userName) {
          $accessStorage->addTermPermissionsByUserIds(
            [$accessStorage->getUserIdByName($userName)['uid']], $term->tid
          );
        }
      }

      if (!empty($termsHash['access_role'])) {
        $rolesIds = explode(', ', $termsHash['access_role']);
        $accessStorage->addTermPermissionsByRoleIds($rolesIds, $term->tid);
      }
    }
  }

  /**
   * @Given /^I create a taxonomy vocabulary with the name "([^"]*)" and the vid "([^"]*)"$/
   */
  public function createVocabulary($name, $vid) {
    $vocabulary = \Drupal::entityQuery('taxonomy_vocabulary')
      ->condition('vid', $vid)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($vocabulary)) {
      $vocabulary = Vocabulary::create([
        'name' => $name,
        'vid' => $vid,
      ]);
      $vocabulary->save();
    }
  }

  /**
   * @Then I open Permissions By Term advanced info
   */
  public function iOpenOpenPermissionsByTermAdvancedInfo() {
    $this->getSession()->evaluateScript("jQuery('#edit-permissions-by-term-info').attr('open', true);");
  }

  /**
   * @Given /^I create (\d+) nodes of the following type: "([^"]*)"$/
   */
  public function iCreateNodesOfType($number, $type) {
    for ($i = 0; $i <= $number; $i++) {
      $node = new \stdClass();
      $node->type = $type;
      $node->title = $this->createRandomString();
      $node->body = $this->createRandomString();
      $this->nodeCreate($node);
    }
  }

  /**
   *
   */
  private function createRandomString($length = 10) {
    return substr(str_shuffle(
      str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", $length)
    ), 0, $length);
  }

  /**
   * @Given node access records are rebuilt
   * @Given Node access records are rebuild
   */
  public function nodeAccessRecordsAreRebuilt(): void {
    node_access_rebuild();
  }

  /**
   * @Given node access records are disabled
   */
  public function nodeAccessRecordsAreDisabled(): void {
    \Drupal::configFactory()
      ->getEditable('permissions_by_term.settings')
      ->set('disable_node_access_records', TRUE)
      ->save();
  }

  /**
   * @Given node access records are enabled
   */
  public function nodeAccessRecordsAreEnabled(): void {
    \Drupal::configFactory()
      ->getEditable('permissions_by_term.settings')
      ->set('disable_node_access_records', FALSE)
      ->save();
  }

  /**
   * @Then /^I select index (\d+) in the dropdown named "([^"]*)"$/
   */
  public function selectIndexInDropdown($index, $name) {
    $this->getSession()->evaluateScript(
      'document.getElementsByName("' . $name . '")[0].selectedIndex = ' .
      $index . ';');
  }

  /**
   * @Then /^I open the node edit form by node title "([^"]*)"$/
   * @param string $title
   */
  public function openNodeEditFormByTitle($title) {
    $query = \Drupal::service('database')->select('node_field_data', 'nfd')
      ->fields('nfd', ['nid'])
      ->condition('nfd.title', $title);

    $this->visitPath('/node/' . $query->execute()->fetchField() . '/edit');
  }

  /**
   * @Then /^I open the node view by node title "([^"]*)"$/
   * @param string $title
   */
  public function openNodeViewByTitle($title) {
    $query = \Drupal::service('database')->select('node_field_data', 'nfd')
      ->fields('nfd', ['nid'])
      ->condition('nfd.title', $title);

    $this->visitPath('/node/' . $query->execute()->fetchField());
  }

  /**
   * @Then /^I scroll to the element with id "([^"]*)"$/
   * @param string $id
   */
  public function iScrollToElementWithId($id) {
    $this->getSession()->executeScript(
      "
                var element = document.getElementById('" . $id . "');
                element.scrollIntoView( true );
            "
    );
  }

  /**
   * @Then /^I check the checkbox with id "([^"]*)" by JavaScript$/
   * @param string $id
   */
  public function checkCheckboxWithJs($id) {
    $this->getSession()->executeScript(
      "
                document.getElementById('" . $id . "').checked = true;
            "
    );
  }

  /**
   * @Then /^I check the checkbox with id "([^"]*)"$/
   * @param string $id
   */
  public function checkCheckbox($id) {
    $page = $this->getSession()->getPage();
    $selectElement = $page->find('xpath', '//input[@id = "' . $id . '"]');

    $selectElement->check();
  }

  /**
   * @Then /^I uncheck the checkbox with id "([^"]*)"$/
   * @param string $id
   */
  public function uncheckCheckbox($id) {
    $page = $this->getSession()->getPage();
    $selectElement = $page->find('xpath', '//input[@id = "' . $id . '"]');

    $selectElement->uncheck();
  }

  /**
   * @Then /^I select the select option with label "([^"]*)" with id "([^"]*)"$/
   * @param string $label
   * @param string $id
   */
  public function selectOption($label, $id) {
    $page = $this->getSession()->getPage();
    $selectElement = $page->find('xpath', '//select[@id = "' . $id . '"]');
    $selectElement->selectOption($label);
  }

  /**
   * @Then /^I should see the text matching "([^"]*)" after a while$/
   */
  public function iShouldSeeTextAfterWhile($text) {
    try {
      $startTime = time();
      do {
        $content = $this->getSession()->getPage()->getText();
        if (substr_count($content, $text) > 0) {
          return TRUE;
        }
      } while (time() - $startTime < self::MAX_DURATION_SECONDS);

      // $this->getSession()->
      throw new ResponseTextException(
        sprintf('Could not find text %s after %s seconds', $text,
          self::MAX_DURATION_SECONDS),
        $this->getSession()
      );
    }
    catch (StaleElementReference $e) {
      return TRUE;
    }
  }

  /**
   * @Then /^I should not see the text matching "([^"]*)" after a while$/
   */
  public function iShouldNotSeeTextAfterWhile($text) {
    $startTime = time();
    do {
      $content = $this->getSession()->getPage()->getText();
      if (substr_count($content, $text) === 0) {
        return TRUE;
      }
    } while (time() - $startTime < self::MAX_SHORT_DURATION_SECONDS);
    throw new ResponseTextException(
      sprintf('Could find text %s after %s seconds', $text,
        self::MAX_SHORT_DURATION_SECONDS),
      $this->getSession()
    );
  }

  /**
   * @Then /^I click by the selector "([^"]*)" via JavaScript$/
   * @param string $selector
   */
  public function clickBySelector(string $selector) {
    $this->getSession()->executeScript("document.querySelector('" . $selector .
      "').click()");
  }

  /**
   * @Given /^editor role exists$/
   */
  public function createEditorRole() {
    if (($role = Role::load('editor')) === NULL) {
      $role = Role::create(['id' => 'editor', 'label' => 'Editor']);
      $role->save();
    }
    if ($role instanceof RoleInterface) {
      if (!$role->hasPermission('edit any article content')) {
        $role->grantPermission('edit any article content')->save();
      }
    }
  }

  /**
   * @Given /^permission mode is set$/
   */
  public function permissionModeIsSet(): void {
    \Drupal::configFactory()
      ->getEditable('permissions_by_term.settings')
      ->set('permission_mode', TRUE)
      ->save();
  }

  /**
   * @Given /^permission mode is not set$/
   */
  public function permissionModeIsNotSet(): void {
    \Drupal::configFactory()
      ->getEditable('permissions_by_term.settings')
      ->set('permission_mode', FALSE)
      ->save();
  }

  /**
   * @Then /^I submit the form submit button$/
   */
  public function iSubmitTheForm() {
    // Get the mink session.
    $session = $this->getSession();
    $submitButton = $session->getPage()->find('css', '*[type=submit]');

    if (NULL === $submitButton) {
      throw new \InvalidArgumentException(
        'Could not find the submit button with the type "submit"'
      );
    }

    $submitButton->click();
  }

  /**
   * @When I dump the HTML markup of the current page
   */
  public function dumpHtml() {
    print_r($this->getSession()->getPage()->getContent());
  }

  /**
   * @Then /^I create main menu item for node with title "([^"]*)"$/
   */
  public function createMainMenuItemForNode(string $nodeTitle): void {
    $query = \Drupal::service('database')->select('node_field_data', 'nfd')
      ->fields('nfd', ['nid'])
      ->condition('nfd.title', $nodeTitle);

    $menuLink = MenuLinkContent::create([
      'title' => $nodeTitle,
      'link' => [
        'uri' => 'internal:/node/' . $query->execute()
          ->fetchField(),
      ],
      'menu_name' => 'main',
      'expanded' => TRUE,
    ]);
    $menuLink->save();
  }

  /**
   * @Then /^I should see menu item text matching "([^"]*)"$/
   */
  public function seeMenuItemMatchingText(string $text): void {
    $xpath = '//*/ul/li/a[text() = "' . $text . '"]';

    // Get the mink session.
    $session = $this->getSession();
    $element = $session->getPage()->find(
      'xpath',
      $session->getSelectorsHandler()->selectorToXpath('xpath', $xpath)
    );

    if ($element === NULL) {
      throw new \InvalidArgumentException(
        sprintf('Could not evaluate XPath: "%s"', $xpath)
      );
    }
  }

  /**
   * @Then /^I should not see menu item text matching "([^"]*)"$/
   */
  public function seeNotMenuItemMatchingText(string $text): void {
    $xpath = '//*/ul/li/a[text() = "' . $text . '"]';

    // Get the mink session.
    $session = $this->getSession();
    $element = $session->getPage()->find(
      'xpath',
      $session->getSelectorsHandler()->selectorToXpath('xpath', $xpath)
    );

    if ($element !== NULL) {
      throw new \InvalidArgumentException(
        sprintf('Could not evaluate XPath: "%s"', $xpath)
      );
    }
  }

  /**
   * @Then /^I am installing the module named "([^"]*)"$/
   */
  public function iAmInstallingTheModule(string $moduleName): void {
    \Drupal::service('module_installer')->install([$moduleName]);
    // Required to import translations or other batch processes which runs after
    // a module is installed. (by default via backend which would runs a batch)
    $batch =& batch_get();
    if (empty($batch)) {
      return;
    }
    $batch['progressive'] = FALSE;
    batch_process();
  }

  /**
   * @Then /^I open taxonomy term view by term name "([^"]*)"$/
   */
  public function openTaxonomyTermPageByTermName(string $termName) {
    $query = \Drupal::service('database')->select(
      'taxonomy_term_field_data', 'ttfd'
    )
      ->fields('ttfd', ['tid'])
      ->condition('ttfd.name', $termName);

    $this->visitPath('/taxonomy/term/' . $query->execute()->fetchField());
  }

}
