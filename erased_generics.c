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
 * Helper: check if a character is valid in an identifier
 * ----------------------------------------------------------------------- */
static int is_identifier_char(char c)
{
    return isalnum((unsigned char)c) || c == '_';
}

/* -----------------------------------------------------------------------
 * Helper: check if string at position starts with a generic type param
 * (e.g., "T" or "TModel") and is followed by whitespace or specific chars
 *
 * Valid generic type parameters:
 *  - Just "T"
 *  - "T" followed by uppercase letters, digits, or underscores (e.g., "TModel", "TKey", "T1")
 *
 * Invalid (should not match):
 *  - TypeError, Throwable, etc. (lowercase letters after T)
 * ----------------------------------------------------------------------- */
static int is_generic_type_param(const char *str, size_t pos, size_t len)
{
    /* Must start with 'T' */
    if (pos >= len || str[pos] != 'T') {
        return 0;
    }

    /* Make sure we're not in the middle of a longer identifier */
    if (pos > 0 && is_identifier_char(str[pos - 1])) {
        return 0;
    }

    /* Find the end of the identifier, but enforce generic naming convention */
    size_t end = pos + 1;

    /* If there's a second character and it's a lowercase letter, reject */
    /* This catches TypeError, Throwable, Test, etc. */
    if (end < len && is_identifier_char(str[end])) {
        if (islower((unsigned char)str[end])) {
            return 0;
        }

        /* After the first check, accept any identifier characters */
        /* This allows TModel, TKey, TValue, etc. */
        end++;
        while (end < len && is_identifier_char(str[end])) {
            end++;
        }
    }

    /* Check if it's followed by a valid separator (whitespace, $, |, >, etc.) */
    if (end < len) {
        char next = str[end];
        if (next != ' ' && next != '\t' && next != '\n' && next != '\r' &&
            next != '$' && next != '|' && next != '>' && next != ',' &&
            next != ')' && next != ';') {
            return 0;
        }
    }

    return end - pos;
}

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
 *   - Generic type parameters: class Foo<T> { public T $x; } -> class Foo { public mixed $x; }
 *
 * Does NOT strip inside strings or comments to avoid false positives.
 * ----------------------------------------------------------------------- */
static char *strip_generics(const char *source, size_t source_len, size_t *new_len)
{
    char *result = emalloc(source_len * 2 + 1); /* Extra space for replacing T with mixed */
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

        /*
         * ---- Detect standalone generic type parameters ----
         *
         * Look for type parameters like "T" or "TModel" that appear in type positions
         * and replace them with "mixed".
         *
         * We detect this by looking for patterns like:
         *   public T $var
         *   private TModel $item
         *   function foo(T $x)
         *   function foo(): T
         *
         * IMPORTANT: We must NOT replace T when it's inside angle brackets (already handled above)
         */
        if (source[i] == 'T') {
            int param_len = is_generic_type_param(source, i, source_len);
            if (param_len > 0) {
                /* Check if this looks like a type position by looking backwards */
                size_t look_back = r;
                while (look_back > 0 && (result[look_back - 1] == ' ' || result[look_back - 1] == '\t')) {
                    look_back--;
                }

                /* Skip if we're inside angle brackets - this would be inside a generic type argument */
                if (look_back > 0 && (result[look_back - 1] == '<' || result[look_back - 1] == ',')) {
                    /* Don't replace - this is inside generic brackets which will be stripped anyway */
                    result[r++] = source[i++];
                    continue;
                }

                /* Check for type declaration contexts */
                int is_type_context = 0;

                /* Case 1: After visibility modifiers */
                if (look_back >= 6 && strncmp(&result[look_back - 6], "public", 6) == 0) {
                    is_type_context = 1;
                }
                if (look_back >= 7 && strncmp(&result[look_back - 7], "private", 7) == 0) {
                    is_type_context = 1;
                }
                if (look_back >= 9 && strncmp(&result[look_back - 9], "protected", 9) == 0) {
                    is_type_context = 1;
                }

                /* Case 2: After opening paren (function parameter) */
                if (look_back > 0 && result[look_back - 1] == '(') {
                    is_type_context = 1;
                }

                /* Case 3: After comma (might be another parameter, but not inside generics) */
                /* We already checked for < and , above, so this is safe */

                /* Case 4: After colon (return type) */
                if (look_back > 0 && result[look_back - 1] == ':' && result[look_back - 2] != ':') {
                    is_type_context = 1;
                }

                /* Case 5: After pipe (union type) - check ahead for |null pattern */
                if (look_back > 0 && result[look_back - 1] == '|') {
                    /* Check if this is T|null - if so, we need special handling */
                    size_t ahead = i + param_len;
                    while (ahead < source_len && (source[ahead] == ' ' || source[ahead] == '\t')) {
                        ahead++;
                    }

                    /* If followed by |null, replace T with nothing (leaving just |null) */
                    if (ahead < source_len && source[ahead] == '|') {
                        /* Skip T entirely - the result will be something|null */
                        i += param_len;
                        continue;
                    }

                    is_type_context = 1;
                }

                if (is_type_context) {
                    /* Check ahead to see if this is followed by |null */
                    size_t ahead = i + param_len;
                    while (ahead < source_len && (source[ahead] == ' ' || source[ahead] == '\t')) {
                        ahead++;
                    }

                    if (ahead + 4 < source_len && source[ahead] == '|' &&
                        strncmp(&source[ahead + 1], "null", 4) == 0) {
                        /* T|null -> just null */
                        const char *nullable = "null";
                        memcpy(&result[r], nullable, 4);
                        r += 4;
                        i += param_len;
                        continue;
                    }

                    /* Replace the generic type parameter with "mixed" */
                    const char *mixed = "mixed";
                    memcpy(&result[r], mixed, 5);
                    r += 5;
                    i += param_len;
                    continue;
                }
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
