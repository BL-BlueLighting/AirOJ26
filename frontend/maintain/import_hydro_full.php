<?php
/**
 * HydroOJ 全量数据迁移到 AirOJ
 *
 * 用法：
 *   php maintain/import_hydro_full.php /tmp/hydro_full
 *
 * 目录中需要以下 mongoexport 文件：
 *   user.json domain.json document.json record.json message.json badge.json userBadge.json storage.json
 *
 * 迁移内容：用户资料 / 头像 / 背景 / 2FA、题目映射、公告、讨论、站内信、勋章、提交与判题结果。
 * 账号密码本身由 import_hydro_users.php 负责；本脚本对已存在账号不会改密码。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';

$dir = rtrim($argv[1] ?? '/tmp/hydro_full', '/');
$uploads = dirname(__DIR__) . '/data/uploads/';
if (!is_dir($uploads)) @mkdir($uploads, 0755, true);

function hydro_load(string $dir, string $name): array
{
    $path = $dir . '/' . $name;
    if (!is_file($path)) return [];
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function hydro_oid_string($v): string
{
    if (is_array($v) && isset($v['$oid'])) return (string)$v['$oid'];
    return is_string($v) ? $v : '';
}

function hydro_oid_timestamp($v): int
{
    $s = hydro_oid_string($v);
    if (preg_match('/^[0-9a-f]{24}$/i', $s)) return (int)hexdec(substr($s, 0, 8));
    return 0;
}

function hydro_date($v, bool $fallbackNow = true): string
{
    $iso = '';
    if (is_array($v)) {
        if (isset($v['$date'])) {
            $d = $v['$date'];
            if (is_array($d) && isset($d['$numberLong'])) {
                return date('Y-m-d H:i:s', (int)floor(((int)$d['$numberLong']) / 1000));
            }
            $iso = is_string($d) ? $d : '';
        } elseif (isset($v['$numberLong'])) {
            return date('Y-m-d H:i:s', (int)floor(((int)$v['$numberLong']) / 1000));
        }
    } elseif (is_string($v)) {
        $iso = $v;
    }
    if ($iso !== '') {
        $ts = strtotime($iso);
        if ($ts !== false) return date('Y-m-d H:i:s', $ts);
    }
    return $fallbackNow ? date('Y-m-d H:i:s') : '';
}

function hydro_doc_date(array $doc, string $field = ''): string
{
    if ($field !== '') {
        $d = hydro_date($doc[$field] ?? null, false);
        if ($d !== '') return $d;
    }
    $ts = hydro_oid_timestamp($doc['_id'] ?? null);
    if ($ts > 0) return date('Y-m-d H:i:s', $ts);
    return date('Y-m-d H:i:s');
}

function hydro_map_lang(string $lang): string
{
    $l = strtolower(trim($lang));
    if ($l === 'c') return 'c';
    if (str_starts_with($l, 'cc') || str_starts_with($l, 'cpp')) return 'cpp';
    if (str_starts_with($l, 'py')) return 'python3';
    if (str_starts_with($l, 'java')) return 'java';
    if ($l === 'go' || str_starts_with($l, 'go.')) return 'go';
    if (str_contains($l, 'node') || $l === 'js' || $l === 'javascript') return 'javascript';
    return $l !== '' ? $l : 'cpp';
}

function db_insert_ignore(string $table, array $data): void
{
    if (!$data) return;
    $cols = array_keys($data);
    $ph = implode(',', array_fill(0, count($cols), '?'));
    $ignore = DB_DRIVER === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
    Database::exec(
        "$ignore INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES ($ph)",
        array_values($data)
    );
}

function update_user_fields(string $uid, array $fields): void
{
    $allowed = ['nick', 'email', 'bio', 'school', 'avatar', 'background', 'twofa_secret', 'twofa_enabled', 'title', 'equipped_badge', 'hydro_salt', 'hydro_hash', 'must_change_password'];
    $sets = [];
    $params = [];
    foreach ($fields as $k => $v) {
        if (!in_array($k, $allowed, true)) continue;
        $sets[] = "`$k`=?";
        $params[] = $v;
    }
    if (!$sets) return;
    $params[] = $uid;
    Database::exec("UPDATE users SET " . implode(',', $sets) . " WHERE user_id=?", $params);
}

function copy_to_uploads(string $src, string $prefix): ?string
{
    global $uploads;
    if (!is_file($src) || !is_readable($src)) return null;
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'], true)) $ext = 'jpg';
    $safe = preg_replace('/[^a-z0-9_.-]/i', '_', $prefix);
    $name = $safe . '.' . $ext;
    if (!@copy($src, $uploads . $name)) return null;
    return 'data/uploads/' . $name;
}

function storage_path_from_url(string $url): string
{
    $p = ltrim($url, '/');
    if (str_starts_with($p, 'file/')) $p = 'user/' . substr($p, 5);
    return $p;
}

function hydro_avatar(array $u, array $storageByPath): string
{
    $a = trim((string)($u['avatar'] ?? ''));
    if ($a === '') return '';
    if (str_starts_with($a, 'gravatar:')) return '';
    if (str_starts_with($a, 'qq:')) {
        $qq = preg_replace('/\D/', '', substr($a, 3));
        return $qq !== '' ? "https://q1.qlogo.cn/g?b=qq&nk={$qq}&s=200" : '';
    }
    if (str_starts_with($a, 'url:')) {
        $url = substr($a, 4);
        if (preg_match('~^https?://~i', $url)) return $url;
        if (str_starts_with($url, '/file/')) {
            $src = $storageByPath[storage_path_from_url($url)] ?? '';
            if ($src !== '') return copy_to_uploads($src, 'hydro-avatar-' . (string)($u['_id'] ?? 'x')) ?: '';
        }
        return '';
    }
    if (preg_match('~^https?://~i', $a)) return $a;
    return '';
}

function hydro_background(array $u, array $storageByPath, string $dir): string
{
    $bg = trim((string)($u['backgroundImage'] ?? ''));
    if ($bg === '') return '';
    if (preg_match('~^https?://~i', $bg)) return is_valid_background($bg) ? $bg : '';
    if (str_starts_with($bg, '/components/profile/backgrounds/')) {
        $src = $dir . '/files/backgrounds/' . basename($bg);
        return is_file($src) ? (copy_to_uploads($src, 'hydro-bg-' . (string)($u['_id'] ?? 'x')) ?: '') : '';
    }
    if (str_starts_with($bg, '/file/')) {
        $src = $storageByPath[storage_path_from_url($bg)] ?? '';
        return $src !== '' ? (copy_to_uploads($src, 'hydro-bg-' . (string)($u['_id'] ?? 'x')) ?: '') : '';
    }
    return is_valid_background($bg) ? $bg : '';
}

/* ───────────────────────── 读取数据 ───────────────────────── */
$usersDoc     = hydro_load($dir, 'user.json');
$domainsDoc   = hydro_load($dir, 'domain.json');
$documentsDoc = hydro_load($dir, 'document.json');
$recordsDoc   = hydro_load($dir, 'record.json');
$messagesDoc  = hydro_load($dir, 'message.json');
$badgesDoc    = hydro_load($dir, 'badge.json');
$userBadgesDoc = hydro_load($dir, 'userBadge.json');
$storageDoc   = hydro_load($dir, 'storage.json');

