<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;

class ProductRepository extends BaseRepository
{
    protected string $table = 'products';
    protected string $primaryKey = 'id';

    private array $supportedLangs = ['en', 'cn', 'es'];
    private string $defaultLang = 'en';

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function setSupportedLangs(array $langs): void
    {
        $this->supportedLangs = $langs;
    }

    public function setDefaultLang(string $lang): void
    {
        $this->defaultLang = $lang;
    }

    public function getDefaultLang(): string
    {
        return $this->defaultLang;
    }

    /**
     * 使用 JOIN 查询获取完整的产品数据（含指定语言的翻译）
     * 核心多语言查询方法：通过一条 SQL 同时查询主表 products 和 product_translations
     */
    public function getProductBySlug(string $slug, string $lang): ?array
    {
        $lang = in_array($lang, $this->supportedLangs, true) ? $lang : $this->defaultLang;

        $sql = <<<SQL
            SELECT
                p.id,
                p.slug,
                p.category_id,
                p.images,
                p.specs,
                p.datasheet,
                p.sort_order,
                p.is_active,
                p.view_count,
                p.created_at,
                p.updated_at,
                pt.name,
                pt.desc AS description,
                pt.short_desc,
                pt.content,
                pt.seo_title,
                pt.seo_desc,
                pt.lang AS translation_lang,
                c.slug AS category_slug,
                c.parent_id
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.lang = :lang
            WHERE p.slug = :slug
            LIMIT 1
        SQL;

        $product = $this->db->fetch($sql, [
            'slug' => $slug,
            'lang' => $lang,
        ]);

        if ($product === null) {
            return null;
        }

        $product['images'] = $this->decodeJson($product['images'] ?? null);
        $product['specs'] = $this->decodeJson($product['specs'] ?? null);

        if ($product['translation_lang'] === null) {
            return $this->getProductFallback($slug, $lang);
        }

        return $product;
    }

    /**
     * 获取产品详情（后台管理用，含所有语言翻译）
     */
    public function getProductWithAllTranslations(int $id): ?array
    {
        $sql = <<<SQL
            SELECT
                p.id AS p_id,
                p.slug,
                p.category_id,
                p.images,
                p.specs,
                p.datasheet,
                p.sort_order,
                p.is_active,
                p.view_count,
                p.created_at,
                p.updated_at,
                pt.name,
                pt.desc AS description,
                pt.short_desc,
                pt.content,
                pt.seo_title,
                pt.seo_desc,
                pt.lang AS translation_lang
            FROM products p
            LEFT JOIN product_translations pt ON p.id = pt.product_id
            WHERE p.id = :id
        SQL;

        $results = $this->db->fetchAll($sql, ['id' => $id]);

        if (empty($results)) {
            return null;
        }

        $mainData = null;
        $translations = [];

        foreach ($results as $row) {
            if ($mainData === null) {
                $mainData = [
                    'id'          => $row['p_id'],
                    'slug'        => $row['slug'],
                    'category_id' => $row['category_id'],
                    'images'      => $this->decodeJson($row['images'] ?? null),
                    'specs'       => $this->decodeJson($row['specs'] ?? null),
                    'datasheet'   => $row['datasheet'],
                    'sort_order'  => $row['sort_order'],
                    'is_active'   => $row['is_active'],
                    'view_count'  => $row['view_count'],
                    'created_at'  => $row['created_at'],
                    'updated_at'  => $row['updated_at'],
                ];
            }

            if ($row['translation_lang'] !== null) {
                $translations[$row['translation_lang']] = [
                    'name'       => $row['name'],
                    'desc'       => $row['description'],
                    'short_desc' => $row['short_desc'],
                    'content'    => $row['content'],
                    'seo_title'  => $row['seo_title'],
                    'seo_desc'   => $row['seo_desc'],
                ];
            }
        }

        $mainData['translations'] = $translations;
        return $mainData;
    }

