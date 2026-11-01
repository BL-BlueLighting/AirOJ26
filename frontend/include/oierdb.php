<?php
/**
 * OIerDb 奖项数据导入与查询。
 *
 * 数据来源：https://github.com/OIerDb-ng/OIerDb（AGPL-3.0）
 * raw.txt 每行格式：赛事,奖项,姓名,年级,学校,分数,省份,性别,首拼
 */

/** 姓名归一化：去掉所有空白，便于 OIerDb 姓名匹配 */
function oierdb_normalize_name(string $name): string
{
    return (string)preg_replace('/[\s\x{3000}]+/u', '', trim($name));
}

/** 奖项展示文本：赛事 · 奖项 · 学校 */
function oierdb_record_label(array $record): string
{
    $parts = [];
    foreach (['contest', 'award', 'school'] as $key) {
        $v = trim((string)($record[$key] ?? ''));
        if ($v !== '') $parts[] = $v;
    }
    $label = implode(' · ', $parts);
    return mb_substr($label, 0, 240);
}

/** 当前 OIerDb 记录数 */
function oierdb_count(): int
{
    try {
        return (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM oier_awards")['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * 导入 raw.txt（会清空并替换现有数据）。
 * @return array{count:int}
 */
function oierdb_import_file(string $path): array
{
    $pdo = Database::connect();
    $fh = @fopen($path, 'rb');
    if (!$fh) throw new RuntimeException('无法读取 OIerDb 文件');

    $count = 0;
    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM oier_awards");
        $stmt = $pdo->prepare(
            "INSERT INTO oier_awards
             (contest,award,real_name,real_name_key,grade,school,score,province,gender,initials)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $first = true;
        while (($line = fgets($fh)) !== false) {
            if ($first) {
                $line = (string)preg_replace('/^\xEF\xBB\xBF/', '', $line);
                $first = false;
            }
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            $cols = str_getcsv($line, ',', '"', '');
            if (count($cols) < 4) continue;
            $cols = array_pad($cols, 10, '');
            $name = trim((string)$cols[2]);
            if ($name === '') continue;

            $stmt->execute([
                trim((string)$cols[0]),                 // contest
                trim((string)$cols[1]),                 // award
                $name,                                  // real_name
                oierdb_normalize_name($name),           // real_name_key
                trim((string)$cols[3]),                 // grade
                trim((string)$cols[4]),                 // school
                trim((string)$cols[5]),                 // score
                trim((string)$cols[6]),                 // province
                trim((string)$cols[7]),                 // gender
                trim((string)$cols[8]),                 // initials
            ]);
            $count++;
        }
        if ($count === 0) throw new RuntimeException('文件中没有解析到有效的 OIerDb 记录');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fclose($fh);
        throw $e;
    }
    fclose($fh);

    Database::setConfig('oierdb_imported_at', date('Y-m-d H:i:s'));
    Database::setConfig('oierdb_count', (string)$count);
    return ['count' => $count];
}

/**
 * 按实人认证姓名搜索奖项记录（姓名严格匹配）。
 * @return array{rows:array,total:int,page:int,pages:int}
 */
function oierdb_search_by_name(string $realName, string $query = '', int $page = 1, int $limit = 30): array
{
    $key = oierdb_normalize_name($realName);
    if ($key === '') return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];

    $where = 'real_name_key = ?';
    $params = [$key];
    $query = trim($query);
    if ($query !== '') {
        $like = '%' . $query . '%';
        $where .= ' AND (contest LIKE ? OR award LIKE ? OR school LIKE ? OR grade LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }

    $limit = max(1, min(100, $limit));
    $page = max(1, $page);
    $total = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM oier_awards WHERE {$where}", $params)['c'] ?? 0);
    $pages = max(1, (int)ceil($total / $limit));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $limit;

    $rows = Database::fetchAll(
        "SELECT id, contest, award, real_name, grade, school, score, province, gender
         FROM oier_awards WHERE {$where}
         ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
        $params
    );
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
}

/**
 * 实人认证发起时按提交姓名生成候选奖项（不保存姓名本身）。
 */
function oierdb_refresh_candidates(string $userId, string $realName): void
{
    $key = oierdb_normalize_name($realName);
    Database::exec("DELETE FROM frv_award_candidates WHERE user_id=?", [$userId]);
    if ($key === '') return;
    try {
        $rows = Database::fetchAll(
            "SELECT id FROM oier_awards WHERE real_name_key=? ORDER BY id DESC LIMIT 500",
            [$key]
        );
        foreach ($rows as $r) {
            Database::exec(
                "INSERT INTO frv_award_candidates (user_id,award_id) VALUES (?,?)",
                [$userId, (int)$r['id']]
            );
        }
    } catch (Throwable $e) {
        error_log('[AirOJ FRV] refresh award candidates failed: ' . $e->getMessage());
    }
}

/** 当前用户的候选奖项（按实人认证时的姓名匹配生成） */
function oierdb_candidates_by_user(string $userId, string $query = '', int $page = 1, int $limit = 30): array
{
    if ($userId === '') return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
    $where = 'c.user_id = ?';
    $params = [$userId];
    $query = trim($query);
    if ($query !== '') {
        $like = '%' . $query . '%';
        $where .= ' AND (a.contest LIKE ? OR a.award LIKE ? OR a.school LIKE ? OR a.grade LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }

    $limit = max(1, min(100, $limit));
    $page = max(1, $page);
    $total = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM frv_award_candidates c JOIN oier_awards a ON a.id=c.award_id WHERE {$where}",
        $params
    )['c'] ?? 0);
    $pages = max(1, (int)ceil($total / $limit));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $limit;

    $rows = Database::fetchAll(
        "SELECT a.id, a.contest, a.award, a.real_name, a.grade, a.school, a.score, a.province, a.gender
         FROM frv_award_candidates c JOIN oier_awards a ON a.id=c.award_id
         WHERE {$where} ORDER BY a.id DESC LIMIT {$limit} OFFSET {$offset}",
        $params
    );
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
}

/** 校验某条奖项是否属于该用户的实人认证匹配结果 */
function oierdb_candidate_owned(string $userId, int $awardId): ?array
{
    if ($userId === '' || $awardId <= 0) return null;
    return Database::fetchOne(
        "SELECT a.* FROM frv_award_candidates c JOIN oier_awards a ON a.id=c.award_id
         WHERE c.user_id=? AND c.award_id=?",
        [$userId, $awardId]
    );
}