/* ───────────────────────── 用户 ───────────────────────── */
$hydroUserByUid = [];
$unameByUid = [];
foreach ($usersDoc as $u) {
    $uid = (string)($u['_id'] ?? '');
    if ($uid === '') continue;
    $hydroUserByUid[$uid] = $u;
    $unameByUid[$uid] = trim((string)($u['uname'] ?? ''));
}

$storageByPath = [];
foreach ($storageDoc as $s) {
    $path = (string)($s['path'] ?? '');
    $etag = (string)($s['etag'] ?? '');
    $phys = $etag !== '' ? base64_decode($etag, true) : false;
    if ($path !== '' && $phys && is_file($phys)) $storageByPath[$path] = $phys;
}

$skipNames = ['Guest', 'Hydro'];
$usersCreated = 0; $usersUpdated = 0; $usersSkipped = 0;
foreach ($hydroUserByUid as $uid => $u) {
    $uname = trim((string)($u['uname'] ?? ''));
    if ($uname === '' || in_array($uname, $skipNames, true)) { $usersSkipped++; continue; }

    $mail   = trim((string)($u['mail'] ?? ''));
    $bio    = trim((string)($u['bio'] ?? ''));
    $school = trim((string)($u['school'] ?? ''));
    $tfa    = isset($u['tfa']) && is_string($u['tfa']) ? trim($u['tfa']) : '';
    $avatar = hydro_avatar($u, $storageByPath);
    $bg     = hydro_background($u, $storageByPath, $dir);

    $air = Database::fetchOne("SELECT * FROM users WHERE user_id=?", [$uname]);
    if (!$air) {
        $reg = hydro_date($u['regat'] ?? null);
        Database::insert(
            "INSERT INTO users (user_id,password,email,nick,school,bio,avatar,background,reg_time,hydro_salt,hydro_hash,must_change_password)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,1)",
            [$uname, '', $mail, $uname, $school, $bio, $avatar, $bg, $reg,
             (string)($u['salt'] ?? ''), (string)($u['hash'] ?? '')]
        );
        $usersCreated++;
        continue;
    }

    $fields = [];
    if ($mail !== '' && trim((string)($air['email'] ?? '')) === '') $fields['email'] = $mail;
    if ($bio !== '' && trim((string)($air['bio'] ?? '')) === '') $fields['bio'] = $bio;
    if ($school !== '' && trim((string)($air['school'] ?? '')) === '') $fields['school'] = $school;
    if (trim((string)($air['nick'] ?? '')) === '') $fields['nick'] = $uname;
    if ($avatar !== '' && trim((string)($air['avatar'] ?? '')) === '') $fields['avatar'] = $avatar;
    if ($bg !== '' && trim((string)($air['background'] ?? '')) === '') $fields['background'] = $bg;
    if ($tfa !== '' && empty($air['twofa_enabled'])) {
        $fields['twofa_secret'] = $tfa;
        $fields['twofa_enabled'] = 1;
    }
    // 已有账号若尚未迁移过密码，则补上 Hydro 登录凭据
    if (trim((string)($air['password'] ?? '')) === '' && empty($air['hydro_hash'])
        && !empty($u['salt']) && !empty($u['hash'])) {
        $fields['hydro_salt'] = (string)$u['salt'];
        $fields['hydro_hash'] = (string)$u['hash'];
        $fields['must_change_password'] = 1;
    }
    if ($fields) { update_user_fields($uname, $fields); $usersUpdated++; }
}