    /**
     * 获取产品列表（支持多语言）
     */
    public function getProducts(array $options = []): array
    {
        $lang = $options['lang'] ?? $this->defaultLang;
        $categoryId = $options['category_id'] ?? null;
        $isActive = $options['is_active'] ?? true;
        $orderBy = $options['order_by'] ?? 'p.sort_order ASC, p.id DESC';
        $limit = (int) ($options['limit'] ?? 0);
        $offset = (int) ($options['offset'] ?? 0);

        if (!in_array($lang, $this->supportedLangs, true)) {
            $lang = $this->defaultLang;
        }

        $sql = <<<SQL
            SELECT
                p.id,
                p.slug,
                p.category_id,
                p.images,
                p.specs,
                p.sort_order,
                p.is_active,
                p.view_count,
                p.created_at,
                pt.name,
                pt.short_desc,
                pt.seo_title,
                pt.seo_desc,
                c.slug AS category_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.lang = :lang
            WHERE 1=1
        SQL;

        $params = ['lang' => $lang];

        if ($categoryId !== null) {
            $sql .= " AND p.category_id = :category_id";
            $params['category_id'] = $categoryId;
        }

        if ($isActive !== null) {
            $sql .= " AND p.is_active = :is_active";
            $params['is_active'] = (int) $isActive;
        }

        $sql .= " ORDER BY {$orderBy}";

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $products = $this->db->fetchAll($sql, $params);

        foreach ($products as &$product) {
            $product['images'] = $this->decodeJson($product['images'] ?? null);
            $product['specs'] = $this->decodeJson($product['specs'] ?? null);
        }

        return $products;
    }

