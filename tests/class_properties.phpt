--TEST--
Generics extension: generic type parameters on class properties
--DESCRIPTION--
Tests that generic type parameters on class property declarations are correctly
erased before parsing, allowing the code to execute without parse errors.
--SKIPIF--
<?php
if (!extension_loaded('erased_generics')) die('skip erased_generics extension not loaded');
?>
--FILE--
<?php

class Item {
    public string $value;

    public function __construct(string $value) {
        $this->value = $value;
    }
}

class Container<T> {
    public mixed $item;
    private array<T> $items = [];

    public function setItem(mixed $item): void {
        $this->item = $item;
    }

    public function addToItems(mixed $item): void {
        $this->items[] = $item;
    }

    public function getItem(): mixed {
        return $this->item;
    }

    public function getItems(): array {
        return $this->items;
    }
}

$container = new Container<Item>();
$item1 = new Item('first');
$item2 = new Item('second');

$container->setItem($item1);
$container->addToItems($item1);
$container->addToItems($item2);

echo $container->getItem()->value . "\n";
echo count($container->getItems()) . "\n";
?>
--EXPECT--
first
2