/* ───────────────────────── 题目映射 / 补充导入 ───────────────────────── */
$diffMap = [
    1  => ['BASIC', 1, 'low'],
    2  => ['BASIC', 2, 'low'],
    3  => ['BASIC', 3, 'mid'],
    4  => ['BASIC', 5, 'mid'],
    5  => ['ADVANCED', 6, 'low'],
    6  => ['ADVANCED', 8, 'mid'],
    7  => ['ADVANCED', 10, 'mid'],
    8  => ['EXPERT', 11, 'mid'],
    9  => ['MASTER', 13, 'low'],
    10 => ['Re:MASTER', 15, 'mid'],
];
$problemMap = [];
$problemsCreated = 0;
foreach ($documentsDoc as $p) {
    if ((int)($p['docType'] ?? 0) !== 10) continue;
    $title = trim((string)($p['title'] ?? ''));
    if ($title === '') continue;
    $docId = (string)($p['docId'] ?? '');
    if ($docId === '') continue;

    $matches = Database::fetchAll("SELECT problem_id FROM problem WHERE title=? ORDER BY problem_id", [$title]);
    $airPid = 0;
    foreach ($matches as $m) {
        if ((int)$m['problem_id'] >= 1005) { $airPid = (int)$m['problem_id']; break; }
    }
    if (!$airPid && $matches) $airPid = (int)$matches[0]['problem_id'];

    if (!$airPid) {
        $raw = (string)($p['content'] ?? '');
        $langs = json_decode($raw, true);
        if (is_array($langs)) {
            $content = (string)($langs['zh'] ?? $langs['en'] ?? reset($langs));
        } else {
            $content = $raw;
        }
        $tags = normalize_tags(implode(',', $p['tag'] ?? []));
        $author = $unameByUid[(string)($p['owner'] ?? '')] ?? '';
        $defunct = !empty($p['hidden']) ? 'Y' : 'N';
        $airPid = Database::insert(
            "INSERT INTO problem (title,description,input,output,sample_input,sample_output,hint,tags,author,group_id,spj,defunct)
             VALUES (?,?,?,?,?,?,?,?,?,0,'0',?)",
            [$title, $content, '', '', '', '', '', $tags, $author, $defunct]
        );
        $m = $diffMap[(int)($p['difficulty'] ?? 0)] ?? null;
        if ($m) {
            Database::exec("INSERT INTO problem_difficulty (problem_id,category,level,position) VALUES (?,?,?,?)",
                [$airPid, $m[0], $m[1], $m[2]]);
        }
        $dataDir = INPUTS_DIR . '/' . $airPid;
        if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
        foreach ($p['data'] ?? [] as $f) {
            $src = base64_decode((string)($f['etag'] ?? ''), true);
            if ($src === false || !is_file($src)) continue;
            $name = basename((string)($f['name'] ?? basename($src)));
            if ($name === '' || str_contains($name, '..')) continue;
            @copy($src, $dataDir . '/' . $name);
        }
        $problemsCreated++;
    }
    $problemMap[$docId] = $airPid;
}

