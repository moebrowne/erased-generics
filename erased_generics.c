#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"

#include <stdio.h>
#include <string.h>
#include <ctype.h>

/* Version defined here directly to avoid header ordering issues */
#define PHP_ERASED_GENERICS_VERSION "0.1.0"

/* Store the original compile function pointer */
static zend_op_array *(*original_compile_file)(zend_file_handle *file_handle, int type);

/* -----------------------------------------------------------------------
 * strip_generics()
 *
 * Walks through the source string and removes generic type annotations
 * of the form:  SomeType<A, B>  ->  SomeType
 *
 * Handles:
 *   - Nested generics:  array<Map<string, Widget>>
 *   - Multiple params:  array<string, int>
 *   - Whitespace:       array < Widget >
 *
 * Does NOT strip inside strings or comments to avoid false positives.
 * ----------------------------------------------------------------------- */
static char *strip_generics(const char *source, size_t source_len, size_t *new_len)
{
    char *result = emalloc(source_len + 1);
    size_t r = 0;
    size_t i = 0;

    while (i < source_len) {

        /* ---- Skip heredoc/nowdoc ---- */
        if (i + 3 <= source_len && source[i] == '<' && source[i+1] == '<' && source[i+2] == '<') {
            /* Look ahead to see if this is actually a heredoc/nowdoc */
            size_t test_pos = i + 3;
            while (test_pos < source_len && (source[test_pos] == ' ' || source[test_pos] == '\t')) {
                test_pos++;
            }

            /* Check if we have a valid heredoc/nowdoc identifier */
            int has_quote = (test_pos < source_len && source[test_pos] == '\'');
            size_t id_start = test_pos + (has_quote ? 1 : 0);
            size_t id_end = id_start;
            while (id_end < source_len && (isalnum((unsigned char)source[id_end]) || source[id_end] == '_')) {
                id_end++;
            }

            /* If we found a valid identifier, treat this as heredoc/nowdoc */
            if (id_end > id_start) {
                /* Copy <<< */
                result[r++] = source[i++];
                result[r++] = source[i++];
                result[r++] = source[i++];

                /* Copy whitespace */
                while (i < source_len && (source[i] == ' ' || source[i] == '\t')) {
                    result[r++] = source[i++];
                }

                /* Check for nowdoc quote */
                int is_nowdoc = (i < source_len && source[i] == '\'');
                if (is_nowdoc) {
                    result[r++] = source[i++];
                }

                /* Copy and save the label */
                size_t label_start = i;
                while (i < source_len && (isalnum((unsigned char)source[i]) || source[i] == '_')) {
                    result[r++] = source[i++];
                }
                size_t label_len = i - label_start;

                if (label_len == 0) {
                    /* Not a valid heredoc/nowdoc, just continue normally */
                    continue;
                }

                char *label = emalloc(label_len + 1);
                memcpy(label, source + label_start, label_len);
                label[label_len] = '\0';

                /* Copy closing quote if nowdoc */
                if (is_nowdoc && i < source_len && source[i] == '\'') {
                    result[r++] = source[i++];
                }

                /* Copy until newline */
                while (i < source_len && source[i] != '\n') {
                    result[r++] = source[i++];
                }
                if (i < source_len && source[i] == '\n') {
                    result[r++] = source[i++];
                }

                /* Copy everything until we find the closing label */
                int found_end = 0;
                while (i < source_len && !found_end) {
                    /* Check if we're at the start of a line */
                    if (i == 0 || source[i-1] == '\n') {
                        /* Check if this line starts with the label */
                        size_t j = 0;
                        while (j < label_len && i + j < source_len && source[i+j] == label[j]) {
                            j++;
                        }
                        /* Check if label is followed by ; or newline or end */
                        if (j == label_len &&
                            (i + j >= source_len || source[i+j] == ';' ||
                             source[i+j] == '\n' || source[i+j] == '\r')) {
                            /* Copy the closing label */
                            for (size_t k = 0; k < label_len; k++) {
                                result[r++] = source[i++];
                            }
                            found_end = 1;
                            break;
                        }
                    }
                    /* Not the end label, just copy the character */
                    result[r++] = source[i++];
                }

                efree(label);
                continue;
            }
        }

        /* ---- Skip single-quoted strings ---- */
        if (source[i] == '\'') {
            result[r++] = source[i++];
            while (i < source_len) {
                result[r++] = source[i];
                if (source[i] == '\\' && i + 1 < source_len) {
                    i++;
                    result[r++] = source[i];
                } else if (source[i] == '\'') {
                    i++;
                    break;
                }
                i++;
            }
            continue;
        }

        /* ---- Skip double-quoted strings ---- */
        if (source[i] == '"') {
            result[r++] = source[i++];
            while (i < source_len) {
                result[r++] = source[i];
                if (source[i] == '\\' && i + 1 < source_len) {
                    i++;
                    result[r++] = source[i];
                } else if (source[i] == '"') {
                    i++;
                    break;
                }
                i++;
            }
            continue;
        }

        /* ---- Skip single-line comments // ---- */
        if (source[i] == '/' && i + 1 < source_len && source[i + 1] == '/') {
            while (i < source_len && source[i] != '\n') {
                result[r++] = source[i++];
            }
            continue;
        }

        /* ---- Skip single-line comments # ---- */
        if (source[i] == '#') {
            while (i < source_len && source[i] != '\n') {
                result[r++] = source[i++];
            }
            continue;
        }

        /* ---- Skip multi-line comments ---- */
        if (source[i] == '/' && i + 1 < source_len && source[i + 1] == '*') {
            result[r++] = source[i++];
            result[r++] = source[i++];
            while (i < source_len) {
                if (source[i] == '*' && i + 1 < source_len && source[i + 1] == '/') {
                    result[r++] = source[i++];
                    result[r++] = source[i++];
                    break;
                }
                result[r++] = source[i++];
            }
            continue;
        }

        /*
         * ---- Detect generic syntax ----
         *
         * Look for:  identifier <whitespace> < ...nested... >
         *
         * We only strip the <...> part, keeping the identifier itself.
         * We check that the character before '<' is a valid identifier char
         * to avoid stripping comparison operators like ($a < $b).
         */
        if (source[i] == '<' && r > 0 &&
            (isalnum((unsigned char)result[r - 1]) || result[r - 1] == '_')) {

            /* Peek ahead past whitespace to confirm there is a matching '>' */
            size_t peek = i + 1;
            while (peek < source_len && source[peek] == ' ') peek++;

            /* Only treat as generic if next char looks like a type (letter, \, or ?) */
            if (peek < source_len &&
                (isalpha((unsigned char)source[peek]) || source[peek] == '\\' || source[peek] == '?')) {

                /* Consume the entire <...> block, respecting nesting */
                int depth = 1;
                i++; /* skip the opening '<' */
                while (i < source_len && depth > 0) {
                    if (source[i] == '<') depth++;
                    else if (source[i] == '>') depth--;
                    i++;
                }
                /* The <...> block has been consumed and discarded */
                continue;
            }
        }


        /* Default: copy character as-is */
        result[r++] = source[i++];
    }

    result[r] = '\0';
    *new_len  = r;
    return result;
}

