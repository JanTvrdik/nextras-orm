<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipManyHasOneTest extends DatabaseTestCase
{

	public function testBasics()
	{
		/** @var Book[] $books */
		$books = $this->orm->books->findAll()->orderBy('id');
		$authors = [];

		foreach ($books as $book) {
			$authors[] = $book->author->id;
		}

		Assert::same([1, 1, 2, 2], $authors);
	}


	public function testEmptyEntityPreloadContainer()
	{
		/** @var Book[] $books */
		$books = $this->orm->books->findAll()->orderBy('id');
		$authors = [];

		foreach ($books as $book) {
			$book->setPreloadContainer(NULL);
			$authors[] = $book->author->id;
		}

		Assert::same([1, 1, 2, 2], $authors);
	}

}


$test = new RelationshipManyHasOneTest($dic);
$test->run();
