--TEST--
Generics extension: multiple generic type parameters are erased
--DESCRIPTION--
Tests that multiple parameters with generic types are all correctly
erased, including mixed generic and non-generic parameters.
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

class Container {
    public string $label;

    public function __construct(string $label) {
        $this->label = $label;
    }
}

function process(array<Widget> $widgets, array<Container> $containers, int $limit): string {
    $result = [];

    foreach ($widgets as $widget) {
        $result[] = 'widget:' . $widget->name;
    }

    foreach ($containers as $container) {
        $result[] = 'container:' . $container->label;
    }

    return implode(',', array_slice($result, 0, $limit));
}

$widgets    = [new Widget('w1'), new Widget('w2')];
$containers = [new Container('c1')];

echo process($widgets, $containers, 3) . "\n";
echo process($widgets, $containers, 2) . "\n";
?>
--EXPECT--
widget:w1,widget:w2,container:c1
widget:w1,widget:w2
