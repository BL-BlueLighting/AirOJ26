<?php
/**
 * 手机扫码后的实人认证页面（无需登录，凭一次性 ticket 进入）。
 */
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/db.php';
require_once __DIR__ . '/include/functions.php';
require_once __DIR__ . '/include/frv.php';

$ticketCode = (string)($_GET['ticket'] ?? '');
$ticket = frv_get_ticket($ticketCode);
$cfg = frv_config();
$error = '';

// 认证完成后手机返回/刷新本页时，不要显示 410，直接展示结果
if ($ticket && (string)$ticket['status'] === 'verified') {
    echo frv_mobile_shell('实人认证已完成',
        '<div class="big ok">✓</div><h1>认证已完成</h1>'
        . '<p class="ok">实人认证已通过，请回到电脑端查看结果。</p>'
    );
    exit;
}
if ($ticket && (string)$ticket['status'] === 'failed') {
    echo frv_mobile_shell('实人认证未通过',
        '<div class="big err">!</div><h1>认证未通过</h1>'
        . '<p class="err">请回到电脑端重新生成二维码后再试。</p>'
    );
    exit;
}
if ($ticket && (string)$ticket['status'] === 'pending' && trim((string)$ticket['started_at']) !== '') {
    echo frv_mobile_shell('实人认证进行中',
        '<h1>认证进行中</h1>'
        . '<p>请完成刷脸；完成后回到电脑端查看结果。</p>'
    );
    exit;
}

if (!$cfg['enabled']) {
    $error = '管理员尚未开启实人认证。';
} elseif (!$ticket) {
    $error = '二维码无效或已过期，请回到电脑端重新生成。';
} elseif ((string)$ticket['status'] !== 'pending') {
    $error = '该二维码已使用或已失效，请回到电脑端重新生成。';
} elseif ((int)$ticket['expires_at'] < time()) {
    $error = '二维码已过期，请回到电脑端重新生成。';
} else {
    $u = Database::fetchOne(
        "SELECT user_id, frv_status, frv_start_date, frv_start_count FROM users WHERE user_id=?",
        [(string)$ticket['user_id']]
    );
    if (!$u) {
        $error = '用户不存在。';
    } elseif ((string)($u['frv_status'] ?? 'none') === 'verified') {
        $error = '该账号已完成实人认证。';
    } elseif (frv_remaining_attempts($u) <= 0) {
        $error = '今日实人认证次数已用完，请明天再试。';
    }
}

if ($error !== '') {
    http_response_code(410);
    echo frv_mobile_shell('实人认证 · 二维码不可用',
        '<h1>二维码不可用</h1><p class="err">' . htmlspecialchars($error) . '</p>'
        . '<p>请回到电脑端刷新二维码后重新扫码。</p>'
    );
    exit;
}

$ticketJson = json_encode($ticketCode, JSON_UNESCAPED_SLASHES);
$body = <<<HTML
<h1>手机实人认证</h1>
<p>请填写与电脑端一致的姓名和身份证号，然后在手机上完成刷脸。</p>
<div style="margin:12px 0 14px;padding:11px 12px;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:#0b110e;color:#9fb3a8">
    <div style="font-size:12px;font-weight:700;color:#e6efe9;margin-bottom:6px">AirOJ DX 奖项 &amp; 实人认证 声明</div>
    <p style="margin:0 0 5px;font-size:11px;line-height:1.7">本站的实人认证由 DCloud (dcloud.io) 所提供，DCloud 拥有国家信息安全等级保护 (三级)，证书编号：11010813802-20001。</p>
    <p style="margin:0 0 5px;font-size:11px;line-height:1.7">本站承诺不保存您的身份证号；认证完成后仅保存认证姓名和认证结果，用于奖项认证。</p>
    <p style="margin:0 0 5px;font-size:11px;line-height:1.7">奖项认证使用 OIerDb-NG 的信息，使用 AGPL-v3.0。本站已使用 GPL-v3.0 开源。</p>
    <p style="margin:0 0 5px;font-size:11px;line-height:1.7">OIerDb-NG 项目链接：<a href="https://github.com/OIerDb-ng/OIerDb" target="_blank" rel="noopener" style="color:#6be0a4">https://github.com/OIerDb-ng/OIerDb</a></p>
    <p style="margin:0 0 5px;font-size:11px;line-height:1.7">感谢 OIerDB-NG 的 Contributors。</p>
    <p style="margin:6px 0 0;font-size:11px;line-height:1.7;color:#70837a">-- BL.BlueLighting, 2026<br>-- AirOJ DX 2026 Maintainer, AirOJ Master.</p>
</div>
<form id="frv-mobile-form" autocomplete="off">
    <label for="frv-m-name">真实姓名 *</label>
    <input type="text" id="frv-m-name" maxlength="20" placeholder="请输入身份证上的姓名" required>
    <label for="frv-m-id">身份证号 *</label>
    <input type="text" id="frv-m-id" maxlength="18" inputmode="numeric" placeholder="18 位身份证号" required>
    <label class="consent"><input type="checkbox" id="frv-m-agree"> <span>我已阅读并同意以上《AirOJ DX 奖项 &amp; 实人认证 声明》，授权使用姓名和身份证号进行核验。</span></label>
    <button type="submit" class="btn" id="frv-m-submit">开始刷脸</button>
    <p class="msg" id="frv-m-msg"></p>
    <p style="font-size:11px;color:#70837a">如果当前浏览器无法打开摄像头，请复制本页链接到系统浏览器（Safari / Chrome 等）继续。</p>
</form>
<script>
(function () {
    var TICKET = {$ticketJson};
    var form = document.getElementById('frv-mobile-form');
    var btn = document.getElementById('frv-m-submit');
    var msg = document.getElementById('frv-m-msg');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var realName = document.getElementById('frv-m-name').value.trim();
        var idCard = document.getElementById('frv-m-id').value.trim().toUpperCase();
        var agree = document.getElementById('frv-m-agree').checked;
        if (!realName) { msg.textContent = '请输入真实姓名'; return; }
        if (!/^[0-9]{17}[0-9X]$/.test(idCard)) { msg.textContent = '请输入正确的 18 位身份证号'; return; }
        if (!agree) { msg.textContent = '请先阅读并同意实人认证服务条款'; return; }
        if (typeof window.getMetaInfo !== 'function') {
            msg.textContent = '实人认证 SDK 未加载，请刷新页面或更换浏览器后重试';
            return;
        }
        var metaInfo = '';
        try {
            var m = window.getMetaInfo();
            metaInfo = typeof m === 'string' ? m : JSON.stringify(m || {});
        } catch (err) {
            metaInfo = '';
        }
        if (!metaInfo) { msg.textContent = '无法获取手机设备信息，请更换浏览器后重试'; return; }
        btn.disabled = true;
        btn.textContent = '正在发起认证…';
        msg.textContent = '';
        try {
            var res = await fetch('/frv_mobile_start.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'omit',
                body: JSON.stringify({ticket: TICKET, real_name: realName, id_card: idCard, meta_info: metaInfo})
            });
            var data = await res.json();
            if (data && data.ok && data.certify_url) {
                window.location.href = data.certify_url;
                return;
            }
            msg.textContent = (data && data.error) ? data.error : '发起认证失败，请稍后重试';
        } catch (err) {
            msg.textContent = '网络错误，请稍后重试';
        }
        btn.disabled = false;
        btn.textContent = '开始刷脸';
    });
})();
</script>
HTML;

echo frv_mobile_shell('实人认证', $body, (string)$cfg['meta_script_url']);
