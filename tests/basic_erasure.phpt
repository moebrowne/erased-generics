--TEST--
Generics extension: basic type erasure on function parameter
--DESCRIPTION--
Tests that array<Widget> is correctly erased to array before parsing,
allowing the function to be called without parse errors.
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

function something(array<Widget> $widgets): void {
    foreach ($widgets as $widget) {
        echo $widget->name . "\n";
    }
}

$widgets = [new Widget('foo'), new Widget('bar')];
something($widgets);
?>
--EXPECT--
foo
bar
