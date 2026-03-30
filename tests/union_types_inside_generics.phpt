--TEST--
Generics extension: union types inside generic brackets
--DESCRIPTION--
Tests that union types inside generic brackets like Widget<A|B> are correctly erased.
The entire <A|B> should be stripped, leaving just Widget.
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
    public array $items = [];
}

class Either {}
class Result {}

// Function parameters with union types inside generics
function process(Collection<Widget|string> $data): void {
    echo "processed\n";
}

function handle(Either<int|string> $value): void {
    echo "handled\n";
}

function transform(array<Widget|Collection> $items): void {
    echo "array with " . count($items) . " items\n";
}

// Return types with union types inside generics
function getData(): Collection<int|string> {
    return new Collection();
}

function getResult(): Result<Widget|string> {
    return new Result();
}

function getArray(): array<Widget|string|int> {
    return [new Widget('test'), 'hello', 42];
}

// Class methods with union types inside generics
class DataProcessor {
    public function process(Collection<Widget|string> $data): Either<int|string> {
        return new Either();
    }

    private function transform(array<int|string|float> $values): void {
        echo "transformed\n";
    }
}

// Properties with union types inside generics
class Container {
    public Collection<Widget|string> $items;
    private array<int|string> $data = [];
}

// Constructor promotion with union types inside generics
class Service {
    public function __construct(
        public Collection<Widget|string> $items,
        private array<int|string|float> $values = [],
    ) {}
}

// Nested generics with union types inside
function complex(array<Collection<Widget|string>> $data): void {
    echo "complex with " . count($data) . " collections\n";
}

function nested(Collection<array<int|string>> $items): void {
    echo "nested\n";
}

// Test execution
process(new Collection());
handle(new Either());
transform([new Widget('foo'), new Collection()]);

var_dump(getData() instanceof Collection);
var_dump(getResult() instanceof Result);
var_dump(count(getArray()));

$processor = new DataProcessor();
var_dump($processor->process(new Collection()) instanceof Either);

$container = new Container();
$container->items = new Collection();
var_dump($container->items instanceof Collection);

$service = new Service(new Collection());
var_dump($service->items instanceof Collection);

complex([new Collection(), new Collection()]);
nested(new Collection());

echo "All union types inside generics tests passed\n";
?>
--EXPECT--
processed
handled
array with 2 items
bool(true)
bool(true)
int(3)
bool(true)
bool(true)
bool(true)
complex with 2 collections
nested
All union types inside generics tests passed
