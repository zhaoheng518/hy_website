<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;

class CategoryRepository extends BaseRepository
{
    protected string $table = 'categories';
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

    public function getCategories(array $options = []): array
    {
        $lang = $options['lang'] ?? $this->defaultLang;
        $parentId = $options['parent_id'] ?? null;
        $isActive = $options['is_active'] ?? true;
        $orderBy = $options['order_by'] ?? 'c.sort_order ASC, c.id DESC';
        $limit = (int) ($options['limit'] ?? 0);
        $offset = (int) ($options['offset'] ?? 0);

        if (!in_array($lang, $this->supportedLangs, true)) {
            $lang = $this->defaultLang;
        }

        $sql = <<<SQL
            SELECT
                c.id,
                c.slug,
                c.parent_id,
                c.sort_order,
                c.is_active,
                c.created_at,
                c.updated_at,
                ct.name,
                ct.description,
                ct.meta_title,
                ct.meta_description,
                ct.lang AS translation_lang
            FROM categories c
            LEFT JOIN category_translations ct ON c.id = ct.category_id AND ct.lang = :lang
            WHERE 1=1
        SQL;

        $params = ['lang' => $lang];

        if ($parentId !== null) {
            $sql .= " AND c.parent_id = :parent_id";
            $params['parent_id'] = $parentId;
        }

        if ($isActive !== null) {
            $sql .= " AND c.is_active = :is_active";
            $params['is_active'] = (int) $isActive;
        }

        $sql .= " ORDER BY {$orderBy}";

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        return $this->db->fetchAll($sql, $params);
    }

    public function getCategoryBySlug(string $slug, string $lang): ?array
    {
        $lang = in_array($lang, $this->supportedLangs, true) ? $lang : $this->defaultLang;

        $sql = <<<SQL
            SELECT
                c.id,
                c.slug,
                c.parent_id,
                c.sort_order,
                c.is_active,
                c.created_at,
                c.updated_at,
                ct.name,
                ct.description,
                ct.meta_title,
                ct.meta_description,
                ct.lang AS translation_lang
            FROM categories c
            LEFT JOIN category_translations ct ON c.id = ct.category_id AND ct.lang = :lang
            WHERE c.slug = :slug
            LIMIT 1
        SQL;

        return $this->db->fetch($sql, ['slug' => $slug, 'lang' => $lang]);
    }

    public function getCategoryWithAllTranslations(int $id): ?array
    {
        $sql = "SELECT c.*, ct.name, ct.description, ct.meta_title, ct.meta_description, ct.lang AS translation_lang
                FROM categories c
                LEFT JOIN category_translations ct ON c.id = ct.category_id
                WHERE c.id = :id";

        $results = $this->db->fetchAll($sql, ['id' => $id]);

        if (empty($results)) {
            return null;
        }

        $mainData = null;
        $translations = [];

        foreach ($results as $row) {
            if ($mainData === null) {
                $mainData = [
                    'id' => $row['id'],
                    'slug' => $row['slug'],
                    'parent_id' => $row['parent_id'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => $row['is_active'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ];
            }

            if ($row['translation_lang'] !== null) {
                $translations[$row['translation_lang']] = [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'meta_title' => $row['meta_title'],
                    'meta_description' => $row['meta_description'],
                ];
            }
        }

        $mainData['translations'] = $translations;
        return $mainData;
    }

    public function countCategories(array $conditions = []): int
    {
        $where = 'WHERE 1=1';
        $params = [];

        if (isset($conditions['parent_id'])) {
            $where .= ' AND parent_id = :parent_id';
            $params['parent_id'] = $conditions['parent_id'];
        }

        if (isset($conditions['is_active'])) {
            $where .= ' AND is_active = :is_active';
            $params['is_active'] = (int) $conditions['is_active'];
        }

        $sql = "SELECT COUNT(*) as cnt FROM categories {$where}";
        $result = $this->db->fetch($sql, $params);
        return (int) ($result['cnt'] ?? 0);
    }

    public function createCategory(array $globalData, array $translations): int
    {
        $this->beginTransaction();

        try {
            $categoryId = $this->create($globalData);

            if ($categoryId <= 0) {
                throw new \RuntimeException("插入分类主表失败");
            }

            foreach ($translations as $lang => $translation) {
                $this->insertTranslation($categoryId, $lang, $translation);
            }

            $this->commit();
            return $categoryId;

        } catch (\Throwable $e) {
            $this->rollback();
            throw new \RuntimeException(
                "创建分类失败，已回滚事务。错误详情: " . $e->getMessage(),
                500,
                $e
            );
        }
    }

    public function updateCategory(int $id, array $globalData, array $translations): bool
    {
        $this->beginTransaction();

        try {
            $updated = $this->update($id, $globalData);

            if ($updated === false) {
                throw new \RuntimeException("更新分类主表失败");
            }

            foreach ($translations as $lang => $translation) {
                $this->upsertTranslation($id, $lang, $translation);
            }

            $this->commit();
            return true;

        } catch (\Throwable $e) {
            $this->rollback();
            throw new \RuntimeException(
                "更新分类失败，已回滚事务。错误详情: " . $e->getMessage(),
                500,
                $e
            );
        }
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $sql = "SELECT 1 FROM categories WHERE slug = :slug";
        $params = ['slug' => $slug];

        if ($excludeId > 0) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        return $this->db->fetch($sql, $params) !== null;
    }

    private function insertTranslation(int $categoryId, string $lang, array $data): int
    {
        $sql = <<<SQL
            INSERT INTO category_translations
            (category_id, lang, name, description, meta_title, meta_description)
            VALUES
            (:category_id, :lang, :name, :description, :meta_title, :meta_description)
        SQL;

        return $this->db->insert($sql, [
            'category_id' => $categoryId,
            'lang' => $lang,
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'meta_title' => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
        ]);
    }

    private function upsertTranslation(int $categoryId, string $lang, array $data): void
    {
        $exists = $this->db->fetch(
            "SELECT id FROM category_translations WHERE category_id = :category_id AND lang = :lang",
            ['category_id' => $categoryId, 'lang' => $lang]
        );

        if ($exists) {
            $sql = <<<SQL
                UPDATE category_translations SET
                    name = :name,
                    description = :description,
                    meta_title = :meta_title,
                    meta_description = :meta_description
                WHERE category_id = :category_id AND lang = :lang
            SQL;
        } else {
            $sql = <<<SQL
                INSERT INTO category_translations
                (category_id, lang, name, description, meta_title, meta_description)
                VALUES
                (:category_id, :lang, :name, :description, :meta_title, :meta_description)
            SQL;
        }

        $this->db->execute($sql, [
            'category_id' => $categoryId,
            'lang' => $lang,
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'meta_title' => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
        ]);
    }

    public function getTranslations(int $categoryId): array
    {
        $sql = "SELECT * FROM category_translations WHERE category_id = :category_id";
        return $this->db->fetchAll($sql, ['category_id' => $categoryId]);
    }

    public function getAllCategoriesFlat(string $lang = 'en'): array
    {
        $sql = <<<SQL
            SELECT c.id, c.slug, c.parent_id, ct.name
            FROM categories c
            LEFT JOIN category_translations ct ON c.id = ct.category_id AND ct.lang = :lang
            WHERE c.is_active = 1
            ORDER BY c.sort_order ASC, c.id ASC
        SQL;

        return $this->db->fetchAll($sql, ['lang' => $lang]);
    }

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
