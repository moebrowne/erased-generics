--TEST--
Generics extension: fully qualified namespace types in generics
--DESCRIPTION--
Tests that fully qualified namespace types (e.g., \App\Models\Widget) inside
generic brackets are correctly erased.
--SKIPIF--
<?php
if (!extension_loaded('erased_generics')) die('skip generics extension not loaded');
?>
--FILE--
<?php

namespace App\Models {
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
}

namespace App {
    // Function parameter with fully qualified namespace
    function processWidgets(array<\App\Models\Widget> $items): void {
        foreach ($items as $widget) {
            echo $widget->name . "\n";
        }
    }

    // Return type with fully qualified namespace
    function getWidgets(): array<\App\Models\Widget> {
        return [
            new \App\Models\Widget('foo'),
            new \App\Models\Widget('bar')
        ];
    }

    // Class instantiation with fully qualified namespace
    $collection = new \App\Models\Collection<\App\Models\Widget>();
    $collection->add(new \App\Models\Widget('test'));
    echo count($collection->all()) . "\n";

    // Class with fully qualified namespace in properties and methods
    class WidgetRepository {
        private array<\App\Models\Widget> $widgets = [];

        public function add(\App\Models\Widget $widget): void {
            $this->widgets[] = $widget;
        }

        public function getAll(): array<\App\Models\Widget> {
            return $this->widgets;
        }
    }

    $repo = new WidgetRepository();
    $repo->add(new \App\Models\Widget('repo_widget'));
    echo count($repo->getAll()) . "\n";

    // Test execution
    $widgets = getWidgets();
    processWidgets($widgets);

    echo "Fully qualified namespace tests passed\n";
}
?>
--EXPECT--
1
1
foo
bar
Fully qualified namespace tests passed
