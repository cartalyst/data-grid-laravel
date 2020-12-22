<?php

/*
 * Part of the Data Grid Laravel package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Cartalyst PSL License.
 *
 * This source file is subject to the Cartalyst PSL License that is
 * bundled with this package in the LICENSE file.
 *
 * @package    Data Grid Laravel
 * @version    4.1.0
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2020, Cartalyst LLC
 * @link       https://cartalyst.com
 */

namespace Cartalyst\DataGrid\Laravel\DataHandlers;

use RuntimeException;
use InvalidArgumentException;
use Cartalyst\Attributes\Value;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Cartalyst\DataGrid\DataHandlers\AbstractHandler;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\MySqlConnection as MySqlDatabaseConnection;
use Illuminate\Database\PostgresConnection as PostgresDatabaseConnection;

class DatabaseHandler extends AbstractHandler
{
    /**
     * Appended attributes.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * Attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Attributes primary key.
     *
     * @var string
     */
    protected $attributesKey = 'slug';

    /**
     * Eav class.
     *
     * @var string
     */
    protected $eavClass;

    /**
     * {@inheritdoc}
     */
    public function validateSource($data)
    {
        $this->eavClass = get_class($data);

        $isHasMany              = $this->isHasMany($data);
        $isQueryBuilder         = $this->isQueryBuilder($data);
        $isBelongsToMany        = $this->isBelongsToMany($data);
        $isEloquentModel        = $this->isEloquentModel($data);
        $isEloquentQueryBuilder = $this->isEloquentQueryBuilder($data);

        // Since Data Grid accepts different data types,
        // we need to check which ones are valid types.
        if (! $isEloquentModel && ! $isQueryBuilder && ! $isEloquentQueryBuilder && ! $isHasMany && ! $isBelongsToMany) {
            throw new InvalidArgumentException('Invalid data source passed to the database handler. Must be an Eloquent model / query / valid relationship, or a database query.');
        }

        if ($this->isEloquentModel($data)) {
            $this->extractProperties($data);

            return $data->newQuery();
        }

        if ($this->isEloquentQueryBuilder($data)) {
            $this->extractProperties($data->getModel());
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareTotalCount(): void
    {
        $this->parameters->set('total', $this->prepareCount());
    }

    /**
     * {@inheritdoc}
     */
    public function prepareSelect(): void
    {
        // Fallback array to select
        $toSelect = [];

        // Loop through columns and inspect whether they are an alias or not.
        //
        // If the key is not numeric, it is the real column name and the
        // value is the alias. Otherwise, there is no alias and we're
        // dealing directly with the column name. Aliases are used
        // quite often for joined tables.
        foreach ($this->settings->get('columns') as $key => $value) {
            if (! in_array($value, $this->appends) && array_search($value, $this->attributes) === false) {
                $toSelect[] = is_numeric($key) ? $value : "{$key} as {$value}";
            }
        }

        $this->data->addSelect($toSelect);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareFilters(): void
    {
        $applied = [];

        list($columnFilters, $globalFilters) = $this->getFilters();

        $data = $this->data;

        $filters = $this->settings->get('filters');

        foreach ($columnFilters as $filter) {
            list($column, $operator, $value) = $filter;

            $applied[] = compact('column', 'operator', 'value');

            if (array_key_exists($column, $filters) && is_callable($callable = $filters[$column])) {
                // Apply custom sort logic
                call_user_func($callable, $data, $operator, $value);
            } else {
                $operand = 'and';

                if (strpos($value, ', ') !== false) {
                    $value = explode(', ', $value);

                    $operand = 'or';
                }

                if (! is_array($value)) {
                    $value = [$value];
                }

                $this->data->whereNested(function () use ($column, $operator, $value, $operand) {
                    foreach ($value as $val) {
                        $this->applyFilter($this->data, $column, $operator, $val, $operand);
                    }
                });
            }
        }

        $global = $this->settings->get('global');

        foreach ($globalFilters as $filter) {
            list($operator, $value) = $filter;

            $applied[] = compact('operator', 'value');

            if (is_callable($callable = $global)) {
                // Apply custom sort logic
                call_user_func($callable, $data, $operator, $value);
            } else {
                $this->data->whereNested(function ($data) use ($operator, $value) {
                    $this->globalFilter($data, $operator, $value);
                });
            }
        }

        $this->parameters->set('filters', $applied);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRegexFilters(): bool
    {
        $regex = false;

        switch ($connection = $this->getConnection()) {
            case $connection instanceof MySqlDatabaseConnection:
                $regex = true;

                break;
        }

        return $regex;
    }

    /**
     * Applies a global filter across all registered columns. The
     * filter is applied in a "or where" fashion, where
     * the value can be matched across any column.
     *
     * @param \Illuminate\Database\Query\Builder $nestedQuery
     * @param string                             $operator
     * @param string                             $value
     *
     * @return void
     */
    public function globalFilter(QueryBuilder $nestedQuery, string $operator, string $value): void
    {
        foreach ($this->settings->get('columns') as $key => $_value) {
            if (is_numeric($key)) {
                $key = $_value;
            }

            $this->applyFilter($nestedQuery, $key, $operator, $value, 'or');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prepareFilteredCount(): void
    {
        $total = $this->parameters->get('total');

        $filters = $this->parameters->get('filters', []);

        $filtered = count($filters) ? $this->prepareCount() : $total;

        $this->parameters->set('filtered', $filtered);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareSort(): void
    {
        $data = $this->data;

        if ($this->isHasMany($data) || $this->isBelongsToMany($data) || $this->isEloquentQueryBuilder($data)) {
            $data = $data->getQuery();
        }

        $requestedSort = $this->requestProvider->getSort();

        // If request doesn't provide sort, set the defaults
        if (empty($requestedSort) && $this->settings->has('sorts')) {
            // Account for multiple sorts
            $sorts = $this->settings->get('sorts');

            $sorts = array_key_exists('column', $sorts) ? [$sorts] : $sorts;
        } else {
            $sorts = $requestedSort ?: [];
        }

        $applied = [];

        $_sorts = $this->settings->get('sorts');

        foreach ($sorts as $sort) {
            $column = array_key_exists('column', $sort) ? $sort['column'] : null;

            $direction = array_key_exists('direction', $sort) ? $sort['direction'] : null;

            $realColumn = $this->calculateSortColumn($column);

            if (! $realColumn) {
                continue;
            }

            if (array_key_exists($realColumn, $sorts) && is_callable($callable = $_sorts[$realColumn])) {
                // Apply custom sort logic
                call_user_func($callable, $data, $direction);
            } else {
                $data->orderBy($realColumn, $direction);
            }

            $applied[] = compact('column', 'direction');
        }

        if (! empty($applied)) {
            $this->parameters->set('sort', $applied);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function calculateSortColumn(string $column = null): ?string
    {
        if (! $column) {
            return null;
        }

        $index = array_search($column, $this->settings->get('columns'));

        $key = $index !== false ? $index : false;

        // If the sort column doesn't exist, something has gone wrong
        if ($key === false) {
            // Attempt to find the column in the query builder
            $data = $this->data;

            if (is_object($data) && method_exists($data, 'getQuery')) {
                $data = $data->getQuery();
            }

            foreach ((array) $data->columns as $_column) {
                if (strpos((string) $_column, $column) !== false) {
                    return $column;
                }
            }

            throw new RuntimeException("Sort column [{$column}] does not exist in data.");
        }

        // If our column is an alias, we'll use the actual
        // value instead of the alias for sorting.
        if (! is_numeric($key) && ! is_bool($key)) {
            $column = $key;
        }

        return $column;
    }

    /**
     * {@inheritdoc}
     */
    public function preparePagination(bool $paginate = true): void
    {
        $filteredCount = $this->parameters->get('filtered');

        // If our filtered results are zero, let's not set any pagination
        if ($filteredCount === 0) {
            return;
        }

        if (! $paginate) {
            return;
        }

        $page      = $this->requestProvider->getPage() ?: $this->parameters->get('page');
        $method    = $this->requestProvider->getMethod() ?: $this->parameters->get('method');
        $throttle  = $this->requestProvider->getThrottle() ?: $this->parameters->get('throttle');
        $threshold = $this->requestProvider->getThreshold() ?: $this->parameters->get('threshold');

        // Fallback to defaults
        $method    = $method ?: $this->requestProvider->getDefaultMethod();
        $throttle  = $throttle ?: $this->requestProvider->getDefaultThrottle();
        $threshold = $threshold ?: $this->requestProvider->getDefaultThreshold();

        list($pages, $perPage) = $this->calculatePagination($filteredCount, $method, $threshold, $throttle);

        $page = $page > $pages ? $pages : $page;

        list($page, $previousPage, $nextPage) = $this->calculatePages($filteredCount, $page, $perPage);

        $this->data->forPage($page, $perPage);

        $this->parameters->add(compact('page', 'pages', 'perPage', 'previousPage', 'nextPage'));
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($maxResults = null)
    {
        if ($maxResults) {
            $this->data->limit($maxResults);
        }

        $this->results = $this->hydrateResults($this->data->get());
    }

    /**
     * Counts data records.
     * Accounts for the bug #4306 on laravel/framework.
     *
     * @return int
     */
    protected function prepareCount(): int
    {
        $data = $this->data;

        if ($this->isEloquentQueryBuilder($data) && empty($data->getQuery()->groups) || $this->isQueryBuilder($data) && empty($data->groups)) {
            return $data->count();
        }

        return count($data->get());
    }

    /**
     * Applies a filter to the given query.
     *
     * @param mixed  $query
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @param string $method
     *
     * @return void
     */
    protected function applyFilter($query, $column, $operator, $value, $method = 'and'): void
    {
        $method = $method === 'and' ? 'where' : 'orWhere';

        switch ($operator) {
            case 'like':
                if (strpos($value, '%') === false) {
                    $value = "%{$value}%";
                }

                break;
            case 'regex':
                if ($this->supportsRegexFilters()) {
                    $method .= 'Raw';
                }

                if ($this->getConnection() instanceof MySqlDatabaseConnection) {
                    $query->{$method}("{$column} {$operator} ?", [$value]);
                }

                return;
        }

        if (strpos($column, '..') !== false) {
            $cols = explode('..', $column);

            $query->whereHas(reset($cols), function ($q) use ($cols, $operator, $value) {
                $q->where(end($cols), $operator, $value);
            });
        } elseif (array_search($column, $this->attributes) !== false) {
            $valueModel = new Value();

            $matches = $valueModel->newQuery()
                ->where('entity_type', $this->eavClass)
                ->{$method}('value', $operator, $value)
                ->get()
            ;

            $key = $query->getModel()->getKeyName();

            if (! $matches->toArray()) {
                $query->where($key, null);
            }

            foreach ($matches as $match) {
                $query->{$method}($key, $operator, $match->entity_id);
            }
        } else {
            if ($value === '%null%') {
                $query->whereNull($column);
            } elseif ($value === '%not_null%') {
                $query->whereNotNull($column);
            } else {
                if ($this->getConnection() instanceof PostgresDatabaseConnection) {
                    $booleanMappings = [
                        '%0%' => '%f%',
                        '%1%' => '%t%',
                    ];

                    $query->{$method}(function ($q) use ($column, $operator, $booleanMappings, $value) {
                        $format = 'cast("%s" as text) %s \'%s\'';

                        $q->whereRaw(sprintf($format, $column, $operator, $value));

                        if (isset($booleanMappings[$value])) {
                            $value = $booleanMappings[$value];

                            $q->orWhereRaw(sprintf($format, $column, $operator, $value));
                        }
                    });
                } else {
                    $query->{$method}($column, $operator, $value);
                }
            }
        }
    }

    /**
     * Returns the connection associated with the handler's data set.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    protected function getConnection(): ConnectionInterface
    {
        $data = $this->data;

        if ($this->isEloquentQueryBuilder($data)) {
            $data = $data->getQuery();
        }

        return $data->getConnection();
    }

    /**
     * Extracts properties.
     *
     * @param mixed $data
     *
     * @return void
     */
    protected function extractProperties($data): void
    {
        $this->appends = array_keys($data->attributesToArray());

        if (is_object($data) && method_exists($data, 'availableAttributes')) {
            $attributes = $data->availableAttributes()->pluck($this->attributesKey);

            $arrayable = is_object($attributes) && method_exists($attributes, 'toArray');

            $this->attributes = $arrayable === true ? $attributes->toArray() : $attributes;
        }
    }

    /**
     * Determines if the given object is an instance of the eloquent model.
     *
     * @param mixed $object
     *
     * @return bool
     */
    protected function isEloquentModel($object): bool
    {
        return $object instanceof EloquentModel;
    }

    /**
     * Determines if the given object is an instance
     * of the eloquent has many relationship.
     *
     * @param mixed $object
     *
     * @return bool
     */
    protected function isHasMany($object): bool
    {
        return $object instanceof HasMany;
    }

    /**
     * Determines if the given object is an instance of the query builder.
     *
     * @param mixed $object
     *
     * @return bool
     */
    protected function isQueryBuilder($object): bool
    {
        return $object instanceof QueryBuilder;
    }

    /**
     * Determines if the given object is an instance of
     * the eloquent belongs to many relationship.
     *
     * @param mixed $object
     *
     * @return bool
     */
    protected function isBelongsToMany($object): bool
    {
        return $object instanceof BelongsToMany;
    }

    /**
     * Determines if the given object is an instance
     * of the eloquent query builder.
     *
     * @param mixed $object
     *
     * @return bool
     */
    protected function isEloquentQueryBuilder($object): bool
    {
        return $object instanceof EloquentQueryBuilder;
    }
}