/* ───────────────────────── 公告 ───────────────────────── */
$newsCount = 0;
foreach ($domainsDoc as $d) {
    $bulletin = (string)($d['bulletin'] ?? '');
    if (trim($bulletin) === '') continue;
    $title = trim((string)($d['name'] ?? 'HydroOJ')) . ' 公告';
    if (Database::fetchOne("SELECT news_id FROM news WHERE title=? AND content=?", [$title, $bulletin])) continue;
    Database::insert(
        "INSERT INTO news (title,content,time,pinned) VALUES (?,?,?,?)",
        [$title, $bulletin, date('Y-m-d H:i:s'), ($d['_id'] ?? '') === 'system' ? 1 : 0]
    );
    $newsCount++;
}

/* ───────────────────────── 讨论 ───────────────────────── */
$topicCount = 0;
foreach ($documentsDoc as $d) {
    if ((int)($d['docType'] ?? 0) !== 21) continue;
    $title = trim((string)($d['title'] ?? ''));
    $content = (string)($d['content'] ?? '');
    $owner = $unameByUid[(string)($d['owner'] ?? '')] ?? '';
    if ($title === '' || $owner === '' || in_array($owner, $skipNames, true)) continue;

    $problemId = 0;
    if ((int)($d['parentType'] ?? 0) === 10) {
        $problemId = $problemMap[(string)($d['parentId'] ?? '')] ?? 0;
    }
    if (Database::fetchOne("SELECT topic_id FROM bbs_topic WHERE title=? AND user_id=? AND content=?", [$title, $owner, $content])) continue;
    Database::insert(
        "INSERT INTO bbs_topic (problem_id,user_id,title,content,reply_count,last_reply,created_at)
         VALUES (?,?,?,?,0,NULL,?)",
        [$problemId, $owner, $title, $content, hydro_doc_date($d)]
    );
    $topicCount++;
}

/* ───────────────────────── 站内信 ───────────────────────── */
$messageCount = 0;
foreach ($messagesDoc as $m) {
    $content = (string)($m['content'] ?? '');
    if ($content === '') continue;
    $fromUid = (string)($m['from'] ?? '');
    $from = $unameByUid[$fromUid] ?? '';
    if ($from === '') $from = $fromUid === '1' ? 'Hydro' : 'Guest';
    $created = hydro_doc_date($m);
    $firstLine = trim((string)strtok($content, "\r\n"));
    $subject = '[HydroOJ] ' . mb_substr($firstLine !== '' ? $firstLine : '系统通知', 0, 60);

    $tos = $m['to'] ?? [];
    if (!is_array($tos)) $tos = [$tos];
    foreach ($tos as $toUid) {
        $to = $unameByUid[(string)$toUid] ?? '';
        if ($to === '' || in_array($to, $skipNames, true)) continue;
        if (Database::fetchOne(
            "SELECT id FROM messages WHERE from_user=? AND to_user=? AND content=? AND created_at=?",
            [$from, $to, $content, $created]
        )) continue;
        Database::insert(
            "INSERT INTO messages (from_user,to_user,subject,content,is_read,from_deleted,to_deleted,created_at)
             VALUES (?,?,?,?,1,0,0,?)",
            [$from, $to, $subject, $content, $created]
        );
        $messageCount++;
    }
}

