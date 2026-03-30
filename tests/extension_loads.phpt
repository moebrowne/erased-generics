--TEST--
Check if erased_generics is loaded
--EXTENSIONS--
erased_generics
--FILE--
<?php
echo 'The extension "erased_generics" is available';
?>
--EXPECT--
The extension "erased_generics" is available
