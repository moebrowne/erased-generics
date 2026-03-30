--TEST--
Generic return type erasure
--EXTENSIONS--
erased_generics
--FILE--
<?php

class Widget {
    public function __construct(public string $name) {}
}

class Collection {}

function getWidgets(): array<Widget> {
    return [new Widget('foo'), new Widget('bar')];
}

function getMap(): array<string, int> {
    return ['one' => 1, 'two' => 2];
}

function getCollection(): Collection<Widget> {
    return new Collection();
}

function getNestedData(): array<Collection<Widget>> {
    return [new Collection(), new Collection()];
}

function getNamespacedTypes(): array<\stdClass> {
    return [new \stdClass(), new \stdClass()];
}

class WidgetRepository {
    public function getAll(): array<Widget> {
        return [new Widget('public')];
    }

    private function getPrivate(): Collection<Widget> {
        return new Collection();
    }

    protected static function getStatic(): array<string, int> {
        return ['static' => 1];
    }

    public function testPrivate() {
        return $this->getPrivate();
    }

    public static function testStatic() {
        return self::getStatic();
    }
}

$closure = function(): array<Widget> {
    return [new Widget('closure')];
};

$arrowFn = fn(): array<string, int> => ['arrow' => 42];

var_dump(getWidgets());
var_dump(getMap());
var_dump(getCollection());
var_dump(getNestedData());
var_dump(getNamespacedTypes());

$repo = new WidgetRepository();
var_dump($repo->getAll());
var_dump($repo->testPrivate());
var_dump(WidgetRepository::testStatic());
var_dump($closure());
var_dump($arrowFn());

echo "Success: All return types with generics work correctly\n";

?>
--EXPECTF--
array(2) {
  [0]=>
  object(Widget)#%d (1) {
    ["name"]=>
    string(3) "foo"
  }
  [1]=>
  object(Widget)#%d (1) {
    ["name"]=>
    string(3) "bar"
  }
}
array(2) {
  ["one"]=>
  int(1)
  ["two"]=>
  int(2)
}
object(Collection)#%d (0) {
}
array(2) {
  [0]=>
  object(Collection)#%d (0) {
  }
  [1]=>
  object(Collection)#%d (0) {
  }
}
array(2) {
  [0]=>
  object(stdClass)#%d (0) {
  }
  [1]=>
  object(stdClass)#%d (0) {
  }
}
array(1) {
  [0]=>
  object(Widget)#%d (1) {
    ["name"]=>
    string(6) "public"
  }
}
object(Collection)#%d (0) {
}
array(1) {
  ["static"]=>
  int(1)
}
array(1) {
  [0]=>
  object(Widget)#%d (1) {
    ["name"]=>
    string(7) "closure"
  }
}
array(1) {
  ["arrow"]=>
  int(42)
}
Success: All return types with generics work correctly
