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

use Illuminate\Contracts\Support\Arrayable;

class ArrayableStub implements Arrayable
{
    /**
     * Holds nested data.
     *
     * @var bool
     */
    protected $nested;

    /**
     * Constructor.
     *
     * @param  bool  $nested
     * @return void
     */
    public function __construct($nested = false)
    {
        $this->nested = $nested;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray()
    {
        $data = array(
            'first_name' => 'Dan',
            'last_name'  => 'Syme',
            'gender'     => 'male',
            'sortable'   => 'foo-13',
            'age'        => 30,
        );

        if ($this->nested) {
            $data['address'] = array(
                'street' => array('name' => 'foo-street'),
                'city'   =>  $data['first_name'] . '-city',
            );
        }

        return $data;
    }
}
