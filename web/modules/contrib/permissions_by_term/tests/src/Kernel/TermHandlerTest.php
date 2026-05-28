<?php

declare(strict_types=1);

namespace Drupal\Tests\permissions_by_term\Kernel;

/**
 * @group permissions_by_term
 */
class TermHandlerTest extends PbtKernelTestBase {

  public function testGetTidsBoundForAllNids(): void {
    $this->createRelationOneGrantedTerm();
    $this->createRelationOneGrantedTerm();

    /**
     * @var \Drupal\permissions_by_term\Service\TermHandler $termHandler
     */
    $termHandler = \Drupal::service('permissions_by_term.term_handler');
    $tidsBoundToAllNids = $termHandler->getTidsBoundToAllNidsForPublishedNodes();

    $expectedNidToTidsPairs = [
      1 =>
        [
          0 => '1',
          1 => '2',
          2 => '3',
        ],
      2 =>
        [
          0 => '4',
          1 => '5',
          2 => '6',
        ],
    ];

    self::assertEquals($expectedNidToTidsPairs, $tidsBoundToAllNids);
  }

}
