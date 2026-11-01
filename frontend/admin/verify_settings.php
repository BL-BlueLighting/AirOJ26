<?php
$title = '认证设置';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/functions.php';
require_once __DIR__ . '/../include/frv.php';
require_once __DIR__ . '/../include/oierdb.php';
if (session_status() === PHP_SESSION_NONE) @session_start();
$is_admin = Database::fetchOne("SELECT * FROM privilege WHERE user_id=? AND right_str='administrator'", [$_SESSION['airoj_user']['user_id'] ?? '']);
if (!isset($_SESSION['airoj_user']) || !$is_admin) { header('Location: ../login.php'); exit; }

$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_verify'])) {
    $enabled    = !empty($_POST['frv_enabled']) ? '1' : '0';
    $baseUrl    = rtrim(trim((string)($_POST['frv_base_url'] ?? '')), '/');
    $appId      = trim((string)($_POST['frv_app_id'] ?? ''));
    $metaScript = trim((string)($_POST['frv_meta_script_url'] ?? ''));
    $returnUrl  = trim((string)($_POST['frv_return_url'] ?? ''));
    $dailyLimit = max(1, min(20, (int)($_POST['frv_daily_limit'] ?? 3)));
    $manual     = !empty($_POST['award_manual_enabled']) ? '1' : '0';
    $connect    = trim((string)($_POST['frv_connect_code'] ?? ''));

    if ($baseUrl !== '' && !preg_match('~^https?://~i', $baseUrl)) {
        $err = 'URL 化云函数地址必须以 http(s):// 开头';
    } elseif ($returnUrl !== '' && !preg_match('~^https?://~i', $returnUrl)) {
        $err = '回调地址必须以 http(s):// 开头';
    } elseif ($metaScript !== '' && !preg_match('~^https?://~i', $metaScript)) {
        $err = 'MetaInfo SDK 地址必须以 http(s):// 开头';
    } elseif ($enabled === '1' && $baseUrl === '') {
        $err = '开启实人认证前请填写 URL 化云函数地址';
    } else {
        Database::setConfig('frv_enabled', $enabled);
        Database::setConfig('frv_base_url', $baseUrl);
        Database::setConfig('frv_app_id', $appId);
        Database::setConfig('frv_meta_script_url', $metaScript !== '' ? $metaScript : frv_default_meta_script());
        Database::setConfig('frv_return_url', $returnUrl);
        Database::setConfig('frv_daily_limit', (string)$dailyLimit);
        Database::setConfig('award_manual_enabled', $manual);
        if (!empty($_POST['clear_frv_connect_code'])) {
            Database::setConfig('frv_connect_code', '');
        } elseif ($connect !== '') {
            Database::setConfig('frv_connect_code', $connect);
        }
        header('Location: /admin/verify_settings.php?saved=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_oierdb'])) {
    set_time_limit(0);
    ignore_user_abort(true);
    $file = $_FILES['oierdb_file'] ?? null;
    if (!is_array($file) || !isset($file['error'])) {
        $err = '请选择 OIerDb raw.txt 文件';
    } elseif ((int)$file['error'] !== UPLOAD_ERR_OK) {
        $err = '上传失败（错误码 ' . (int)$file['error'] . '），请检查 PHP 上传大小限制';
    } elseif (!is_uploaded_file((string)$file['tmp_name'])) {
        $err = '上传文件无效';
    } else {
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            $err = '文件为空';
        } elseif ($size > 64 * 1024 * 1024) {
            $err = 'OIerDb 文件不能超过 64 MB';
        } else {
            try {
                $result = oierdb_import_file((string)$file['tmp_name']);
                header('Location: /admin/verify_settings.php?imported=' . (int)$result['count']);
                exit;
            } catch (Throwable $e) {
                $err = '导入失败：' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['saved'])) $ok = '认证设置已保存';
if (isset($_GET['imported'])) $ok = 'OIerDb 数据导入完成，共 ' . (int)$_GET['imported'] . ' 条记录';

$cfg = frv_config();
$oierCount = oierdb_count();
$importedAt = (string)Database::getConfig('oierdb_imported_at', '');
$hasConnectCode = (string)Database::getConfig('frv_connect_code', '') !== '';
$uploadMax = (string)ini_get('upload_max_filesize');
$postMax = (string)ini_get('post_max_size');
$iniBytes = static function (string $v): int {
    $v = trim($v);
    if ($v === '') return 0;
    $unit = strtolower(substr($v, -1));
    $num = (int)$v;
    return $num * (['k' => 1024, 'm' => 1048576, 'g' => 1073741824][$unit] ?? 1);
};
$uploadLimitOk = $iniBytes($uploadMax) >= 32 * 1024 * 1024 && $iniBytes($postMax) >= 32 * 1024 * 1024;
$effectiveReturn = frv_return_url();

$admin_current_group = 'verify';
$admin_current_item  = 'verify_settings';
require __DIR__ . '/_layout_top.php'; ?>
<div class="page-header">
    <h1><i class="fa-solid fa-id-card"></i> 认证设置</h1>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

<div class="card" style="max-width:980px">
    <h2><i class="fa-solid fa-shield-halved"></i> 实人认证（uniCloud FRV）</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px">
        网页端使用阿里云实人认证 Web SDK 获取 <code>window.getMetaInfo()</code>，由本站服务端调用
        URL 化 uniCloud 云函数完成 <code>getCertifyUrl</code> / <code>getAuthResult</code>。
        认证通过后才能申请奖项认证。身份证号只用于本次核验，不会写入数据库。
    </p>
    <form method="post">
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px;cursor:pointer;font-weight:600">
            <input type="checkbox" name="frv_enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
            开启实人认证
        </label>
        <div class="form-group">
            <label>URL 化云函数地址 *</label>
            <input type="url" name="frv_base_url" class="form-input" value="<?= htmlspecialchars($cfg['base_url']) ?>"
                   placeholder="https://xxx.bspapp.com/airoj-frv">
            <p style="font-size:11px;color:var(--text-muted);margin-top:4px">
                本站会请求 <code>&lt;地址&gt;/getCertifyUrl</code> 和 <code>&lt;地址&gt;/getAuthResult</code>。
            </p>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>uniCloud AppID</label>
                <input type="text" name="frv_app_id" class="form-input" value="<?= htmlspecialchars($cfg['app_id']) ?>" placeholder="可选，URL 化调用需要时填写">
            </div>
            <div class="form-group">
                <label>每日认证次数上限</label>
                <input type="number" name="frv_daily_limit" class="form-input" min="1" max="20" value="<?= (int)$cfg['daily_limit'] ?>">
            </div>
        </div>
        <div class="form-group">
            <label>uni-cloud-s2s ConnectCode</label>
            <input type="password" name="frv_connect_code" class="form-input" autocomplete="new-password"
                   placeholder="<?= $hasConnectCode ? '已配置，留空保持不变' : 'CONNECTCODE 字符串' ?>">
            <?php if ($hasConnectCode): ?>
            <label style="display:flex;align-items:center;gap:8px;margin-top:6px;font-size:12px;color:var(--text-muted);cursor:pointer">
                <input type="checkbox" name="clear_frv_connect_code" value="1"> 清除已保存的 ConnectCode
            </label>
            <?php endif; ?>
            <p style="font-size:11px;color:var(--text-muted);margin-top:4px">
                云函数开启 uni-cloud-s2s 时填写；本站会发送 <code>Unicloud-S2s-Authorization: CONNECTCODE ...</code>。
            </p>
        </div>
        <div class="form-group">
            <label>MetaInfo SDK 地址</label>
            <input type="url" name="frv_meta_script_url" class="form-input" value="<?= htmlspecialchars($cfg['meta_script_url']) ?>">
            <p style="font-size:11px;color:var(--text-muted);margin-top:4px">默认使用阿里云实人认证 Web SDK，提供 <code>window.getMetaInfo()</code>。</p>
        </div>
        <div class="form-group">
            <label>认证回调地址</label>
            <input type="url" name="frv_return_url" class="form-input" value="<?= htmlspecialchars($cfg['return_url']) ?>"
                   placeholder="<?= htmlspecialchars($effectiveReturn) ?>">
            <p style="font-size:11px;color:var(--text-muted);margin-top:4px">
                留空则使用 <code><?= htmlspecialchars($effectiveReturn) ?></code>；uniCloud 要求 HTTPS。
            </p>
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin:16px 0;cursor:pointer;font-weight:600">
            <input type="checkbox" name="award_manual_enabled" value="1" <?= $cfg['manual_award'] ? 'checked' : '' ?>>
            开启奖项人工认证（默认关闭；关闭后用户只能使用 OIerDb 自动认证）
        </label>
        <button type="submit" name="save_verify" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> 保存认证设置</button>
    </form>
</div>

<div class="card" style="max-width:980px">
    <h2><i class="fa-solid fa-database"></i> OIerDb 奖项数据</h2>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:10px">
        上传 OIerDb-ng 的 <code>data/raw.txt</code>（约 24 MB）导入本地奖项库，用于自动认证。
        导入会替换现有数据。数据来源：<a href="https://github.com/OIerDb-ng/OIerDb" target="_blank" rel="noopener">OIerDb-ng</a>（AGPL-3.0）。
    </p>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">
        当前记录：<strong><?= $oierCount ?></strong> 条<?= $importedAt !== '' ? ' · 最近导入：' . htmlspecialchars($importedAt) : '' ?>
        · PHP 上传限制：<?= htmlspecialchars($uploadMax) ?> / POST <?= htmlspecialchars($postMax) ?>
    </p>
    <?php if (!$uploadLimitOk): ?>
    <div class="alert alert-info" style="font-size:12px">
        当前 PHP 上传限制可能不足以导入 raw.txt，请把 <code>upload_max_filesize</code> 和 <code>post_max_size</code> 调整到至少 32M。
        也可以在服务器上以 PHP-FPM 用户执行（例如 <code>sudo -u www php maintain/import_oierdb.php /path/raw.txt</code>）。
    </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group"><input type="file" name="oierdb_file" accept=".txt,.csv,text/plain" required></div>
            <button type="submit" name="import_oierdb" class="btn btn-primary"
                    onclick="return confirm('导入会替换现有 OIerDb 数据，确认继续？')">
                <i class="fa-solid fa-upload"></i> 上传并导入
            </button>
        </div>
    </form>
</div>

<div class="card" style="max-width:980px">
    <h2><i class="fa-solid fa-circle-info"></i> uniCloud 部署提示</h2>
    <ol style="font-size:13px;line-height:1.8;color:var(--text-secondary);padding-left:20px">
        <li>在 uniCloud 控制台开通「实人认证」，为云函数加载 <code>uni-cloud-verify</code> 扩展库。</li>
        <li>新建一个云函数并 URL 化，路径例如 <code>https://xxx.bspapp.com/airoj-frv</code>；本站请求其 <code>/getCertifyUrl</code> 与 <code>/getAuthResult</code>。</li>
        <li>云函数内使用 <code>uniCloud.getFacialRecognitionVerifyManager({ requestId, appId })</code>；Web 流程调用 <code>getCertifyUrl({ realName, idCard, metaInfo, returnUrl })</code>，结果查询调用 <code>getAuthResult({ certifyId })</code>。</li>
        <li>如需 uni-cloud-s2s，配置 connectCode 并填入上方表单；也可在云函数中自行校验请求来源。</li>
        <li>把云函数返回的 <code>certifyUrl</code> / <code>authState</code> 原样返回；本站会解析并写入认证状态。</li>
    </ol>
    <details style="margin-top:10px">
        <summary style="cursor:pointer;font-weight:600;font-size:13px">URL 化云函数示例（Node.js）</summary>
        <pre style="margin-top:10px;padding:14px;background:var(--bg-secondary);border-radius:var(--radius);overflow:auto;font-size:12px;line-height:1.6">'use strict';
const { verifyHttpInfo } = require('uni-cloud-s2s');

exports.main = async (event, context) => {
  // URL 化调用时校验 ConnectCode
  if (context.source === 'http') {
    try {
      verifyHttpInfo(event);
    } catch (e) {
      return { errCode: 51000, errMsg: 's2s auth failed' };
    }
  }
  const frv = uniCloud.getFacialRecognitionVerifyManager({
    requestId: context.requestId,
    appId: event.appId
  });
  const action = event.action || (event.path || '').split('/').pop();
  try {
    if (action === 'getCertifyUrl') {
      const res = await frv.getCertifyUrl({
        realName: event.realName,
        idCard: event.idCard,
        metaInfo: event.metaInfo,
        returnUrl: event.returnUrl
      });
      return { errCode: 0, errMsg: '', certifyUrl: res.certifyUrl };
    }
    if (action === 'getAuthResult') {
      const res = await frv.getAuthResult({ certifyId: event.certifyId });
      return { errCode: 0, errMsg: '', authState: res.authState, realName: res.realName };
    }
    return { errCode: 'invalid-action', errMsg: 'invalid action' };
  } catch (e) {
    return { errCode: e.errCode || 'frv-error', errMsg: e.errMsg || e.message || 'FRV error' };
  }
};</pre>
        <p style="font-size:11px;color:var(--text-muted);margin:8px 0 0">
            使用 ConnectCode 时需要在 <code>uni-config-center/uni-cloud-s2s/config.json</code> 配置同样的值；
            如果不想使用 S2S，可删除 <code>verifyHttpInfo</code> 部分，并保持上方 ConnectCode 留空（不推荐）。
        </p>
    </details>
    <p style="font-size:12px;color:var(--text-muted)">
        参考：<a href="https://doc.dcloud.net.cn/uniCloud/frv/dev.html" target="_blank" rel="noopener">uniCloud 实人认证开发指南</a>
    </p>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
