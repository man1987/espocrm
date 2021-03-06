<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2021 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\ORM\Repository;

use Espo\ORM\{
    Collection,
    SthCollection,
    Entity,
    EntityManager,
    QueryParams\Select,
    QueryParams\SelectBuilder,
    QueryParams\Parts\WhereItem,
    Mapper\Mapper,
};

use RuntimeException;

/**
 * Builds select parameters for an RDB repository. Contains 'find' methods.
 */
class RDBSelectBuilder
{
    protected $entityManager;

    protected $builder;

    protected $repository = null;

    protected $entityType = null;

    protected $returnSthCollection = false;

    public function __construct(EntityManager $entityManager, string $entityType, ?Select $query = null)
    {
        $this->entityManager = $entityManager;

        $this->entityType = $entityType;

        $this->repository = $this->entityManager->getRepository($entityType);

        if ($query && $query->getFrom() !== $entityType) {
            throw new RuntimeException("SelectBuilder: Passed query doesn't match the entity type.");
        }

        $this->builder = new SelectBuilder($entityManager->getQueryComposer());

        if ($query) {
            $this->builder->clone($query);
        }

        if (!$query) {
            $this->builder->from($entityType);
        }
    }

    protected function getMapper(): Mapper
    {
        return $this->entityManager->getMapper();
    }

    /**
     * @param $params @deprecated. Omit it.
     */
    public function find(?array $params = null): Collection
    {
        $query = $this->getMergedParams($params);

        $collection = $this->getMapper()->select($query);

        return $this->handleReturnCollection($collection);
    }

    /**
     * @param $params @deprecated
     */
    public function findOne(?array $params = null): ?Entity
    {
        $builder = $this;

        if ($params !== null) { // @todo Remove.
            $query = $this->getMergedParams($params);
            $builder = $this->repository->clone($query);
        }

        $collection = $builder->sth()->limit(0, 1)->find();

        foreach ($collection as $entity) {
            return $entity;
        }

        return null;
    }

    /**
     * Get a number of records.
     *
     * @param $params @deprecated
     */
    public function count(?array $params = null): int
    {
        if ($params) { // @todo Remove.
            $query = $this->getMergedParams($params);
            return $this->getMapper()->count($query);
        }

        $query = $this->builder->build();

        return $this->getMapper()->count($query);
    }

    /**
     * Get a max value.
     *
     * @return int|float
     */
    public function max(string $attribute)
    {
        $query = $this->builder->build();

        return $this->getMapper()->max($query, $attribute);
    }

    /**
     * Get a min value.
     *
     * @return int|float
     */
    public function min(string $attribute)
    {
        $query = $this->builder->build();

        return $this->getMapper()->min($query, $attribute);
    }

    /**
     * Get a sum value.
     *
     * @return int|float
     */
    public function sum(string $attribute)
    {
        $query = $this->builder->build();

        return $this->getMapper()->sum($query, $attribute);
    }

    /**
     * Add JOIN.
     *
     * @param string $relationName
     *     A relationName or table. A relationName is in camelCase, a table is in CamelCase.
     * @param string|null $alias An alias.
     * @param WhereItem|array|null $conditions Join conditions.
     */
    public function join($relationName, ?string $alias = null, $conditions = null): self
    {
        $this->builder->join($relationName, $alias, $conditions);

        return $this;
    }

    /**
     * Add LEFT JOIN.
     *
     * @param string $relationName
     *     A relationName or table. A relationName is in camelCase, a table is in CamelCase.
     * @param string|null $alias An alias.
     * @param WhereItem|array|null $conditions Join conditions.
     */
    public function leftJoin($relationName, ?string $alias = null, $conditions = null): self
    {
        $this->builder->leftJoin($relationName, $alias, $conditions);

        return $this;
    }

    /**
     * Set DISTINCT parameter.
     */
    public function distinct(): self
    {
        $this->builder->distinct();

        return $this;
    }

    /**
     * Lock selected rows. To be used within a transaction.
     */
    public function forUpdate(): self
    {
        $this->builder->forUpdate();

        return $this;
    }

    /**
     * Set to return STH collection. Recommended for fetching large number of records.
     *
     * @todo Remove.
     */
    public function sth(): self
    {
        $this->returnSthCollection = true;

        return $this;
    }

