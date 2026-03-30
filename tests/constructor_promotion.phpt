--TEST--
Type erasure with constructor promotion
--FILE--
<?php
class Widget {}
class Collection {}

class Foo {
    public function __construct(
        public Collection<Widget> $widgets,
        private array<int> $ids,
    ) {}
}

$foo = new Foo(new Collection(), [1, 2, 3]);
echo "Constructor promotion test passed\n";
?>
--EXPECT--
Constructor promotion test passed
