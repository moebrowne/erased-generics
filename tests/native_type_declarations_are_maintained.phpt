--TEST--
Generics extension: native type hint is preserved after generic erasure
--DESCRIPTION--
Verifies that stripping generic annotations does not accidentally remove
the base type hint. Specifically:

    function foo(array<Widget> $a)

must be erased to:

    function foo(array $a)

and NOT to:

    function foo($a)

This is tested by passing a non-array value and confirming a TypeError is
thrown, proving the `array` type hint is still enforced at runtime.
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

function processWidgets(array<Widget> $widgets): void {
    foreach ($widgets as $widget) {
        echo $widget->name . "\n";
    }
}

// 1. Verify the type hint still works correctly with a valid array
processWidgets([new Widget('foo'), new Widget('bar')]);

// 2. Verify the type hint is still enforced - passing a non-array must throw TypeError
try {
    processWidgets('not-an-array');
    echo "FAIL: TypeError was not thrown\n";
} catch (TypeError $e) {
    echo "TypeError caught: type hint is intact\n";
}

// 3. Use reflection to confirm the parameter type is `array`, not missing entirely
$ref   = new ReflectionFunction('processWidgets');
$param = $ref->getParameters()[0];
$type  = $param->getType();

if ($type === null) {
    echo "FAIL: parameter has no type (type hint was stripped entirely)\n";
} elseif ((string) $type === 'array') {
    echo "OK: parameter type is `array`\n";
} else {
    echo "FAIL: unexpected type: " . (string) $type . "\n";
}
?>
--EXPECT--
foo
bar
TypeError caught: type hint is intact
OK: parameter type is `array`
