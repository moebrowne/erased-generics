--TEST--
Shorthand nullable generic types are supported
--FILE--
<?php

class Widget {}

function process(?Widget $item): ?Widget {
    return $item;
}

var_dump(process(new Widget()));
var_dump(process(null));

?>
--EXPECT--
object(Widget)#1 (0) {
}
NULL
