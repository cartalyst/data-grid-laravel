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

class DataGridFacadeTest extends PHPUnit_Framework_TestCase
{
    /**
     * Facade class.
     *
     * @var string
     */
    protected $facade = 'Cartalyst\DataGrid\Laravel\Facades\DataGrid';

    /** @test */
    public function it_is_a_facade_instance()
    {
        $illuminateFacade = 'Illuminate\Support\Facades\Facade';

        $reflection = new ReflectionClass($this->facade);

        $this->assertTrue($reflection->isSubclassOf($illuminateFacade));
    }

    /** @test */
    public function it_has_a_facade_accessor()
    {
        $reflection = new ReflectionClass($this->facade);

        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $this->assertSame('datagrid', $method->invoke(null));
    }
}
