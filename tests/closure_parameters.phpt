--TEST--
Generics extension: closure function parameters with generic types
--DESCRIPTION--
Tests that closures with generic type parameters are correctly erased.
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

class Map implements Countable {
    private array $data = [];

    public function __construct(array $data = []) {
        $this->data = $data;
    }

    public function count(): int {
        return count($this->data);
    }
}

class Collection implements Countable {
    private array $items = [];

    public function __construct(array $items = []) {
        $this->items = $items;
    }

    public function count(): int {
        return count($this->items);
    }
}

// Closure with generic parameter
$processWidgets = function(array<Widget> $widgets): void {
    foreach ($widgets as $widget) {
        echo $widget->name . "\n";
    }
};

// Arrow function with generic parameter
$filterMap = fn(Map<string, int> $map) => count($map);

// Closure with multiple generic parameters
$processMap = function(array<string, Widget> $map): int {
    return count($map);
};

// Nested closure with generic parameter
$factory = function() {
    return function(Collection<Widget> $items): int {
        return count($items);
    };
};

$widgets = [new Widget('foo'), new Widget('bar'), new Widget('baz')];
$processWidgets($widgets);

echo "Map count: " . $filterMap(new Map(['a' => 1, 'b' => 2])) . "\n";
echo "Widget map count: " . $processMap(['w1' => new Widget('x'), 'w2' => new Widget('y')]) . "\n";

$innerClosure = $factory();
echo "Collection count: " . $innerClosure(new Collection([new Widget('a'), new Widget('b')])) . "\n";
?>
--EXPECT--
foo
bar
baz
Map count: 2
Widget map count: 2
Collection count: 2
