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

namespace Cartalyst\DataGrid\Laravel;

use Cartalyst\DataGrid\Contracts\Provider;
use Cartalyst\DataGrid\DataGrid;
use Cartalyst\DataGrid\RequestProvider;
use Symfony\Component\HttpFoundation\Request;

class Environment
{
    /**
     * The request provider instance.
     *
     * @var \Cartalyst\DataGrid\Contracts\Provider
     */
    protected $requestProvider;

    /**
     * Constructor.
     *
     * @param  \Cartalyst\DataGrid\Contracts\Provider  $requestProvider
     * @return void
     */
    public function __construct(Provider $requestProvider = null)
    {
        $this->requestProvider = $requestProvider ?: new RequestProvider(Request::createFromGlobals());
    }

    /**
     * Create a new data grid instance.
     *
     * @param  \Cartalyst\DataGrid\Contracts\Handler $dataHandler
     * @param \Cartalyst\DataGrid\Contracts\Provider $requestProvider
     * @return \Cartalyst\DataGrid\DataGrid|mixed
     */
    public function make($dataHandler, Provider $requestProvider = null)
    {
        return DataGrid::make($dataHandler, $requestProvider ?: $this->requestProvider);
    }
}