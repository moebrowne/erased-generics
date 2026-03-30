--TEST--
Generics extension: generic type parameters on class declaration and instantiation
--DESCRIPTION--
Tests that generic type parameters on class declarations (class Thing<T> {})
and on object instantiation (new Thing<Widget>()) are correctly erased before
parsing, allowing the code to execute without parse errors.
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

class Thing<T> {
    private array $items = [];

    public function add(mixed $item): void {
        $this->items[] = $item;
    }

    public function count(): int {
        return count($this->items);
    }

    public function first(): mixed {
        return $this->items[0] ?? null;
    }
}

// Generic type on instantiation should be erased
$thing = new Thing<Widget>();
$thing->add(new Widget('foo'));
$thing->add(new Widget('bar'));

echo $thing->count() . "\n";
echo $thing->first()->name . "\n";

// Verify it is a plain Thing instance at runtime (type is erased)
echo ($thing instanceof Thing ? 'true' : 'false') . "\n";
echo get_class($thing) . "\n";
?>
--EXPECT--
2
foo
true
Thing
