<?php

declare(strict_types=1);

namespace Drupal\Tests\permissions_by_term\Kernel;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * @group permissions_by_term
 */
class AccessStorageTest extends PbtKernelTestBase {

  use UserCreationTrait;

  public function testTidsByNidRetrieval() {
    $this->createRelationOneGrantedTerm();
    /**
     * @var \Drupal\permissions_by_term\Service\TermHandler $termHandler
     */
    $termHandler = \Drupal::service('permissions_by_term.term_handler');

    self::assertCount(3, $termHandler->getTidsByNid('1'));
    self::assertNull($termHandler->getTidsByNid('99'));
  }

  /**
   * Tests that deleteAllTermPermissionsByTermBundle() removes permissions
   * for all terms in one vocabulary, while keeping permissions for terms
   * in other vocabularies.
   *
   * This test:
   * - Creates two vocabularies with terms.
   * - Assigns permissions to a test user for terms in both vocabularies.
   * - Deletes permissions for all terms in the first vocabulary.
   * - Checks that only the second vocabulary term remains.
   *
   * @group permissions_by_term
   */
  public function testDeleteAllTermPermissionsByTermBundle() {
    // Create the first vocabulary and its terms.
    $firstVocab = Vocabulary::create([
      'vid' => 'first_vocab',
      'name' => 'First Vocabulary',
    ]);
    $firstVocab->save();

    $term1FirstVocab = Term::create([
      'name' => 'Term 1',
      'vid' => $firstVocab->id(),
    ]);
    $term1FirstVocab->save();

    $term2FirstVocab = Term::create([
      'name' => 'Term 2',
      'vid' => $firstVocab->id(),
    ]);
    $term2FirstVocab->save();

    // Create the second vocabulary and its term.
    $secondVocab = Vocabulary::create([
      'vid' => 'second_vocab',
      'name' => 'Second Vocabulary',
    ]);
    $secondVocab->save();

    $term1SecondVocab = Term::create([
      'name' => 'Term 1',
      'vid' => $secondVocab->id(),
    ]);
    $term1SecondVocab->save();

    // Create an admin user.
    $adminUser = $this->createUser([], NULL, TRUE);

    // Assign permissions for first vocabulary terms.
    $this->accessStorage->addTermPermissionsByUserIds([$adminUser->id()], $term1FirstVocab->id());
    $this->accessStorage->addTermPermissionsByRoleIds($adminUser->getRoles(), $term2FirstVocab->id());

    // Assign permissions for second vocabulary term.
    $this->accessStorage->addTermPermissionsByUserIds([$adminUser->id()], $term1SecondVocab->id());

    // Confirm all 3 terms are permitted before deletion.
    $permittedTerms = $this->accessStorage->getPermittedTids($adminUser->id(), $adminUser->getRoles());
    $this->assertCount(3, $permittedTerms, 'Admin user should have 3 permitted terms initially.');

    // Delete permissions for all terms in the first vocabulary.
    $this->accessStorage->deleteAllTermPermissionsByTermBundle($firstVocab->id());

    // Confirm only the second vocabulary term remains permitted.
    $permittedTermsAfterRemoval = $this->accessStorage->getPermittedTids($adminUser->id(), $adminUser->getRoles());
    $this->assertCount(1, $permittedTermsAfterRemoval, 'Only one permitted term should remain after deletion.');
    $this->assertEquals($term1SecondVocab->id(), $permittedTermsAfterRemoval[0], 'Remaining permitted term should belong to the second vocabulary.');
  }

}
