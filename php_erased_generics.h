#ifndef PHP_ERASED_GENERICS_H
#define PHP_ERASED_GENERICS_H

extern zend_module_entry erased_generics_module_entry;
#define phpext_erased_generics_ptr &erased_generics_module_entry

zend_op_array *erased_generics_compile_file(zend_file_handle *file_handle, int type);

#endif /* PHP_ERASED_GENERICS_H */
