<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipManyHasOneTest extends DataTestCase
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


	public function testTranslator()
	{
		// id > 1 => to start collection with entity.translator = NULL
		$books = $this->orm->books->findBy(['id>' => 1])->orderBy('id');
		$authors = $translators = [];

		foreach ($books as $book) {
			$translators[] = $book->translator ? $book->translator->id : NULL;
		}

		Assert::same([NULL, 2, 2], $translators);
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


	public function testPersistanceHasOne()
	{
		$author = new Author();
		$author->name = 'Jon Snow';

		$book = new Book();
		$this->orm->books->attach($book);
		$book->title = 'A new book';
		$book->author = $author;
		$book->publisher = 1;

		$this->orm->books->persistAndFlush($book);

		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(3, $author->id);
	}


	public function testAutoConnection()
	{
		$author1 = $this->orm->authors->getById(1);

		$book = new Book();
		$book->title = 'A new book';
		$book->publisher = $this->orm->publishers->getById(1);
		$author1->translatedBooks->add($book);
		Assert::true($author1->translatedBooks->has($book));
		Assert::same($book->translator, $author1);


		$book = new Book();
		$book->title = 'The second new book';
		$book->publisher = $this->orm->publishers->getById(1);
		$book->translator = $author1;
		Assert::true($author1->translatedBooks->has($book));
		Assert::same($book->translator, $author1);


		$author2 = $this->orm->authors->getById(2);
		$author2->translatedBooks->add($book);
		Assert::false($author1->translatedBooks->has($book));
		Assert::true($author2->translatedBooks->has($book));
		Assert::same($book->translator, $author2);


		$book->translator = $author1;
		Assert::false($author2->translatedBooks->has($book));
		Assert::true($author1->translatedBooks->has($book));
		Assert::same($book->translator, $author1);
	}

}


$test = new RelationshipManyHasOneTest($dic);
$test->run();
