--TEST--
Generics extension: standard PHPDoc generic type parameters (T, TModel, etc.)
--DESCRIPTION--
Tests that standard PHPDoc generic type parameters like "T" or "TModel" are replaced
with "mixed" when used in type declarations. The generic type parameters should work
in class properties, function parameters, and return types.
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

// Test with single letter T
class Foo<T> {
    public T $widgets;

    public function setWidgets(T $value): void {
        $this->widgets = $value;
    }

    public function getWidgets(): T {
        return $this->widgets;
    }
}

// Test with TModel
class Bar<TModel> {
    public TModel $item;
    private TModel $anotherItem;

    public function setItem(TModel $value): void {
        $this->item = $value;
    }

    public function getItem(): TModel {
        return $this->item;
    }
}

// Test instantiation
$foo = new Foo<Widget>();
$widget = new Widget('test');
$foo->setWidgets($widget);

echo $foo->getWidgets()->name . "\n";
echo ($foo instanceof Foo ? 'true' : 'false') . "\n";

$bar = new Bar<Widget>();
$bar->setItem($widget);
echo $bar->getItem()->name . "\n";

?>
--EXPECT--
test
true
test
