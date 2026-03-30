--TEST--
Generics extension: nested generic type parameters are correctly erased
--DESCRIPTION--
Tests that nested generic annotations such as Collection<Map<string, Widget>>
and Collection<array<int>> are fully stripped before parsing, including
multiple levels of nesting.
--SKIPIF--
<?php
if (!extension_loaded('erased_generics')) die('skip erased_generics extension not loaded');
?>
--FILE--
<?php

class Widget {
    public string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }
}

class Collection<T> {
    private array $items = [];

    public function add(mixed $item): void {
        $this->items[] = $item;
    }

    public function all(): array {
        return $this->items;
    }

    public function count(): int {
        return count($this->items);
    }
}

class Map<K, V> {
    private array $data = [];

    public function set(mixed $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function get(mixed $key): mixed {
        return $this->data[$key] ?? null;
    }
}

// Nested generic: Collection<Map<string, Widget>>
function buildIndex(Collection<Map<string, Widget>> $collections): void {
    foreach ($collections->all() as $map) {
        echo $map->get('primary')->name . "\n";
    }
}

// Nested generic: Collection<array<int>>
function sumAll(Collection<array<int>> $groups): int {
    $total = 0;
    foreach ($groups->all() as $group) {
        $total += array_sum($group);
    }
    return $total;
}

// --- Test 1: Collection<Map<string, Widget>> ---
$map1 = new Map<string, Widget>();
$map1->set('primary', new Widget('foo'));

$map2 = new Map<string, Widget>();
$map2->set('primary', new Widget('bar'));

$collections = new Collection<Map<string, Widget>>();
$collections->add($map1);
$collections->add($map2);

buildIndex($collections);
echo $collections->count() . "\n";

// --- Test 2: Collection<array<int>> ---
$groups = new Collection<array<int>>();
$groups->add([1, 2, 3]);
$groups->add([4, 5]);

echo sumAll($groups) . "\n";

// --- Test 3: Three levels of nesting ---
// Collection<Map<string, Collection<Widget>>>
$inner1 = new Collection<Widget>();
$inner1->add(new Widget('nested'));

$outerMap = new Map<string, Collection<Widget>>();
$outerMap->set('group', $inner1);

$outer = new Collection<Map<string, Collection<Widget>>>();
$outer->add($outerMap);

echo $outer->count() . "\n";
echo $outer->all()[0]->get('group')->all()[0]->name . "\n";
?>
--EXPECT--
foo
bar
2
15
1
nested
