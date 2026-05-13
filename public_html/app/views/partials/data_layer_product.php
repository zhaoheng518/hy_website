<?php
/**
 * DataLayer push: product detail page.
 * Included from product_detail.php via require.
 *
 * Required variables (must exist in caller scope):
 *   $product      array   - product row
 *   $categoryName string  - resolved category display name
 *   $lang         string  - current language code
 *
 * Privacy: no PII output. Only structural/meta fields pushed.
 * Compatibility: PHP 7+, no Composer.
 */
$_dl_product_data = [
    'product_id'       => (string) ($product['id'] ?? $product['slug'] ?? ''),
    'product_name'     => (string) ($product['name'] ?? ''),
    'product_category' => (string) ($categoryName ?? ''),
    'product_slug'     => (string) ($product['slug'] ?? ''),
    'product_model'    => (string) ($product['product_model'] ?? ''),
    'page_type'        => 'product',
    'page_lang'        => (string) ($lang ?? 'en'),
];
?>
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push(<?php echo json_encode(
    $_dl_product_data,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE
); ?>);
</script>
