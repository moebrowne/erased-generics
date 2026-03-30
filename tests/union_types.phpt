--TEST--
Generics extension: union types with generics
--DESCRIPTION--
Tests that union types work correctly with generic syntax.
Union types with generics should have the generic portion stripped
while maintaining the union type syntax (|).
--SKIPIF--
<?php
if (!extension_loaded('erased_generics')) die('skip generics extension not loaded');
?>
--FILE--
<?php

class Widget {
    public string $name;
    public function __construct(string $name) {
        $this->name = $name;
    }
}

class Collection {}

// Function parameters with union types and generics
function processData(array<Widget>|null $items): void {
    if ($items === null) {
        echo "null\n";
    } else {
        echo "array with " . count($items) . " items\n";
    }
}

function handleWidgets(Collection<Widget>|array<Widget> $widgets): string {
    if (is_array($widgets)) {
        return "array";
    }
    return "collection";
}

function mixedTypes(array<string, int>|false $data): string {
    if ($data === false) {
        return "false";
    }
    return "array";
}

// Return types with union types and generics
function getOptionalWidgets(): array<Widget>|null {
    return null;
}

function getWidgetsOrCollection(): array<Widget>|Collection<Widget> {
    return [];
}

function fetchData(): array<string, int>|false {
    return false;
}

// Class methods with union types and generics
class DataRepository {
    public function find(int $id): array<Widget>|null {
        return null;
    }

    public function findAll(): array<Widget>|Collection<Widget> {
        return [new Widget('test')];
    }

    private function getData(): array<string, int>|false {
        return ['key' => 42];
    }
}

// Properties with union types and generics
class Container {
    public array<Widget>|null $widgets = null;
    private Collection<string>|array<string> $items;
}

// Constructor promotion with union types and generics
class Service {
    public function __construct(
        public array<Widget>|null $widgets,
        private Collection<int>|array<int> $ids = [],
    ) {}
}

// Test execution
processData([new Widget('foo'), new Widget('bar')]);
processData(null);

echo handleWidgets([new Widget('test')]) . "\n";
echo handleWidgets(new Collection()) . "\n";

echo mixedTypes(['a' => 1]) . "\n";
echo mixedTypes(false) . "\n";

var_dump(getOptionalWidgets());
var_dump(is_array(getWidgetsOrCollection()));
var_dump(fetchData());

$repo = new DataRepository();
var_dump($repo->find(1));
var_dump(is_array($repo->findAll()));

$container = new Container();
var_dump($container->widgets);

$service = new Service([new Widget('service')]);
var_dump(count($service->widgets));

echo "All union type tests passed\n";
?>
--EXPECT--
array with 2 items
null
array
collection
array
false
NULL
bool(true)
bool(false)
NULL
bool(true)
NULL
int(1)
All union type tests passed
