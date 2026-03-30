--TEST--
Generics extension: nullable types inside generic brackets
--DESCRIPTION--
Tests that nullable types inside generic brackets like array<?Widget>
are correctly erased. The ? inside brackets is shorthand for Widget|null,
meaning an array containing nullable Widget elements.
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

class Collection {
    private array $items = [];

    public function add(mixed $item): void {
        $this->items[] = $item;
    }

    public function all(): array {
        return $this->items;
    }
}

// Function parameter with nullable type inside generics
function processWidgets(array<?Widget> $items): void {
    foreach ($items as $widget) {
        if ($widget === null) {
            echo "null\n";
        } else {
            echo $widget->name . "\n";
        }
    }
}

// Return type with nullable inside generics
function getOptionalWidgets(): array<?Widget> {
    return [new Widget('foo'), null, new Widget('bar')];
}

// Both syntaxes: long form with union type
function processWithUnion(array<Widget|null> $items): void {
    foreach ($items as $item) {
        echo ($item === null ? 'null-union' : $item->name) . "\n";
    }
}

// Class method with nullable inside generics
class WidgetService {
    public function find(array<?int> $ids): array<?Widget> {
        $result = [];
        foreach ($ids as $id) {
            if ($id === null) {
                $result[] = null;
            } else {
                $result[] = new Widget("widget_$id");
            }
        }
        return $result;
    }
}

// Property with nullable inside generics
class Container {
    public array<?Widget> $widgets;

    public function __construct() {
        $this->widgets = [new Widget('initial'), null];
    }
}

// Constructor promotion with nullable inside generics
class Repository {
    public function __construct(
        public array<?Widget> $widgets = [],
    ) {}
}

// Test execution
$widgets = getOptionalWidgets();
processWidgets($widgets);

// Test the union syntax equivalent
processWithUnion([new Widget('union'), null]);

$service = new WidgetService();
$found = $service->find([1, null, 2]);
foreach ($found as $widget) {
    echo ($widget === null ? 'service-null' : $widget->name) . "\n";
}

$container = new Container();
echo count($container->widgets) . "\n";

$repo = new Repository([new Widget('test'), null]);
echo count($repo->widgets) . "\n";

echo "Nullable inside generics tests passed\n";
?>
--EXPECT--
foo
null
bar
union
null-union
widget_1
service-null
widget_2
2
2
Nullable inside generics tests passed