    /**
     * 计算产品总数
     */
    public function countProducts(array $conditions = []): int
    {
        $where = 'WHERE 1=1';
        $params = [];

        if (isset($conditions['category_id'])) {
            $where .= ' AND p.category_id = :category_id';
            $params['category_id'] = $conditions['category_id'];
        }

        if (isset($conditions['is_active'])) {
            $where .= ' AND p.is_active = :is_active';
            $params['is_active'] = (int) $conditions['is_active'];
        }

        if (isset($conditions['lang'])) {
            $where .= ' AND pt.lang = :lang';
            $params['lang'] = $conditions['lang'];
        }

        $sql = "SELECT COUNT(*) as cnt
                FROM products p
                LEFT JOIN product_translations pt ON p.id = pt.product_id
                {$where}";

        $result = $this->db->fetch($sql, $params);
        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * 使用 PDO 事务创建产品（含所有语言翻译）
     * 步骤：1. 开启事务 → 2. 插入主表 products → 3. 循环插入翻译表 product_translations
     *       → 4. 提交事务；若任何一步失败则回滚
     */
    public function createProduct(array $globalData, array $translations): int
    {
        $this->beginTransaction();

        try {
            $productId = $this->create($globalData);

            if ($productId <= 0) {
                throw new \RuntimeException("插入产品主表失败，未获取到有效ID");
            }

            foreach ($translations as $lang => $translation) {
                $this->insertTranslation($productId, $lang, $translation);
            }

            $this->commit();
            return $productId;

        } catch (\Throwable $e) {
            $this->rollback();
            throw new \RuntimeException(
                "创建产品失败，已回滚事务。错误详情: " . $e->getMessage(),
                500,
                $e
            );
        }
    }

    /**
     * 使用 PDO 事务更新产品
     */
    public function updateProduct(int $id, array $globalData, array $translations): bool
    {
        $this->beginTransaction();

        try {
            $updated = $this->update($id, $globalData);

            if ($updated === false) {
                throw new \RuntimeException("更新产品主表失败");
            }

            foreach ($translations as $lang => $translation) {
                $this->upsertTranslation($id, $lang, $translation);
            }

            $this->commit();
            return true;

        } catch (\Throwable $e) {
            $this->rollback();
            throw new \RuntimeException(
                "更新产品失败，已回滚事务。错误详情: " . $e->getMessage(),
                500,
                $e
            );
        }
    }

    /**
     * 删除产品（依赖外键级联删除翻译记录）
     */
    public function deleteProduct(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * 检查 slug 是否存在
     */
    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $sql = "SELECT 1 FROM products WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($excludeId > 0) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        return $this->db->fetch($sql, $params) !== null;
    }

    /**
     * 增加浏览次数
     */
    public function incrementViewCount(int $id): void
    {
        $sql = "UPDATE products SET view_count = view_count + 1 WHERE id = :id";
        $this->db->execute($sql, ['id' => $id]);
    }

    /**
     * 批量获取所有产品的 slug 映射
     */
    public function getAllSlugs(): array
    {
        $sql = "SELECT id, slug FROM products ORDER BY id ASC";
        $results = $this->db->fetchAll($sql);
        $map = [];
        foreach ($results as $row) {
            $map[$row['slug']] = (int) $row['id'];
        }
        return $map;
    }

    /**
     * 插入单条翻译记录
     */
    private function insertTranslation(int $productId, string $lang, array $data): int
    {
        $sql = <<<SQL
            INSERT INTO product_translations
            (product_id, lang, name, `desc`, short_desc, content, seo_title, seo_desc)
            VALUES
            (:product_id, :lang, :name, :desc, :short_desc, :content, :seo_title, :seo_desc)
        SQL;

        return $this->db->insert($sql, [
            'product_id' => $productId,
            'lang'       => $lang,
            'name'       => $data['name'] ?? '',
            'desc'       => $data['desc'] ?? '',
            'short_desc' => $data['short_desc'] ?? '',
            'content'    => $data['content'] ?? '',
            'seo_title'  => $data['seo_title'] ?? '',
            'seo_desc'   => $data['seo_desc'] ?? '',
        ]);
    }

    /**
     * 插入或更新翻译记录（Upsert）
     */
    private function upsertTranslation(int $productId, string $lang, array $data): void
    {
        $exists = $this->db->fetch(
            "SELECT id FROM product_translations WHERE product_id = :product_id AND lang = :lang",
            ['product_id' => $productId, 'lang' => $lang]
        );

        if ($exists) {
            $sql = <<<SQL
                UPDATE product_translations SET
                    name = :name,
                    `desc` = :desc,
                    short_desc = :short_desc,
                    content = :content,
                    seo_title = :seo_title,
                    seo_desc = :seo_desc
                WHERE product_id = :product_id AND lang = :lang
            SQL;
        } else {
            $sql = <<<SQL
                INSERT INTO product_translations
                (product_id, lang, name, `desc`, short_desc, content, seo_title, seo_desc)
                VALUES
                (:product_id, :lang, :name, :desc, :short_desc, :content, :seo_title, :seo_desc)
            SQL;
        }

        $this->db->execute($sql, [
            'product_id' => $productId,
            'lang'       => $lang,
            'name'       => $data['name'] ?? '',
            'desc'       => $data['desc'] ?? '',
            'short_desc' => $data['short_desc'] ?? '',
            'content'    => $data['content'] ?? '',
            'seo_title'  => $data['seo_title'] ?? '',
            'seo_desc'   => $data['seo_desc'] ?? '',
        ]);
    }

    /**
     * 获取某产品的所有翻译
     */
    public function getTranslations(int $productId): array
    {
        $sql = "SELECT * FROM product_translations WHERE product_id = :product_id";
        return $this->db->fetchAll($sql, ['product_id' => $productId]);
    }

    /**
     * 获取某产品指定语言的翻译
     */
    public function getTranslation(int $productId, string $lang): ?array
    {
        $sql = "SELECT * FROM product_translations WHERE product_id = :product_id AND lang = :lang LIMIT 1";
        return $this->db->fetch($sql, ['product_id' => $productId, 'lang' => $lang]);
    }

    /**
     * 无翻译时回退到默认语言
     */
    private function getProductFallback(string $slug, string $lang): ?array
    {
        if ($lang === $this->defaultLang) {
            return null;
        }
        return $this->getProductBySlug($slug, $this->defaultLang);
    }

    /**
     * 安全解析 JSON 字段
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '' || $value === 'null') {
            return [];
        }
        if (!is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 验证产品数据完整性
     */
    public function validateProductData(array $data, array $translations): ?string
    {
        if (empty($data['slug'])) {
            return "产品slug不能为空";
        }
        if (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
            return "slug只能包含小写字母、数字和连字符";
        }
        if ($this->slugExists($data['slug'])) {
            return "该slug已存在: " . $data['slug'];
        }
        foreach ($translations as $lang => $translation) {
            if (empty($translation['name'])) {
                return "语言[{$lang}]的产品名称不能为空";
            }
        }
        return null;
    }

    /**
     * 生成唯一的 slug
     */
    public function generateUniqueSlug(string $baseSlug, int $excludeId = 0): string
    {
        $slug = $baseSlug;
        $counter = 1;
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        return $slug;
    }
}
