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

namespace Cartalyst\DataGrid\Tests;

use stdClass;
use Mockery as m;
use PHPUnit_Framework_TestCase;
use Cartalyst\Attributes\EntityTrait;
use Cartalyst\Attributes\EntityInterface;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Cartalyst\DataGrid\DataHandlers\DatabaseHandler as Handler;

class DatabaseHandlerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Close mockery.
     *
     * @return void
     */
    public function tearDown()
    {
        m::close();
    }

    public function testInstanceOfEloquentModel()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridModel());

        $handler->getData()->shouldReceive('get');

        $handler->hydrate();
    }

    public function testInstanceOfQueryEloquentBuilder()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $handler->getData()->shouldReceive('get');

        $handler->hydrate();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInstanceOfInvalidObject()
    {
        $dataGrid = m::mock('Cartalyst\DataGrid\DataGrid');

        $dataGrid->shouldReceive('getData')->andReturn(m::mock('InvalidObject'));

        $handler = new Handler($dataGrid);
    }

    public function testPreparingSelect()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $dataGrid->getData()->shouldReceive('addSelect')->with(array(
            'foo',
            'bar.baz as qux',
        ))->once();

        $handler->prepareSelect();
    }

    public function testPreparingCount()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $dataGrid->getData()->shouldReceive('addSelect')->with(array(
            'foo',
            'bar.baz as qux',
        ))->once();

        $handler->getData()->shouldReceive('get')->once();
        $handler->getData()->shouldReceive('count')->once()->andReturn(6);

        $handler->prepareSelect();
        $handler->hydrate();
        $handler->prepareTotalCount();

        $this->assertEquals($handler->getTotalCount(), 6);
    }

    public function testGettingSimpleFilters()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[supportsRegexFilters]', array($dataGrid = $this->getMockDataGridBuilder()));
        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getFilters')->once()->andReturn(array(
                array('foo' => 'Filter 1'),
                array('qux' => 'Filter 2'),
                'Filter 3',
            )
        );

        $expectedColumn = array(
            array(
                'foo',
                'like',
                'Filter 1',
            ),
            array(
                'bar.baz',
                'like',
                'Filter 2',
            ),
        );
        $expectedGlobal = array(
            array(
                'like',
                'Filter 3',
            ),
        );

        $actual = $handler->getFilters();
        $this->assertCount(2, $actual);
        list($actualColumn, $actualGlobal) = $actual;

        $this->assertEquals($actualColumn, $expectedColumn);
        $this->assertEquals($actualGlobal, $expectedGlobal);
    }

    public function testGettingNullFilters()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[supportsRegexFilters]', array($dataGrid = $this->getMockDataGridBuilder()));
        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getFilters')->once()->andReturn(array(
                array('foo' => 'null'),
                array('qux' => 'Filter 2'),
                'Filter 3',
            )
        );

        $expectedColumn = array(
            array(
                'foo',
                'like',
                'null',
            ),
            array(
                'bar.baz',
                'like',
                'Filter 2',
            ),
        );
        $expectedGlobal = array(
            array(
                'like',
                'Filter 3',
            ),
        );

        $actual = $handler->getFilters();
        $this->assertCount(2, $actual);
        list($actualColumn, $actualGlobal) = $actual;

        $this->assertEquals($actualColumn, $expectedColumn);
        $this->assertEquals($actualGlobal, $expectedGlobal);
    }

    public function testGettingComplexFilters()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[supportsRegexFilters]', array($dataGrid = $this->getMockDataGridBuilder()));

        $handler->shouldReceive('supportsRegexFilters')->andReturn(true);

        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getFilters')->once()->andReturn(array(
            array('foo' => '/^\d{1,5}.*?$/'),
            array('qux' => '|>=5|<=8|'),
        ));

        $expected = array(
            array(
                'foo',
                'regex',
                '^\d{1,5}.*?$',
            ),
            array(
                'bar.baz',
                '>=',
                '5',
            ),
            array(
                'bar.baz',
                '<=',
                '8',
            ),
        );
        $actual = $handler->getFilters();
        $this->assertCount(2, $actual);
        list($actual, ) = $actual;

        $this->assertEquals($expected, $actual);
    }

    public function testSettingUpColumnFilters()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[supportsRegexFilters]', array($dataGrid = $this->getMockDataGridBuilder()));
        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getFilters')->once()->andReturn(array(
                array('foo' => 'Filter 1'),
                array('qux' => 'Filter 2'),
                array('baz' => 'null'),
                array('bar' => 'not_null'),
                'Filter 3',
            )
        );

        $dataGrid->getData()->shouldReceive('where')->with('foo', 'like', '%Filter 1%')->once();
        $dataGrid->getData()->shouldReceive('where')->with('bar.baz', 'like', '%Filter 2%')->once();
        $dataGrid->getData()->shouldReceive('whereNull')->with('baz')->once();
        $dataGrid->getData()->shouldReceive('whereNotNull')->with('bar')->once();
        $dataGrid->getData()->shouldReceive('whereNested')->with(m::type('Closure'))->once();

        $handler->prepareFilters();
    }

    public function testSettingUpAttributeFilters()
    {
        $dataGrid = m::mock('Cartalyst\DataGrid\DataGrid');
        $dataGrid->shouldReceive('getData')->andReturn($model = m::mock('Cartalyst\DataGrid\Tests\Foo'));
        $model->shouldReceive('availableAttributes')->once()->andReturn($collection = m::mock('Illuminate\Support\Collection'));
        $collection->shouldReceive('lists')->once()->andReturn(array());
        $model->shouldReceive('attributesToArray')->once()->andReturn(array());
        $model->shouldReceive('newQuery')->once()->andReturn($builder = m::mock('Illuminate\Database\Eloquent\Builder'));
        $dataGrid->shouldReceive('getEnvironment')->andReturn($environment = m::mock('Cartalyst\DataGrid\Environment'));
        $environment->shouldReceive('getRequestProvider')->andReturn(m::mock('Cartalyst\DataGrid\RequestProviders\ProviderInterface'));
        $dataGrid->shouldReceive('getColumns')->andReturn(array(
            'foo',
            'bar.baz' => 'qux',
        ));

        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[supportsRegexFilters]', array($dataGrid));
        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getFilters')->once()->andReturn(array(
                array('foo' => 'Filter 1'),
                array('qux' => 'Filter 2'),
                array('baz' => 'null'),
                array('bar' => 'not_null'),
                'Filter 3',
            )
        );

        $builder->shouldReceive('where')->with('foo', 'like', '%Filter 1%')->once();
        $builder->shouldReceive('where')->with('bar.baz', 'like', '%Filter 2%')->once();
        $builder->shouldReceive('whereNull')->with('baz')->once();
        $builder->shouldReceive('whereNotNull')->with('bar')->once();
        $builder->shouldReceive('whereNested')->with(m::type('Closure'))->once();

        $handler->prepareFilters();
    }

    public function testGlobalFilterOnQuery()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $query = m::mock('Illuminate\Database\Query\Builder');
        $query->shouldReceive('orWhere')->with('foo', 'like', '%Global Filter%')->once();
        $query->shouldReceive('orWhere')->with('bar.baz', 'like', '%Global Filter%')->once();

        $handler->globalFilter($query, 'like', 'Global Filter');
    }

    public function testOperatorFilters()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[supportsRegexFilters]', array($dataGrid = $this->getMockDataGridBuilder()));
        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getFilters')->once()->andReturn(array(
                array('foo' => '|>=5|<=20|<>10|!=11|'),
                array('qux' => '|>3|<5|'),
            )
        );

        $dataGrid->getData()->shouldReceive('where')->with('foo', '>=', '5')->once();
        $dataGrid->getData()->shouldReceive('where')->with('foo', '<=', '20')->once();
        $dataGrid->getData()->shouldReceive('where')->with('foo', '<>', '10')->once();
        $dataGrid->getData()->shouldReceive('where')->with('foo', '!=', '11')->once();
        $dataGrid->getData()->shouldReceive('where')->with('bar.baz', '>', '3')->once();
        $dataGrid->getData()->shouldReceive('where')->with('bar.baz', '<', '5')->once();

        $handler->prepareFilters();
    }

    public function testNestedFilters()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[supportsRegexFilters]', array($dataGrid = $this->getMockDataGridBuilder()));
        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getFilters')->once()->andReturn(array(
                array('baz..name' => 'foo'),
            )
        );

        $expected = array(
            array('foo' => 'bar', 'baz' => array('name' => 'foo')),
            array('corge' => 'fred', 'baz' => array('name' => 'bar')),
        );

        $handler->getData()->shouldReceive('whereHas')->once();
        $handler->getData()->shouldReceive('get')->andReturn($expected[0]);

        $handler->prepareFilters();
    }

    public function testRegexFilters()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());
        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getFilters')->once()->andReturn(array(
            array('foo' => '/^B.*?\sCorlett$/'),
        ));

        $dataGrid->getData()->shouldReceive('whereRaw')->with('foo regex ?', array('^B.*?\sCorlett$'))->once();

        $dataGrid->getData()->shouldReceive('getQuery')->andReturn($query = m::mock('Illuminate\Database\Query\Builder'));
        $query->shouldReceive('getConnection')->andReturn(m::mock('Illuminate\Database\MySqlConnection'));

        $handler->prepareFilters();
    }

    public function testFilteredCount()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $dataGrid->getData()->shouldReceive('count')->once()->andReturn(5);

        $handler->prepareFilteredCount();
        $this->assertEquals(5, $handler->getFilteredCount());
    }

    public function testSortingWhenNoOrdersArePresent()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());
        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getSort')->once();

        $handler->prepareSort();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSortingInvalidColumn()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());
        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getSort')->once()->andReturn('foobar');

        $handler->prepareSort();
    }

    public function testSortingWhenOrdersAreAlreadyPresent()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());
        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getSort')->once()->andReturn('qux');
        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getDirection')->once()->andReturn('desc');
        $dataGrid->getData()->shouldReceive('getQuery')->once()->andReturn($query = m::mock('Illuminate\Database\Query\Builder'));

        $query->orders = array(
            array(
                'column'    => 'corge',
                'direction' => 'asc',
            ),
        );

        $handler->prepareSort();

        // Validate the orders are correct
        $this->assertCount(2, $query->orders);
        $this->assertEquals('bar.baz', $query->orders[0]['column']);
        $this->assertEquals('desc', $query->orders[0]['direction']);
        $this->assertEquals('corge', $query->orders[1]['column']);
        $this->assertEquals('asc', $query->orders[1]['direction']);
    }

    public function testSortingByNestedResources3()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getSort')->once()->andReturn('foo');
        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getDirection')->once()->andReturn('asc');
        $dataGrid->getData()->shouldReceive('getQuery')->once()->andReturn($query = m::mock('Illuminate\Database\Query\Builder'));
        $query->shouldReceive('orderBy')->once();

        $expected = new \Illuminate\Database\Eloquent\Collection(array(
            new \Illuminate\Database\Eloquent\Collection(array('foo' => 'bar', 'baz' => array('name' => 'foo'))),
            new \Illuminate\Database\Eloquent\Collection(array('corge' => 'fred', 'baz' => array('name' => 'bar'))),
        ));

        $handler->getData()->shouldReceive('get')->andReturn($expected);

        $query->orders = 'foo';

        $handler->prepareSort();
        $handler->hydrate();

        $results = $handler->getResults();

        // Validate the orders are correct
        $this->assertEquals($expected[0]->toArray(), $results[0]);
        $this->assertEquals($expected[1]->toArray(), $results[1]);
    }

    public function testTransform()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $expected = new \Illuminate\Database\Eloquent\Collection(array(
            new \Illuminate\Database\Eloquent\Collection(array('foo' => 'bar', 'baz' => array('name' => 'foo'))),
            new \Illuminate\Database\Eloquent\Collection(array('foo' => 'fred', 'baz' => array('name' => 'bar'))),
        ));

        $validated = array(
            array('foo' => 'foobar', 'baz' => array('name' => 'foo')),
            array('foo' => 'foobar', 'baz' => array('name' => 'bar')),
        );

        $handler->getData()->shouldReceive('get')->andReturn($expected);

        $handler->setTransformer(function ($el) {
            $el->put('foo', 'foobar');

            return $el;
        });

        $handler->hydrate();

        $results = $handler->getResults();

        // Validate the orders are correct
        $this->assertEquals($validated[0], $results[0]);
        $this->assertEquals($validated[1], $results[1]);
    }

    public function testSortingHasMany()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridHasMany());
        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getSort')->once()->andReturn('qux');
        $dataGrid->getEnvironment()->getRequestProvider()->shouldReceive('getDirection')->once()->andReturn('desc');

        $handler->prepareSort();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCalculatingPaginationThrowsExceptionIfRequestedPagesIsZero()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $handler->calculatePagination(10, 'single', 0, 0);
    }

    public function testCalculatingPagination1()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $result = $handler->calculatePagination(100, 'group', 100, 10);
        $this->assertCount(2, $result);
        list($totalPages, $perPage) = $result;
        $this->assertSame(10, $totalPages);
        $this->assertSame(10, $perPage);
    }

    public function testCalculatingPagination2()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $result = $handler->calculatePagination(90, 'group', 100, 10);
        list($totalPages, $perPage) = $result;
        $this->assertSame(1, $totalPages);
        $this->assertSame(90, $perPage);
    }

    public function testCalculatingPagination3()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $result = $handler->calculatePagination(120, 'group', 100, 10);
        list($totalPages, $perPage) = $result;
        $this->assertSame(10, $totalPages);
        $this->assertSame(12, $perPage);
    }

    public function testCalculatingPagination4()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $result = $handler->calculatePagination(1200, 'single', 100, 100);
        list($totalPages, $perPage) = $result;
        $this->assertSame(12, $totalPages);
        $this->assertSame(100, $perPage);
    }

    public function testCalculatingPagination5()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $result = $handler->calculatePagination(12000, 'single', 100, 100);
        list($totalPages, $perPage) = $result;
        $this->assertSame(120, $totalPages);
        $this->assertSame(100, $perPage);
    }

    public function testCalculatingPagination6()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $result = $handler->calculatePagination(170, 'group', 100, 10);
        list($totalPages, $perPage) = $result;
        $this->assertSame(10, $totalPages);
        $this->assertSame(17, $perPage);
    }

    public function testCalculatingPagination7()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $result = $handler->calculatePagination(171, 'group', 100, 10);
        list($totalPages, $perPage) = $result;
        $this->assertSame(10, $totalPages);
        $this->assertSame(18, $perPage);
    }

    public function testSettingUpPaginationLeavesDefaultParametersIfNoFilteredResultsArePresent()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[calculatePagination]', array($dataGrid = $this->getMockDataGridBuilder()));

        $handler->preparePagination();
    }

    public function testSettingUpPaginationWithOnePage()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[calculatePagination]', array($dataGrid = $this->getMockDataGridBuilder()));
        $handler->setFilteredCount(10);

        $request = $dataGrid->getEnvironment()->getRequestProvider();
        $request->shouldReceive('getPage')->once()->andReturn(1);
        $request->shouldReceive('getMethod')->once()->andReturn('group');
        $request->shouldReceive('getThreshold')->once()->andReturn(100);
        $request->shouldReceive('getThrottle')->once()->andReturn(100);

        $handler->shouldReceive('calculatePagination')->with(10, 'group', 100, 100)->once()->andReturn(array(1, 10));

        $dataGrid->getData()->shouldReceive('forPage')->with(1, 10)->once();

        $handler->preparePagination();

        $this->assertNull($handler->getPreviousPage());
        $this->assertNull($handler->getNextPage());
        $this->assertSame(1, $handler->getPagesCount());
    }

    public function testSettingUpPaginationOnPage2Of3()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[calculatePagination]', array($dataGrid = $this->getMockDataGridBuilder()));
        $handler->setFilteredCount(30);

        $request = $dataGrid->getEnvironment()->getRequestProvider();
        $request->shouldReceive('getPage')->once()->andReturn(2);
        $request->shouldReceive('getMethod')->once()->andReturn('group');
        $request->shouldReceive('getThreshold')->once()->andReturn(100);
        $request->shouldReceive('getThrottle')->once()->andReturn(100);

        $handler->shouldReceive('calculatePagination')->with(30, 'group', 100, 100)->once()->andReturn(array(3, 10));

        $dataGrid->getData()->shouldReceive('forPage')->with(2, 10)->once();

        $handler->preparePagination();

        $this->assertSame(1, $handler->getPreviousPage());
        $this->assertSame(3, $handler->getNextPage());
        $this->assertSame(3, $handler->getPagesCount());
    }

    public function testSettingUpPaginationOnPage3Of3()
    {
        $handler = m::mock('Cartalyst\DataGrid\DataHandlers\DatabaseHandler[calculatePagination]', array($dataGrid = $this->getMockDataGridBuilder()));
        $handler->setFilteredCount(30);

        $request = $dataGrid->getEnvironment()->getRequestProvider();
        $request->shouldReceive('getPage')->once()->andReturn(3);
        $request->shouldReceive('getMethod')->once()->andReturn('group');
        $request->shouldReceive('getThreshold')->once()->andReturn(100);
        $request->shouldReceive('getThrottle')->once()->andReturn(100);

        $handler->shouldReceive('calculatePagination')->with(30, 'group', 100, 100)->once()->andReturn(array(3, 10));

        $dataGrid->getData()->shouldReceive('forPage')->with(3, 10)->once();

        $handler->preparePagination();

        $this->assertSame(2, $handler->getPreviousPage());
        $this->assertNull($handler->getNextPage());
        $this->assertSame(3, $handler->getPagesCount());
    }

    public function testHydrating()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $results = array(
            $result1 = new stdClass,
            $result2 = new stdClass,
        );

        $result1->foo   = 'bar';
        $result1->baz   = 'qux';
        $result2->corge = 'fred';

        $dataGrid->getData()->shouldReceive('get')->andReturn($results);

        $handler->hydrate();

        $expected = array(
            array('foo' => 'bar', 'baz' => 'qux'),
            array('corge' => 'fred'),
        );

        $this->assertCount(count($expected), $results = $handler->getResults());
        $this->assertEquals($expected, $results);

        foreach ($results as $index => $result) {
            $this->assertTrue(array_key_exists($index, $results));
            $this->assertEquals($expected[$index], $result);
        }
    }

    public function testHydratingMaxResults()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $results = array(
            $result1 = new stdClass,
        );

        $result1->foo = 'bar';
        $result1->baz = 'qux';

        $dataGrid->getData()->shouldReceive('get')->andReturn($results);
        $dataGrid->getData()->shouldReceive('limit')->once()->with(1);

        $handler->hydrate(1);

        $expected = array(
            array('foo' => 'bar', 'baz' => 'qux'),
        );

        $this->assertCount(count($expected), $results = $handler->getResults());
        $this->assertEquals($expected, $results);
    }

    public function testHydrating1()
    {
        $handler = new Handler($dataGrid = $this->getMockDataGridBuilder());

        $expected = new \Illuminate\Database\Eloquent\Collection(array(
            new \Illuminate\Database\Eloquent\Collection(array('foo' => 'bar', 'baz' => new \Illuminate\Database\Eloquent\Collection(array('name' => 'foo')))),
            new \Illuminate\Database\Eloquent\Collection(array('corge' => 'fred', 'baz' => new \Illuminate\Database\Eloquent\Collection(array('name' => 'bar')))),
        ));

        $handler->getData()->shouldReceive('get')->andReturn($expected);

        $handler->hydrate();

        $results = $handler->getResults();

        // Validate the orders are correct
        $this->assertEquals($expected[0]->toArray(), $results[0]);
        $this->assertEquals($expected[1]->toArray(), $results[1]);
    }

    protected function getMockDataGridBuilder()
    {
        $dataGrid = m::mock('Cartalyst\DataGrid\DataGrid');
        $dataGrid->shouldReceive('getData')->andReturn($builder = m::mock('Illuminate\Database\Eloquent\Builder'));
        $builder->shouldReceive('getModel')->once()->andReturn($model = m::mock('Illuminate\Database\Eloquent\Model'));
        $model->shouldReceive('attributesToArray')->once()->andReturn(array());
        $dataGrid->shouldReceive('getEnvironment')->andReturn($environment = m::mock('Cartalyst\DataGrid\Environment'));
        $environment->shouldReceive('getRequestProvider')->andReturn(m::mock('Cartalyst\DataGrid\RequestProviders\ProviderInterface'));
        $dataGrid->shouldReceive('getColumns')->andReturn(array(
            'foo',
            'bar.baz' => 'qux',
        ));
        return $dataGrid;
    }

    protected function getMockDataGridModel()
    {
        $dataGrid = m::mock('Cartalyst\DataGrid\DataGrid');
        $dataGrid->shouldReceive('getData')->andReturn($model = m::mock('Illuminate\Database\Eloquent\Model'));
        $model->shouldReceive('attributesToArray')->once()->andReturn(array());
        $model->shouldReceive('newQuery')->once()->andReturn(m::mock('Illuminate\Database\Query\Builder'));
        $dataGrid->shouldReceive('getEnvironment')->andReturn($environment = m::mock('Cartalyst\DataGrid\Environment'));
        $environment->shouldReceive('getRequestProvider')->andReturn(m::mock('Cartalyst\DataGrid\RequestProviders\ProviderInterface'));
        $dataGrid->shouldReceive('getColumns')->andReturn(array(
            'foo',
            'bar.baz' => 'qux',
        ));
        return $dataGrid;
    }

    protected function getMockDataGridHasMany()
    {
        $dataGrid = m::mock('Cartalyst\DataGrid\DataGrid');
        $dataGrid->shouldReceive('getData')->andReturn($data = m::mock('Illuminate\Database\Eloquent\Relations\HasMany'));
        $data->shouldReceive('getQuery')->once()->andReturn($builder = m::mock('Illuminate\Database\Query\Builder'));
        $builder->shouldReceive('orderBy')->once();
        $dataGrid->shouldReceive('getEnvironment')->andReturn($environment = m::mock('Cartalyst\DataGrid\Environment'));
        $environment->shouldReceive('getRequestProvider')->andReturn(m::mock('Cartalyst\DataGrid\RequestProviders\ProviderInterface'));
        $dataGrid->shouldReceive('getColumns')->andReturn(array(
            'foo',
            'bar.baz' => 'qux',
        ));
        return $dataGrid;
    }
}

class Foo extends Eloquent implements EntityInterface {

    use EntityTrait;

}
