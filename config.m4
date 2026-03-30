dnl config.m4 for erased_generics extension

PHP_ARG_ENABLE(erased_generics, whether to enable erased_generics support,
[  --enable-erased_generics        Enable erased_generics support], yes)

if test "$PHP_ERASED_GENERICS" != "no"; then
  PHP_NEW_EXTENSION(erased_generics, erased_generics.c, $ext_shared)
fi
