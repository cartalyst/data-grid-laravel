## Usage

In this section we'll show how you can make use of the extensions package.

### Example

```
$object = new StdClass;
$object->title = 'foo';
$object->age = 20;

$data = [
    [
        'title' => 'bar',
        'age'   => 34,
    ],
    $object,
];

$settings = [
    'columns' => [
        'title',
        'age',
    ]
];

$handler = new CollectionHandler($data, $settings);
$dataGrid = DataGrid::make($handler);
```
