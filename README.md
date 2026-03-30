# PHP Erased Generics Extension

An experimental PHP extension which adds support for erased generics.

- [x] Get an extension compiled and loaded
- [x] Get a simple version working
- [x] Write some docs
- [x] Add some tests
- [x] Add examples
- [x] Support union types
- [ ] Support generic `T` types
- [ ] Get it working with [PIE](https://github.com/php/pie)



## Examples

All the following are supported:


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


### Nested Generics

```php
function foo(array<Map<string, Widget>> $data) {}
function foo(Collection<array<int>> $items) {}
function getNestedData(): array<Map<string, Widget>> {}
```


### Union Types

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


### Not supported

```php
class Foo<T> {
    public T $widgets;
}
```




## How It Works

The extension overrides the `zend_compile_file` function. This function is called each time PHP loads a file for
compilation. The overridden function gets the raw source from disk and strips out generic type annotations then passing
it back to the original `zend_compile_file` call.



## Compile & Install

```
make clean
phpize --clean
phpize
./configure
make
sudo make install
```


## Run

As a one-off:

```
php -d extension=/path/to/erased-generics/modules/erased_generics.so -f file.php
```