/* ───────────────────────── 勋章 / 称号 ───────────────────────── */
$badgeById = [];
foreach ($badgesDoc as $b) $badgeById[(string)($b['_id'] ?? '')] = $b;
$badgeUsers = []; // uid => [badgeId...]
foreach ($badgesDoc as $b) {
    $bid = (string)($b['_id'] ?? '');
    foreach (($b['users'] ?? []) as $uid) $badgeUsers[(string)$uid][] = $bid;
}
foreach ($userBadgesDoc as $ub) {
    $uid = (string)($ub['owner'] ?? '');
    $bid = (string)($ub['badgeId'] ?? '');
    if ($uid !== '' && $bid !== '') $badgeUsers[$uid][] = $bid;
}
$badgeCollectibles = [];
foreach ($badgesDoc as $b) {
    $name = trim((string)($b['title'] ?? $b['short'] ?? ''));
    if ($name === '') continue;
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($b['backgroundColor'] ?? ''))
        ? (string)$b['backgroundColor'] : '#f5b94a';
    $row = Database::fetchOne("SELECT collectible_id FROM collectibles WHERE type='badge' AND name=?", [$name]);
    $cid = $row ? (int)$row['collectible_id'] : Database::insert(
        "INSERT INTO collectibles (type,name,description,value) VALUES ('badge',?,?,?)",
        [$name, (string)($b['content'] ?? ''), 'fa-award|' . $color]
    );
    $badgeCollectibles[(string)($b['_id'] ?? '')] = $cid;
}
$badgeGrants = 0;
foreach ($badgeUsers as $uid => $bids) {
    $uname = $unameByUid[(string)$uid] ?? '';
    if ($uname === '' || in_array($uname, $skipNames, true)) continue;
    $air = Database::fetchOne("SELECT title, equipped_badge FROM users WHERE user_id=?", [$uname]);
    if (!$air) continue;

    $userDoc = $hydroUserByUid[(string)$uid] ?? [];
    $selectedBadge = (string)($userDoc['badgeId'] ?? '');
    $firstCid = 0; $selectedCid = 0;
    foreach (array_unique($bids) as $bid) {
        $cid = $badgeCollectibles[(string)$bid] ?? 0;
        if (!$cid) continue;
        db_insert_ignore('user_collectibles', ['user_id' => $uname, 'collectible_id' => $cid]);
        if (!$firstCid) $firstCid = $cid;
        if ($selectedBadge !== '' && (string)$bid === $selectedBadge) $selectedCid = $cid;
        $badgeGrants++;
    }
    $fields = [];
    if (trim((string)($air['title'] ?? '')) === '') {
        $chosen = $badgeById[$selectedBadge] ?? $badgeById[(string)reset($bids)] ?? null;
        if ($chosen) $fields['title'] = mb_substr(trim((string)($chosen['short'] ?? $chosen['title'] ?? '')), 0, 32);
    }
    if ((int)($air['equipped_badge'] ?? 0) === 0) {
        $eq = $selectedCid ?: $firstCid;
        if ($eq) $fields['equipped_badge'] = $eq;
    }
    if ($fields) update_user_fields($uname, $fields);
}

