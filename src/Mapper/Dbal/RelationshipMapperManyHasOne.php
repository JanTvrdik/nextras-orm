<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Object;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\IEntityPreloadContainer;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Collection\EntityContainer;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Mapper\IRelationshipMapper;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\NotSupportedException;


class RelationshipMapperManyHasOne extends Object implements IRelationshipMapper
{
	/** @var Connection */
	protected $connection;

	/** @var PropertyMetadata */
	protected $metadata;

	/** @var IRepository */
	protected $targetRepository;

	/** @var EntityContainer[] */
	protected $cacheEntityContainers;


	public function __construct(Connection $connection, IMapper $targetMapper, PropertyMetadata $metadata)
	{
		$this->connection = $connection;
		$this->targetRepository = $targetMapper->getRepository();
		$this->metadata = $metadata;
	}


	public function getIterator(IEntity $parent, ICollection $collection)
	{
		$container = $this->execute($collection, $parent);
		return [$container->getEntity($parent->getRawValue($this->metadata->name))];
	}


	public function getIteratorCount(IEntity $parent, ICollection $collection)
	{
		throw new NotSupportedException();
	}


	protected function execute(DbalCollection $collection, IEntity $parent)
	{
		$builder = $collection->getQueryBuilder();
		$preloadIterator = $parent->getPreloadContainer();
		$cacheKey = $this->calculateCacheKey($builder, $preloadIterator, $parent);

		$data = & $this->cacheEntityContainers[$cacheKey];
		if ($data) {
			return $data;
		}

		$values = $preloadIterator ? $preloadIterator->getPreloadValues($this->metadata->name) : [$parent->getRawValue($this->metadata->name)];
		$data = $this->fetch(clone $builder, stripos($cacheKey, 'JOIN') !== FALSE, $values, $preloadIterator);
		return $data;
	}


	protected function fetch(QueryBuilder $builder, $hasJoin, array $values, IEntityPreloadContainer $preloadContainer = NULL)
	{
		$values = array_values(array_unique(array_filter($values, function ($value) {
			return $value !== NULL;
		})));

		if (count($values) === 0) {
			return new EntityContainer([], $preloadContainer);
		}

		$primaryKey = $this->targetRepository->getMapper()->getStorageReflection()->getStoragePrimaryKey()[0];
		$builder->andWhere('%column IN %any', $primaryKey, $values);
		$builder->addSelect(($hasJoin ? 'DISTINCT ' : '') . '%table.*', $builder->getFromAlias());
		$result = $this->connection->queryArgs($builder->getQuerySQL(), $builder->getQueryParameters());

		$entities = [];
		while (($data = $result->fetch())) {
			$entity = $this->targetRepository->hydrateEntity($data->toArray());
			$entities[$entity->getValue('id')] = $entity;
		}

		return new EntityContainer($entities, $preloadContainer);
	}


	protected function calculateCacheKey(QueryBuilder $builder, IEntityPreloadContainer $preloadIterator = NULL, $parent)
	{
		return md5(
			$builder->getQuerySQL() .
			json_encode($builder->getQueryParameters()) .
			($preloadIterator ? $preloadIterator->getIdentification() : json_encode($parent->getRawValue($this->metadata->name)))
		);
	}
}
