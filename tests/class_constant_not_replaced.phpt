--TEST--
Generics extension: class constants like Something::TModel should not be replaced
--DESCRIPTION--
Tests that class constant access using :: syntax (e.g., Something::TModel) is not
incorrectly detected as a generic type parameter and replaced with Something::mixed.
The T-prefixed identifiers should only be replaced when they appear in type positions,
not when accessing class constants.
--SKIPIF--
<?php
if (!extension_loaded('erased_generics')) die('skip erased_generics extension not loaded');
?>
--FILE--
<?php

class ModelTypes {
    public const TModel = 'User';
    public const TKey = 'id';
    public const TValue = 'name';
}

// These class constant accesses should NOT be replaced
echo "Model type: " . ModelTypes::TModel . "\n";
echo "Key type: " . ModelTypes::TKey . "\n";
echo "Value type: " . ModelTypes::TValue . "\n";

?>
--EXPECT--
Model type: User
Key type: id
Value type: name