/* -----------------------------------------------------------------------
 * read_file_contents()
 *
 * Reads an entire file into a newly emalloc'd buffer.
 * Returns NULL on failure.
 * ----------------------------------------------------------------------- */
static char *read_file_contents(const char *filename, size_t *out_len)
{
    FILE *fp = fopen(filename, "rb");
    if (!fp) {
        return NULL;
    }

    fseek(fp, 0, SEEK_END);
    long file_size = ftell(fp);
    fseek(fp, 0, SEEK_SET);

    if (file_size <= 0) {
        fclose(fp);
        return NULL;
    }

    char *buffer = emalloc((size_t)file_size + 1);
    size_t bytes_read = fread(buffer, 1, (size_t)file_size, fp);
    fclose(fp);

    buffer[bytes_read] = '\0';
    *out_len = bytes_read;

    return buffer;
}

/* -----------------------------------------------------------------------
 * erased_generics_compile_file()
 *
 * Replacement for zend_compile_file. Reads the file, strips generic
 * annotations, then compiles the modified source string.
 * ----------------------------------------------------------------------- */
zend_op_array *erased_generics_compile_file(zend_file_handle *file_handle, int type)
{
    const char *filename = NULL;

#if PHP_VERSION_ID >= 80100
    filename = ZSTR_VAL(file_handle->filename);
#else
    filename = file_handle->filename;
#endif

    if (!filename) {
        return original_compile_file(file_handle, type);
    }

    /* Read the raw source */
    size_t source_len = 0;
    char *source = read_file_contents(filename, &source_len);

    if (!source) {
        return original_compile_file(file_handle, type);
    }

    /* Strip generic annotations */
    size_t modified_len = 0;
    char *modified = strip_generics(source, source_len, &modified_len);
    efree(source);

    /* Compile the modified source as a string */
    zend_string *source_str   = zend_string_init(modified, modified_len, 0);
    zend_string *filename_str = zend_string_init(filename, strlen(filename), 0);

    efree(modified);

    zend_op_array *op_array = zend_compile_string(
        source_str,
        ZSTR_VAL(filename_str)
#if PHP_VERSION_ID >= 80200
        , ZEND_COMPILE_POSITION_AT_OPEN_TAG
#endif
    );

    zend_string_release(source_str);
    zend_string_release(filename_str);

    return op_array;
}

/* -----------------------------------------------------------------------
 * PHP_MINFO
 * ----------------------------------------------------------------------- */
PHP_MINFO_FUNCTION(erased_generics)
{
    php_info_print_table_start();
    php_info_print_table_header(2, "erased_generics support", "enabled");
    php_info_print_table_row(2, "Version", PHP_ERASED_GENERICS_VERSION);
    php_info_print_table_end();
}

/* -----------------------------------------------------------------------
 * Module init / shutdown
 * ----------------------------------------------------------------------- */
PHP_MINIT_FUNCTION(erased_generics)
{
    original_compile_file = zend_compile_file;
    zend_compile_file     = erased_generics_compile_file;
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(erased_generics)
{
    zend_compile_file = original_compile_file;
    return SUCCESS;
}

/* -----------------------------------------------------------------------
 * Module entry
 * ----------------------------------------------------------------------- */
zend_module_entry erased_generics_module_entry = {
    STANDARD_MODULE_HEADER,
    "erased_generics",
    NULL,
    PHP_MINIT(erased_generics),
    PHP_MSHUTDOWN(erased_generics),
    NULL,
    NULL,
    PHP_MINFO(erased_generics),
    PHP_ERASED_GENERICS_VERSION,
    STANDARD_MODULE_PROPERTIES
};

ZEND_GET_MODULE(erased_generics)
