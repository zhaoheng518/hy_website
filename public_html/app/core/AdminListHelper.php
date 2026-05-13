<?php

namespace App\Core;

/**
 * In-memory search / filter / sort / pagination for JSON-backed admin lists.
 */
final class AdminListHelper
{
    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array{0:array<int,array>,1:int} filtered rows and total count before slice
     */
    public static function process(
        array $rows,
        array $opts = []
    ): array {
        $q = mb_strtolower(trim($opts['q'] ?? ''));
        $status = trim($opts['status'] ?? '');
        $dateFrom = trim($opts['date_from'] ?? '');
        $dateTo = trim($opts['date_to'] ?? '');
        $sort = trim($opts['sort'] ?? 'desc');
        $page = max(1, (int) ($opts['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($opts['per_page'] ?? 20)));
        $searchKeys = $opts['search_keys'] ?? ['title', 'slug', 'name', 'email', 'message'];

        $filtered = array_values(array_filter($rows, function ($row) use ($q, $status, $dateFrom, $dateTo, $searchKeys) {
            if ($status !== '') {
                $st = (string) ($row['status'] ?? 'published');
                if ($st !== $status) {
                    return false;
                }
            }
            if ($dateFrom !== '' || $dateTo !== '') {
                $d = (string) ($row['created_at'] ?? $row['date'] ?? $row['published_at'] ?? '');
                if ($d === '') {
                    return false;
                }
                $ts = strtotime($d) ?: 0;
                if ($dateFrom !== '') {
                    $f = strtotime($dateFrom . ' 00:00:00') ?: 0;
                    if ($ts < $f) {
                        return false;
                    }
                }
                if ($dateTo !== '') {
                    $t = strtotime($dateTo . ' 23:59:59') ?: 0;
                    if ($ts > $t) {
                        return false;
                    }
                }
            }
            if ($q === '') {
                return true;
            }
            foreach ($searchKeys as $key) {
                if (!isset($row[$key])) {
                    continue;
                }
                $val = mb_strtolower((string) $row[$key]);
                if ($val !== '' && mb_strpos($val, $q) !== false) {
                    return true;
                }
            }
            return false;
        }));

        $total = count($filtered);

        usort($filtered, function ($a, $b) use ($sort) {
            $da = (string) ($a['published_at'] ?? $a['date'] ?? $a['created_at'] ?? $a['updated_at'] ?? '');
            $db = (string) ($b['published_at'] ?? $b['date'] ?? $b['created_at'] ?? $b['updated_at'] ?? '');
            $ta = strtotime($da) ?: 0;
            $tb = strtotime($db) ?: 0;
            if ($sort === 'asc') {
                return $ta <=> $tb;
            }
            return $tb <=> $ta;
        });

        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($filtered, $offset, $perPage);

        return [$pageRows, $total];
    }
}