    /**
     * Add a WHERE clause.
     *
     * Usage options:
     * * `where(WhereItem $clause)`
     * * `where(array $clause)`
     * * `where(string $key, string $value)`
     *
     * @param WhereItem|array|string $clause A key or where clause.
     * @param array|string|null $value A value. Omitted if the first argument is not string.
     */
    public function where($clause = [], $value = null): self
    {
        $this->builder->where($clause, $value);

        return $this;
    }

    /**
     * Add a HAVING clause.
     *
     * Usage options:
     * * `having(WhereItem $clause)`
     * * `having(array $clause)`
     * * `having(string $key, string $value)`
     *
     * @param WhereItem|array|string $clause A key or where clause.
     * @param array|string|null $value A value. Omitted if the first argument is not string.
     */
    public function having($clause = [], $value = null): self
    {
        $this->builder->having($clause, $value);

        return $this;
    }

    /**
     * Apply ORDER.
     *
     * Usage options:
     * * `order(Expression|string $orderBy, string|bool $direction)
     * * `order(int $positionInSelect, string|bool $direction)
     * * `order([[$expr1, $direction1], [$expr2, $direction2], ...])
     * * `order([$expr1, $expr2, ...], string|bool $direction)
     *
     * @param string|Expression|int|array $orderBy
     *     An attribute to order by or an array or order items.
     *     Passing an array will reset a previously set order.
     * @param string|bool $direction 'ASC' or 'DESC'. TRUE for DESC order.
     */
    public function order($orderBy = 'id', $direction = Select::ORDER_ASC): self
    {
        $this->builder->order($orderBy, $direction);

        return $this;
    }

    /**
     * Apply OFFSET and LIMIT.
     */
    public function limit(?int $offset = null, ?int $limit = null): self
    {
        $this->builder->limit($offset, $limit);

        return $this;
    }

    /**
     * Specify SELECT. Columns and expressions to be selected. If not called, then
     * all entity attributes will be selected. Passing an array will reset
     * previously set items. Passing a string will append an item.
     *
     * Usage options:
     * * `select([$expr1, $expr2, ...])`
     * * `select([[$expr1, $alias1], [$expr2, $alias2], ...])`
     * * `select(string|Expression $expression)`
     * * `select(string|Expression $expression, string $alias)`
     *
     * @param array|string|Expression $select An array of attributes or one attribute.
     * @param string|null $alias An alias. Actual if the first parameter is a string.
     */
    public function select($select, ?string $alias = null): self
    {
        $this->builder->select($select, $alias);

        return $this;
    }

    /**
     * Specify GROUP BY.
     * Passing an array will reset previously set items.
     * Passing a string will append an item.
     *
     * Usage options:
     * * `groupBy([$expr1, $expr2, ...])`
     * * `groupBy(string|Expression $expression)`
     *
     * @param string|Expression|array $groupBy
     */
    public function groupBy($groupBy): self
    {
        $this->builder->groupBy($groupBy);

        return $this;
    }

    protected function handleReturnCollection(SthCollection $collection): Collection
    {
        if ($this->returnSthCollection) {
            return $collection;
        }

        return $this->entityManager->getCollectionFactory()->createFromSthCollection($collection);
    }

    /**
     * For backward compatibility.
     * @todo Remove.
     */
    protected function getMergedParams(?array $params = null): Select
    {
        if (!$params || empty($params)) {
            return $this->builder->build();
        }

        $params = $params ?? [];

        $builtParams = $this->builder->build()->getRaw();

        $whereClause = $builtParams['whereClause'] ?? [];
        $havingClause = $builtParams['havingClause'] ?? [];
        $joins = $builtParams['joins'] ?? [];
        $leftJoins = $builtParams['leftJoins'] ?? [];

        if (!empty($params['whereClause'])) {
            unset($builtParams['whereClause']);
            if (count($whereClause)) {
                $params['whereClause'][] = $whereClause;
            }
        }

        if (!empty($params['havingClause'])) {
            unset($builtParams['havingClause']);
            if (count($havingClause)) {
                $params['havingClause'][] = $havingClause;
            }
        }

        if (empty($params['whereClause'])) {
            unset($params['whereClause']);
        }

        if (empty($params['havingClause'])) {
            unset($params['havingClause']);
        }

        if (!empty($params['leftJoins']) && !empty($leftJoins)) {
            foreach ($leftJoins as $j) {
                $params['leftJoins'][] = $j;
            }
        }

        if (!empty($params['joins']) && !empty($joins)) {
            foreach ($joins as $j) {
                $params['joins'][] = $j;
            }
        }

        $params = array_replace_recursive($builtParams, $params);

        return Select::fromRaw($params);
    }
}
