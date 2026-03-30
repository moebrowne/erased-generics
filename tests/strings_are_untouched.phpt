--TEST--
Generics extension: generic-like syntax inside strings and heredocs/nowdocs is not stripped
--DESCRIPTION--
Tests that the strip_generics() function does not modify content inside:
  - Single-quoted strings
  - Double-quoted strings
  - Heredoc strings
  - Nowdoc strings
Any <...> syntax appearing inside these contexts must be preserved verbatim.
--SKIPIF--
<?php
if (!extension_loaded('erased_generics')) die('skip erased_generics extension not loaded');
?>
--FILE--
<?php

// Single-quoted string: angle brackets must be preserved verbatim
$single = 'array<Widget>';
echo $single . "\n";

// Double-quoted string: angle brackets must be preserved verbatim
$double = "array<Widget>";
echo $double . "\n";

// Double-quoted string with escaped quote inside
$escaped = "He said \"array<Widget> is cool\"";
echo $escaped . "\n";

// Heredoc: angle brackets must be preserved verbatim
$heredoc = <<<EOT
array<Widget>
Map<string, int>
Collection<array<Widget>>
EOT;
echo $heredoc . "\n";

// Nowdoc: angle brackets must be preserved verbatim
$nowdoc = <<<'EOT'
array<Widget>
Map<string, int>
Collection<array<Widget>>
EOT;
echo $nowdoc . "\n";

// Verify that actual generic erasure still works outside strings
class Container<T> {
    private array $items = [];

    public function add(mixed $item): void {
        $this->items[] = $item;
    }

    public function first(): mixed {
        return $this->items[0] ?? null;
    }
}

$c = new Container<stdClass>();
$obj = new stdClass();
$obj->name = 'test';
$c->add($obj);

echo $c->first()->name . "\n";

// Verify a string containing generic syntax does NOT affect the variable value
$template = 'function foo(array<Widget> $w) {}';
echo strlen($template) . "\n";
echo $template . "\n";
?>
--EXPECT--
array<Widget>
array<Widget>
He said "array<Widget> is cool"
array<Widget>
Map<string, int>
Collection<array<Widget>>
array<Widget>
Map<string, int>
Collection<array<Widget>>
test
33
function foo(array<Widget> $w) {}
