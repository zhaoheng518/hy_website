<?php

namespace App\Core;

/**
 * JSON 产品发布状态（与后台 product_status 一致）。
 */
final class ProductPublishState
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DRAFT = 'draft';

    public static function normalize(?string $raw): string
    {
        $s = strtolower(trim((string) $raw));
        if ($s === self::STATUS_DRAFT) {
            return self::STATUS_DRAFT;
        }

        return self::STATUS_PUBLISHED;
    }

    /** 前台列表/详情/sitemap 等：仅展示已发布 */
    public static function isPublicVisible(array $product): bool
    {
        return self::normalize($product['status'] ?? self::STATUS_PUBLISHED) !== self::STATUS_DRAFT;
    }
}