/* ───────────────────────── 提交 / 判题结果 ───────────────────────── */
$verdictMap = [1 => 'AC', 2 => 'WA', 3 => 'TLE', 4 => 'MLE', 5 => 'OLE', 6 => 'RE', 7 => 'CE', 8 => 'SE'];
usort($recordsDoc, function ($a, $b) {
    $ta = hydro_oid_timestamp($a['_id'] ?? null);
    $tb = hydro_oid_timestamp($b['_id'] ?? null);
    return $ta <=> $tb;
});
$submissionsImported = 0; $submissionsSkipped = 0;
foreach ($recordsDoc as $r) {
    $recordId = hydro_oid_string($r['_id'] ?? null);
    if ($recordId === '') { $submissionsSkipped++; continue; }
    if (Database::fetchOne("SELECT id FROM submissions WHERE hydro_record_id=?", [$recordId])) { $submissionsSkipped++; continue; }

    $uname = $unameByUid[(string)($r['uid'] ?? '')] ?? '';
    if ($uname === '' || in_array($uname, $skipNames, true)) { $submissionsSkipped++; continue; }
    $airPid = $problemMap[(string)($r['pid'] ?? '')] ?? 0;
    if (!$airPid) { $submissionsSkipped++; continue; }

    $statusCode = (int)($r['status'] ?? 0);
    $testCases = is_array($r['testCases'] ?? null) ? $r['testCases'] : [];
    $compilerTexts = is_array($r['compilerTexts'] ?? null) ? $r['compilerTexts'] : [];
    $judgeTexts = is_array($r['judgeTexts'] ?? null) ? $r['judgeTexts'] : [];

    $caseResults = [];
    $passed = 0;
    foreach ($testCases as $tc) {
        $v = $verdictMap[(int)($tc['status'] ?? 0)] ?? 'UK';
        if ($v === 'AC') $passed++;
        $caseResults[] = [
            'name'    => (string)($tc['id'] ?? (count($caseResults) + 1)),
            'verdict' => $v,
            'score'   => (float)($tc['score'] ?? 0),
            'time'    => (float)($tc['time'] ?? 0),
            'memory'  => (int)($tc['memory'] ?? 0),
        ];
    }

    $failed = in_array($statusCode, [7, 8, 9, 10], true)
        || (empty($testCases) && !empty($compilerTexts));
    if ($failed) {
        $err = implode("\n", array_map('strval', $compilerTexts));
        if ($err === '') $err = implode("\n", array_map(fn($x) => is_string($x) ? $x : json_encode($x, JSON_UNESCAPED_UNICODE), $judgeTexts));
        if ($err === '') $err = 'HydroOJ 编译 / 系统错误';
        $status = 'failed';
        $score = 0;
        $total = 0;
        $passed = 0;
        $resultJson = $err;
    } else {
        $status = 'done';
        $score = (float)($r['score'] ?? 0);
        $total = count($testCases);
        $resultJson = json_encode($caseResults, JSON_UNESCAPED_UNICODE);
    }

    $created = hydro_date($r['judgeAt'] ?? null, false);
    if ($created === '') $created = hydro_doc_date($r);

    Database::insert(
        "INSERT INTO submissions
         (problem_id,judge_type,judge_lang,code,status,score,total_cases,passed_cases,result_json,user_id,contest_id,hydro_record_id,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,0,?,?,?)",
        [
            (string)$airPid,
            'standard',
            hydro_map_lang((string)($r['lang'] ?? '')),
            (string)($r['code'] ?? ''),
            $status,
            $score,
            $total,
            $passed,
            $resultJson,
            $uname,
            $recordId,
            $created,
            $created,
        ]
    );
    $submissionsImported++;
}

/* ───────────────────────── 用户统计 ───────────────────────── */
$statsUpdated = 0;
foreach ($unameByUid as $uname) {
    if ($uname === '' || in_array($uname, $skipNames, true)) continue;
    $s = Database::fetchOne(
        "SELECT COUNT(*) AS subs,
                COUNT(DISTINCT CASE WHEN status='done' AND score>=100 THEN problem_id END) AS solved
         FROM submissions WHERE user_id=?",
        [$uname]
    );
    $subs = (int)($s['subs'] ?? 0);
    $solved = (int)($s['solved'] ?? 0);
    Database::exec(
        "UPDATE users SET submit_count=?, submit=?, solved_count=?, solved=? WHERE user_id=?",
        [$subs, $subs, $solved, $solved, $uname]
    );
    $statsUpdated++;
}

echo "用户：新建 {$usersCreated}，更新 {$usersUpdated}，跳过 {$usersSkipped}\n";
echo "题目：新建 {$problemsCreated}，映射 " . count($problemMap) . " 个\n";
echo "公告：{$newsCount} 条\n";
echo "讨论：{$topicCount} 条\n";
echo "站内信：{$messageCount} 条\n";
echo "勋章：{$badgeGrants} 次授予\n";
echo "提交：导入 {$submissionsImported}，跳过 {$submissionsSkipped}\n";
echo "用户统计：更新 {$statsUpdated} 个\n";
