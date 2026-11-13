<?
// Add these lines to the existing /local/php_interface/init.php; do not replace it.
if (gethostname() === 'discount-tools-test') {
    $dtSortHooks = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/dt_catalog_sort/hooks.php';
    if (is_file($dtSortHooks)) {
        require_once $dtSortHooks;
    }
}
