# PHP Erased Generics Extension

An experimental PHP extension which adds support for erased generics.

- [x] Get an extension compiled and loaded
- [x] Get a simple version working
- [x] Write some docs
- [x] Add some tests
- [x] Add examples
- [x] Support union types
- [x] Support generic `T` and `TSomething` types
- [x] Get it working with [PIE](https://github.com/php/pie)



## Install

```
pie install moebrowne/erased-generics
```

<details>

<summary>Compile Manually</summary>

```
make clean
phpize --clean
phpize
./configure
make
sudo make install
```

</details>


## How It Works

The extension overrides the `zend_compile_file` function which is called each time PHP loads a file for compilation. The
overridden function reads the raw source from disk strips out generic type annotations then passes the modified source
to the original `zend_compile_file` call.

All native type declarations are kept, for example `array<Widget>` becomes `array`.


## Supported Syntax

### Functions

```php
// Function parameters
function foo(array<Widget> $items) {}
function foo(array<string, int> $map) {}
function foo(Map<string, Widget> $map) {}

// Return types
function getWidgets(): array<Widget> {}
function getMap(): Map<string, Widget> {}
function getData(): array<string, int> {}
```


### Generic Type Parameters

```php
class Foo<TModel> {
    public TModel $item;

    public function foo(TModel $value): TModel {}
}
```


### Classes

```php
// Class instantiation
new Thing<Widget>();
new Pair<string, int>();

// Class properties
class Foo {
    public array<Widget> $widgets;
}

// Class methods
class Foo {
    public function getWidgets(): array<Widget> {}
    private function getMap(): Map<string, int> {}
    protected static function getData(): Collection<Widget> {}
}

// Constructor promotion
class Foo {
    public function __construct(
        public Collection<Widget> $widgets,
        private array<int> $ids,
    ) {}
}
```


### Closures

```php
// Closure parameters
$process = function (array<Widget> $items) {};
$map = function (Map<string, int> $data) {};
$filter = fn (Collection<Widget> $widgets) => count($widgets);

// Closure return types
$closure = function(): array<Widget> {};
$fn = fn(): array<string, int> => [];
```


### Nested Generics

```php
function foo(array<Map<string, Widget>> $data) {}
function getNestedData(): array<Map<string, Widget>> {}
```


### Unions

```php
// Union types with generics
function process(array<Widget>|null $items) {}
function handle(Collection<Widget>|array<Widget> $data) {}
function fetch(): array<string, int>|false {}

// Union types inside generic brackets
function process(Collection<Widget|string> $data) {}
function transform(array<Widget|string|int> $items) {}

// Union types in classes
class Container {
    public array<Widget>|null $widgets;
    public Collection<Widget|Thingy> $items;

    public function __construct(
        public array<Widget>|null $data,
    ) {}

    public function find(): array<Widget>|null {}
}
```


### Fully Qualified Namespaces

```php
function foo(array<\App\Models\Widget> $items) {}
new Collection<\App\Models\Widget>();
function getModels(): array<\App\Models\Widget> {}
```


### Short-hand Nullable

```php
function foo(array<?Widget> $items) {}
function foo(?array<Widget> $items) {}
```

