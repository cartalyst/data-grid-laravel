<?php

/**
 * Part of the Data Grid package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Cartalyst PSL License.
 *
 * This source file is subject to the Cartalyst PSL License that is
 * bundled with this package in the LICENSE file.
 *
 * @package    Data Grid
 * @version    4.0.0
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2015, Cartalyst LLC
 * @link       http://cartalyst.com
 */

use Cartalyst\DataGrid\DataGrid;
use Cartalyst\DataGrid\DataHandlers\HandlerInterface;

class DataHandlerStub implements HandlerInterface
{
    /**
     * {@inheritDoc}
     */
    public function __construct(DataGrid $dataGrid)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setupDataHandlerContext($paginate = true)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalCount()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getFilteredCount()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setFilteredCount($filteredCount)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getPage()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getPagesCount()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getPreviousPage()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getNextPage()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getPerPage()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getResults()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getSort()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setSort($sort)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getDirection()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setDirection($direction)
    {
    }
}
