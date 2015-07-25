<?php

/**
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
 * @version    1.0.0
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2015, Cartalyst LLC
 * @link       http://cartalyst.com
 */

namespace Cartalyst\DataGrid\Laravel\DataHandlers;

use RuntimeException;
use InvalidArgumentException;
use Cartalyst\Attributes\Value;
use Illuminate\Database\Eloquent\Model;
use Cartalyst\DataGrid\DataHandlers\BaseHandler;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\MySqlConnection as MySqlDatabaseConnection;

class DatabaseHandler extends BaseHandler
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
     * @inheritdoc
     */
    public function validateSource($data)
    {
        $this->eavClass = get_class($data);

        // If the data is an instance of an Eloquent model,
        // we'll grab a new query from it.
        if ($data instanceof Model) {
            $this->extractProperties($data);

            return $data->newQuery();
        } elseif ($data instanceof EloquentQueryBuilder) {
            $this->extractProperties($data->getModel());

            return $data;
        }

        $isHasMany       = $data instanceof HasMany;
        $isQueryBuilder  = $data instanceof QueryBuilder;
        $isBelongsToMany = $data instanceof BelongsToMany;

        // Since Data Grid accepts different data types,
        // we need to check which ones are valid types.
        if (! $isQueryBuilder && ! $isHasMany && ! $isBelongsToMany) {
            throw new InvalidArgumentException('Invalid data source passed to database handler. Must be an Eloquent model / query / valid relationship, or a database query.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prepareTotalCount()
    {
        $this->params->set('total', $this->prepareCount());
    }

    /**
     * Counts data records.
     * Accounts for the bug #4306 on laravel/framework
     *
     * @return int
     */
    protected function prepareCount()
    {
        if ($this->data instanceof EloquentQueryBuilder && empty($this->data->getQuery()->groups)
            || $this->data instanceof QueryBuilder && empty($this->data->groups)
        ) {
            return $this->data->count();
        }

        return count($this->data->get());
    }

    /**
     * {@inheritdoc}
     */
    public function prepareSelect()
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
    public function prepareFilters()
    {
        $me = $this;
        $applied = [];

        list($columnFilters, $globalFilters) = $this->getFilters();

        foreach ($columnFilters as $filter) {

            list($column, $operator, $value) = $filter;
            $applied[] = [
                'column' => $column,
                'operator' => $operator,
                'value' => $value,
            ];

            if (array_key_exists($column, $this->settings->get('filters'))
                && is_callable($callable = $this->settings->get('filters')[$column])
            ) {
                // Apply custom sort logic
                call_user_func($callable, $this->data, $operator, $value);
            } else {
                $this->applyFilter($this->data, $column, $operator, $value);
            }
        }

        foreach ($globalFilters as $filter) {

            list($operator, $value) = $filter;
            $applied[] = [
                'operator' => $operator,
                'value' => $value,
            ];

            if (is_callable($callable = $this->settings->get('global'))) {
                // Apply custom sort logic
                call_user_func($callable, $this->data, $operator, $value);
            } else {
                $this->data->whereNested(function ($data) use ($me, $operator, $value) {
                    $me->globalFilter($data, $operator, $value);
                });
            }
        }

        $this->params->set('filters', $applied);
    }

    /**
     * Applies a filter to the given query.
     *
     * @param  mixed  $query
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed  $value
     * @param  string  $method
     * @return void
     */
    protected function applyFilter($query, $column, $operator, $value, $method = 'and')
    {
        $method = ($method === 'and') ? 'where' : 'orWhere';

        switch ($operator) {
            case 'like':
                $value = "%{$value}%";
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
            $valueModel = new Value;

            $matches = $valueModel->newQuery()
                ->where('entity_type', $this->eavClass)
                ->{$method}('value', $operator, $value)
                ->get();

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
                $query->{$method}($column, $operator, $value);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRegexFilters()
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
     * Returns the connection associated with the handler's data set.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    protected function getConnection()
    {
        $data = $this->data;

        if ($data instanceof EloquentQueryBuilder) {
            $data = $data->getQuery();
        }

        return $data->getConnection();
    }

    /**
     * Applies a global filter across all registered columns. The
     * filter is applied in a "or where" fashion, where
     * the value can be matched across any column.
     *
     * @param  \Illuminate\Database\Query\Builder  $nestedQuery
     * @param  string  $operator
     * @param  string  $value
     * @return void
     */
    public function globalFilter(QueryBuilder $nestedQuery, $operator, $value)
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
    public function prepareFilteredCount()
    {
        if (count($this->params->get('filters'))) {
            $this->params->set('filtered', $this->prepareCount());
        } else {
            $this->params->set('filtered', $this->params->get('total'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prepareSort()
    {
        $data = $this->data;

        if ($data instanceof HasMany || $data instanceof BelongsToMany) {
            $data = $data->getQuery();
        }

        if ($data instanceof EloquentQueryBuilder) {
            $data = $data->getQuery();
        }

        $requestedSort = $this->request->getSort();
        // If request doesn't provide sort, set the defaults
        if (empty($requestedSort) && $this->settings->has('sort')) {
            $sorts = [$this->settings->get('sort')];
        } else {
            $sorts = $requestedSort ?: [];
        }

        $applied = [];
        $data->orders = [];

        foreach ($sorts as $sort) {
            $column = (array_key_exists('column', $sort) ? $sort['column'] : null);
            $direction = (array_key_exists('direction', $sort) ? $sort['direction'] : null);

            $column = $this->calculateSortColumn($column);

            if (! $column) {
                continue;
            }

            if (array_key_exists($column, $this->settings->get('sorts'))
                && is_callable($callable = $this->settings->get('sorts')[$column])
            ) {
                // Apply custom sort logic
                call_user_func($callable, $data, $direction);
            } else {
                $data->orderBy($column, $direction);
            }

            $applied[] = [
                'column' => $column,
                'direction' => $direction,
            ];
        }

        if (! empty($requestedSort)) {
            $this->params->set('sort', $applied);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function calculateSortColumn($column = null)
    {
        if (! $column) {
            return null;
        }

        $index = array_search($column, $this->settings->get('columns'));

        $key = $index !== false ? $index : false;

        // If the sort column doesn't exist, something has gone wrong
        if ($key === false) {
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
    public function preparePagination($paginate = true)
    {
        $filteredCount = $this->params->get('filtered');

        // If our filtered results are zero, let's not set any pagination
        if ($filteredCount == 0) {
            return null;
        }

        if (! $paginate) {
            return $filteredCount;
        }

        $page = $this->request->getPage();
        $method = $this->request->getMethod();
        $threshold = $this->request->getThreshold();
        $throttle = $this->request->getThrottle();

        list($pagesCount, $perPage) = $this->calculatePagination($filteredCount, $method, $threshold, $throttle);

        list($page, $previousPage, $nextPage) = $this->calculatePages($filteredCount, $page, $perPage);

        $this->data->forPage($page, $perPage);

        $this->params->add([
            'page' => $page,
            'pages' => $pagesCount,
            'previous_page' => $previousPage,
            'next_page' => $nextPage,
            'per_page' => $perPage,
        ]);
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



    protected function extractProperties($data)
    {
        $this->appends = array_keys($data->attributesToArray());

        if (method_exists($data, 'availableAttributes')) {
            $attributes = $data->availableAttributes()->lists($this->attributesKey);

            $arrayable = method_exists($attributes, 'toArray');

            $this->attributes = $arrayable === true ? $attributes->toArray() : $attributes;
        }
    }
}
