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
 * @version    1.0.1
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2017, Cartalyst LLC
 * @link       http://cartalyst.com
 */

namespace Cartalyst\DataGrid\Laravel\Tests\Handlers\DatabaseHandler;

use stdClass;
use Mockery as m;
use PHPUnit_Framework_TestCase;
use Illuminate\Database\Eloquent\Collection;
use Cartalyst\DataGrid\Laravel\Tests\Stubs\Bar;
use Cartalyst\DataGrid\Laravel\Tests\Stubs\Foo;
use Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler as Handler;

class GeneralTest extends PHPUnit_Framework_TestCase
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

    /** @test */
    public function it_can_create_an_instance_with_an_eloquent_model()
    {
        $handler = new Handler($this->getMockModel(), $this->getSettings());

        $handler->getData()->shouldReceive('get');

        $handler->hydrate();
    }

    /** @test */
    public function it_can_create_an_instance_with_an_eloquent_builder()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $handler->getData()->shouldReceive('get');

        $handler->hydrate();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function it_will_fail_when_creating_an_instance_with_an_invalid_object()
    {
        $data = m::mock('InvalidObject');

        new Handler($data, $this->getSettings());
    }

    public function testPreparingSelect()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $handler->getData()->shouldReceive('addSelect')
            ->with([
                'foo',
                'bar.baz as qux',
            ])
            ->once();

        $handler->prepareSelect();
    }

    public function testPreparingCount()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $query = $handler->getData();
        $query->shouldReceive('addSelect')->with([
            'foo',
            'bar.baz as qux',
        ])->once();

        $query->shouldReceive('get')->once();
        $query->shouldReceive('count')->once()->andReturn(6);

        $handler->prepareSelect();
        $handler->hydrate();
        $handler->prepareTotalCount();

        $this->assertEquals($handler->getParameters()->get('total'), 6);
    }

    public function testGettingSimpleFilters()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[supportsRegexFilters]',
            [$data, $this->getSettings()]);

        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getFilters')->once()->andReturn([
                    ['foo' => 'Filter 1'],
                    ['qux' => 'Filter 2'],
                    'Filter 3',
                ]
            );

        $handler->setRequestProvider($provider);

        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $expectedColumn = [
            [
                'foo',
                'like',
                'Filter 1',
            ],
            [
                'bar.baz',
                'like',
                'Filter 2',
            ],
        ];
        $expectedGlobal = [
            [
                'like',
                'Filter 3',
            ],
        ];

        $actual = $handler->getFilters();
        $this->assertCount(2, $actual);
        list($actualColumn, $actualGlobal) = $actual;

        $this->assertEquals($actualColumn, $expectedColumn);
        $this->assertEquals($actualGlobal, $expectedGlobal);
    }

    public function testGettingNullFilters()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[supportsRegexFilters]',
            [$data, $this->getSettings()]);

        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getFilters')->once()->andReturn([
                    ['foo' => 'null'],
                    ['qux' => 'Filter 2'],
                    'Filter 3',
                ]
            );

        $handler->setRequestProvider($provider);

        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $expectedColumn = [
            [
                'foo',
                'like',
                'null',
            ],
            [
                'bar.baz',
                'like',
                'Filter 2',
            ],
        ];
        $expectedGlobal = [
            [
                'like',
                'Filter 3',
            ],
        ];

        $actual = $handler->getFilters();
        $this->assertCount(2, $actual);
        list($actualColumn, $actualGlobal) = $actual;

        $this->assertEquals($actualColumn, $expectedColumn);
        $this->assertEquals($actualGlobal, $expectedGlobal);
    }

    public function testGettingComplexFilters()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[supportsRegexFilters]',
            [$data, $this->getSettings()]);

        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getFilters')->once()->andReturn([
                ['foo' => '/^\d{1,5}.*?$/'],
                ['qux' => '|>=5|'],
                ['qux' => '|<=8|'],
            ]);

        $handler->setRequestProvider($provider);

        $handler->shouldReceive('supportsRegexFilters')->andReturn(true);

        $expected = [
            [
                'foo',
                'regex',
                '^\d{1,5}.*?$',
            ],
            [
                'bar.baz',
                '>=',
                '5',
            ],
            [
                'bar.baz',
                '<=',
                '8',
            ],
        ];
        $actual = $handler->getFilters();
        $this->assertCount(2, $actual);
        list($actual) = $actual;

        $this->assertEquals($expected, $actual);
    }

    public function testSettingUpColumnFilters()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[supportsRegexFilters]',
            [$data, $this->getSettings()]);

        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getFilters')->once()->andReturn([
                    ['foo' => 'Filter 1'],
                    ['qux' => 'Filter 2'],
                    ['baz' => 'null'],
                    ['bar' => 'not_null'],
                    'Filter 3',
                ]
            );

        $handler->setRequestProvider($provider);

        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $query = $handler->getData();

        $query->shouldReceive('where')->with('foo', 'like', '%Filter 1%')->once();
        $query->shouldReceive('where')->with('bar.baz', 'like', '%Filter 2%')->once();
        $query->shouldReceive('whereNull')->with('baz')->once();
        $query->shouldReceive('whereNotNull')->with('bar')->once();
        $query->getQuery()->shouldReceive('orWhere')->with('foo', 'like', '%Filter 3%')->once();
        $query->getQuery()->shouldReceive('orWhere')->with('bar.baz', 'like', '%Filter 3%')->once();

        $query->shouldReceive('whereNested')->with(m::on(function ($f) use ($query) {
            $f($query->getQuery());

            return true;
        }))->times(5);

        $query->getQuery()->shouldReceive('getConnection')->andReturn(m::mock('Illuminate\Database\MySqlConnection'));

        $handler->prepareFilters();
    }

    public function testSettingUpAttributeFilters()
    {
        $model    = m::mock(Foo::class);
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');

        $collection = m::mock(Collection::class);
        $collection->shouldReceive('pluck')->once()->andReturn([]);

        $model->shouldReceive('availableAttributes')
            ->once()
            ->andReturn($collection);

        $model->shouldReceive('attributesToArray')
            ->once()
            ->andReturn([]);

        $model->shouldReceive('newQuery')
            ->once()
            ->andReturn($query = m::mock('Illuminate\Database\Query\Builder'));

        $handler = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[supportsRegexFilters]',
            [$model, $this->getSettings()]);

        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getFilters')->once()->andReturn([
                    ['foo' => 'Filter 1'],
                    ['qux' => 'Filter 2'],
                    ['baz' => 'null'],
                    ['bar' => 'not_null'],
                    'Filter 3',
                ]
            );

        $handler->setRequestProvider($provider);

        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $query->shouldReceive('where')->with('foo', 'like', '%Filter 1%')->once();
        $query->shouldReceive('where')->with('bar.baz', 'like', '%Filter 2%')->once();
        $query->shouldReceive('whereNull')->with('baz')->once();
        $query->shouldReceive('whereNotNull')->with('bar')->once();
        $query->shouldReceive('orWhere')->with('foo', 'like', '%Filter 3%')->once();
        $query->shouldReceive('orWhere')->with('bar.baz', 'like', '%Filter 3%')->once();

        $query->shouldReceive('whereNested')->with(m::on(function ($f) use ($query) {
            $f($query);

            return true;
        }))->times(5);

        $query->shouldReceive('getConnection')->andReturn(m::mock('Illuminate\Database\MySqlConnection'));

        $handler->prepareFilters();
    }

    public function testGlobalFilterOnQuery()
    {
        $data    = $this->getMockEloquentBuilder();
        $handler = new Handler($data, $this->getSettings());

        $query = m::mock('Illuminate\Database\Query\Builder');
        $query->shouldReceive('orWhere')->with('foo', 'like', '%Global Filter%')->once();
        $query->shouldReceive('orWhere')->with('bar.baz', 'like', '%Global Filter%')->once();
        $data->getQuery()->shouldReceive('getConnection')->andReturn(m::mock('Illuminate\Database\MySqlConnection'));

        $handler->globalFilter($query, 'like', 'Global Filter');
    }

    public function testOperatorFilters()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[supportsRegexFilters]',
            [$data, $this->getSettings()]);
        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getFilters')->once()->andReturn([
                    ['foo' => '|>=5|'],
                    ['foo' => '|<=20|'],
                    ['foo' => '|<>10|'],
                    ['foo' => '|!=11|'],
                    ['qux' => '|>3|'],
                    ['qux' => '|<5|'],
                ]
            );

        $handler->setRequestProvider($provider);

        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $query = $handler->getData();

        $query->shouldReceive('where')->with('foo', '>=', '5')->once();
        $query->shouldReceive('where')->with('foo', '<=', '20')->once();
        $query->shouldReceive('where')->with('foo', '<>', '10')->once();
        $query->shouldReceive('where')->with('foo', '!=', '11')->once();
        $query->shouldReceive('where')->with('bar.baz', '>', '3')->once();
        $query->shouldReceive('where')->with('bar.baz', '<', '5')->once();

        $query->shouldReceive('whereNested')->with(m::on(function ($f) use ($query) {
            $f($query->getQuery());

            return true;
        }))->times(6);

        $query->getQuery()->shouldReceive('getConnection')->andReturn(m::mock('Illuminate\Database\MySqlConnection'));

        $handler->prepareFilters();
    }

    public function testNestedFilters()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[supportsRegexFilters]',
            [$data, $this->getSettings()]);
        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getFilters')->once()->andReturn([
                    ['baz..name' => 'foo'],
                ]
            );

        $handler->setRequestProvider($provider);

        $handler->shouldReceive('supportsRegexFilters')->andReturn(false);

        $expected = [
            ['foo' => 'bar', 'baz' => ['name' => 'foo']],
            ['corge' => 'fred', 'baz' => ['name' => 'bar']],
        ];

        $query = $handler->getData();

        $query->shouldReceive('whereHas')->once();
        $query->shouldReceive('whereNested')->with(m::on(function ($f) use ($query) {
            $f($query->getQuery());

            return true;
        }))->once();

        $handler->prepareFilters();
    }

    public function testRegexFilters()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = new Handler($data, $this->getSettings());
        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getFilters')->once()->andReturn([
                    ['foo' => '/^B.*?\sCorlett$/'],
                ]
            );

        $handler->setRequestProvider($provider);

        $query = $handler->getData();

        $query->getQuery()->shouldReceive('getConnection')->andReturn(m::mock('Illuminate\Database\MySqlConnection'));
        $query->shouldReceive('whereRaw')->with('foo regex ?', ['^B.*?\sCorlett$'])->once();
        $query->shouldReceive('whereNested')->with(m::on(function ($f) use ($query) {
            $f($query->getQuery());

            return true;
        }))->once();

        $handler->prepareFilters();
    }

    public function testFilteredCount()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = new Handler($data, $this->getSettings());
        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle');

        $handler->setRequestProvider($provider);

        $handler->getData()->shouldReceive('count')->once()->andReturn(5);

        $handler->prepareTotalCount();
        $handler->prepareFilteredCount();
        $this->assertEquals(5, $handler->getParameters()->get('filtered'));
    }

    public function testSortingWhenNoOrdersArePresent()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = new Handler($data, $this->getSettings());
        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getSort')->once();

        $handler->setRequestProvider($provider);

        $handler->prepareSort();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSortingInvalidColumn()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = new Handler($data, $this->getSettings());
        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getSort')->once()->andReturn([['column' => 'foobar']]);

        $handler->setRequestProvider($provider);

        $handler->prepareSort();
    }

    public function testSortingByNestedResources3()
    {
        $data     = $this->getMockEloquentBuilder();
        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = new Handler($data, $this->getSettings());
        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getSort')->once()->andReturn([['column' => 'foo', 'direction' => 'asc']]);

        $handler->setRequestProvider($provider);

        $query = $handler->getData();

        $expected = new Collection([
            new Collection(['foo' => 'bar', 'baz' => ['name' => 'foo']]),
            new Collection(['corge' => 'fred', 'baz' => ['name' => 'bar']]),
        ]);

        $query->getQuery()->shouldReceive('orderBy')->once();
        $query->shouldReceive('get')->andReturn($expected);

        $query->orders = 'foo';

        $handler->prepareSort();
        $handler->hydrate();

        $results = $handler->getResults();

        // Validate the orders are correct
        $this->assertEquals($expected[0], $results[0]);
        $this->assertEquals($expected[1], $results[1]);
    }

    public function testTransform()
    {
        $data                    = $this->getMockEloquentBuilder();
        $settings                = $this->getSettings();
        $settings['transformer'] = function ($el) {
            $el->foo = 'foobar';

            return $el->toArray();
        };

        $handler = new Handler($data, $settings);

        $query = $handler->getData();

        $expected = new Collection([
            new Bar(['foo' => 'bar', 'baz' => 'foo']),
            new Bar(['foo' => 'fred', 'baz' => 'bar']),
        ]);

        $validated = [
            ['foo' => 'foobar', 'baz' => 'foo'],
            ['foo' => 'foobar', 'baz' => 'bar'],
        ];

        $query->shouldReceive('get')->andReturn($expected);

        $handler->hydrate();

        $results = $handler->toArray();

        // Validate the orders are correct
        $this->assertEquals($validated[0], $results[0]);
        $this->assertEquals($validated[1], $results[1]);
    }

    public function testSortingHasMany()
    {
        $data = m::mock('Illuminate\Database\Eloquent\Relations\HasMany');
        $data->shouldReceive('getQuery')->once()->andReturn($builder = m::mock('Illuminate\Database\Query\Builder'));
        $builder->shouldReceive('orderBy')->once();

        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');
        $handler  = new Handler($data, $this->getSettings());
        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getMethod')
            ->shouldReceive('getThreshold')
            ->shouldReceive('getThrottle')
            ->shouldReceive('getSort')->once()->andReturn([['column' => 'qux', 'direction' => 'desc']]);

        $handler->setRequestProvider($provider);

        $handler->prepareSort();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCalculatingPaginationThrowsExceptionIfRequestedPagesIsZero()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $handler->calculatePagination(10, 'single', 0, 0);
    }

    public function testCalculatingPagination1()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $result = $handler->calculatePagination(100, 'group', 100, 10);
        $this->assertCount(2, $result);
        list($totalPages, $perPage) = $result;
        $this->assertSame(10, $totalPages);
        $this->assertSame(10, $perPage);
    }

    public function testCalculatingPagination2()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $result                     = $handler->calculatePagination(90, 'group', 100, 10);
        list($totalPages, $perPage) = $result;
        $this->assertSame(1, $totalPages);
        $this->assertSame(90, $perPage);
    }

    public function testCalculatingPagination3()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $result                     = $handler->calculatePagination(120, 'group', 100, 10);
        list($totalPages, $perPage) = $result;
        $this->assertSame(10, $totalPages);
        $this->assertSame(12, $perPage);
    }

    public function testCalculatingPagination4()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $result                     = $handler->calculatePagination(1200, 'single', 100, 100);
        list($totalPages, $perPage) = $result;
        $this->assertSame(12, $totalPages);
        $this->assertSame(100, $perPage);
    }

    public function testCalculatingPagination5()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $result                     = $handler->calculatePagination(12000, 'single', 100, 100);
        list($totalPages, $perPage) = $result;
        $this->assertSame(120, $totalPages);
        $this->assertSame(100, $perPage);
    }

    public function testCalculatingPagination6()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $result                     = $handler->calculatePagination(170, 'group', 100, 10);
        list($totalPages, $perPage) = $result;
        $this->assertSame(10, $totalPages);
        $this->assertSame(17, $perPage);
    }

    public function testCalculatingPagination7()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $result                     = $handler->calculatePagination(171, 'group', 100, 10);
        list($totalPages, $perPage) = $result;
        $this->assertSame(10, $totalPages);
        $this->assertSame(18, $perPage);
    }

    public function testSettingUpPaginationLeavesDefaultParametersIfNoFilteredResultsArePresent()
    {
        $handler = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[calculatePagination]',
            [$this->getMockEloquentBuilder(), $this->getSettings()]);

        $handler->preparePagination();
    }

    public function testSettingUpPaginationWithOnePage()
    {
        $handler = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[calculatePagination]',
            [$this->getMockEloquentBuilder(), $this->getSettings()]);

        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');

        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getThreshold')->andReturn(100)
            ->shouldReceive('getThrottle')->andReturn(100)
            ->shouldReceive('getMethod')->andReturn('group')
            ->shouldReceive('getPage')->once()->andReturn(1);

        $handler->setRequestProvider($provider);

        $handler->getParameters()->set('filtered', 10);

        $handler->shouldReceive('calculatePagination')->with(10, 'group', 100, 100)->once()->andReturn([1, 10]);

        $handler->getData()->shouldReceive('forPage')->with(1, 10)->once();

        $handler->preparePagination();

        $this->assertNull($handler->getParameters()->get('previousPage'));
        $this->assertNull($handler->getParameters()->get('nextPage'));
        $this->assertSame(1, $handler->getParameters()->get('pages'));
    }

    public function testSettingUpPaginationOnPage2Of3()
    {
        $handler = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[calculatePagination]',
            [$this->getMockEloquentBuilder(), $this->getSettings()]);

        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');

        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getThreshold')->andReturn(100)
            ->shouldReceive('getThrottle')->andReturn(100)
            ->shouldReceive('getMethod')->andReturn('group')
            ->shouldReceive('getPage')->once()->andReturn(2);

        $handler->setRequestProvider($provider);

        $handler->getParameters()->set('filtered', 30);

        $handler->shouldReceive('calculatePagination')->with(30, 'group', 100, 100)->once()->andReturn([3, 10]);

        $handler->getData()->shouldReceive('forPage')->with(2, 10)->once();

        $handler->preparePagination();

        $this->assertSame(1, $handler->getParameters()->get('previousPage'));
        $this->assertSame(3, $handler->getParameters()->get('nextPage'));
        $this->assertSame(3, $handler->getParameters()->get('pages'));
    }

    public function testSettingUpPaginationOnPage3Of3()
    {
        $handler = m::mock('Cartalyst\DataGrid\Laravel\DataHandlers\DatabaseHandler[calculatePagination]',
            [$this->getMockEloquentBuilder(), $this->getSettings()]);

        $provider = m::mock('Cartalyst\DataGrid\Contracts\Provider');

        $provider
            ->shouldReceive('getDefaultMethod')
            ->shouldReceive('getDefaultThrottle')
            ->shouldReceive('getDefaultThreshold');

        $provider
            ->shouldReceive('getThreshold')->andReturn(100)
            ->shouldReceive('getThrottle')->andReturn(100)
            ->shouldReceive('getMethod')->andReturn('group')
            ->shouldReceive('getPage')->once()->andReturn(3);

        $handler->setRequestProvider($provider);

        $handler->getParameters()->set('filtered', 30);

        $handler->shouldReceive('calculatePagination')->with(30, 'group', 100, 100)->once()->andReturn([3, 10]);

        $handler->getData()->shouldReceive('forPage')->with(3, 10)->once();

        $handler->preparePagination();

        $this->assertSame(2, $handler->getParameters()->get('previousPage'));
        $this->assertNull($handler->getParameters()->get('nextPage'));
        $this->assertSame(3, $handler->getParameters()->get('pages'));
    }

    public function testHydrating()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $results = [
            $result1 = new stdClass(),
            $result2 = new stdClass(),
        ];

        $result1->foo   = 'bar';
        $result1->baz   = 'qux';
        $result2->corge = 'fred';

        $handler->getData()->shouldReceive('get')->andReturn($results);

        $handler->hydrate();

        $expected = [
            ['foo' => 'bar', 'baz' => 'qux'],
            ['corge' => 'fred'],
        ];

        $this->assertCount(count($expected), $results = $handler->toArray());
        $this->assertEquals($expected, $results);

        foreach ($results as $index => $result) {
            $this->assertTrue(array_key_exists($index, $results));
            $this->assertEquals($expected[$index], $result);
        }
    }

    public function testHydratingMaxResults()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $results = [
            $result1 = new stdClass(),
        ];

        $result1->foo = 'bar';
        $result1->baz = 'qux';

        $handler->getData()->shouldReceive('get')->andReturn($results);
        $handler->getData()->shouldReceive('limit')->once()->with(1);

        $handler->hydrate(1);

        $expected = [
            ['foo' => 'bar', 'baz' => 'qux'],
        ];

        $this->assertCount(count($expected), $results = $handler->toArray());
        $this->assertEquals($expected, $results);
    }

    public function testHydrating1()
    {
        $handler = new Handler($this->getMockEloquentBuilder(), $this->getSettings());

        $expected = new Collection([
            new Collection([
                'foo' => 'bar',
                'baz' => new Collection(['name' => 'foo']),
            ]),
            new Collection([
                'corge' => 'fred',
                'baz'   => new Collection(['name' => 'bar']),
            ]),
        ]);

        $handler->getData()->shouldReceive('get')->andReturn($expected);

        $handler->hydrate();

        $results = $handler->toArray();

        // Validate the orders are correct
        $this->assertEquals($expected[0]->toArray(), $results[0]);
        $this->assertEquals($expected[1]->toArray(), $results[1]);
    }

    protected function getMockEloquentBuilder()
    {
        $builder = m::mock('Illuminate\Database\Eloquent\Builder');
        $builder->shouldReceive('getModel')->once()->andReturn($model = m::mock('Illuminate\Database\Eloquent\Model'));
        $builder->shouldReceive('getQuery')->andReturn(m::mock('Illuminate\Database\Query\Builder'));

        $model->shouldReceive('attributesToArray')->once()->andReturn([]);

        return $builder;
    }

    protected function getMockModel()
    {
        $model = m::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('attributesToArray')->once()->andReturn([]);
        $model->shouldReceive('newQuery')->once()->andReturn(m::mock('Illuminate\Database\Query\Builder'));

        return $model;
    }

    protected function getSettings()
    {
        return [
            'columns' => [
                'foo',
                'bar.baz' => 'qux',
            ],
        ];
    }
}
