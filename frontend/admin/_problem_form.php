<?php
/**
 * 题目编写表单（左栏编辑 / 右栏实时预览），由 problem_add.php 与 problem_edit.php 复用
 *
 * 使用前需设置：
 *   $pe_action      表单提交地址
 *   $pe_values      题目字段数组
 *   $pe_submit      提交按钮文字
 *   $pe_is_edit     是否为编辑已有题目
 *   $pe_show_test   是否在表单里显示“上传测试数据”（添加页用）
 *   $pe_diffs       已有难度标签数组（可选）
 *   $pe_spj_exists  是否已有特判程序（编辑页用）
 *
 * 预览由 admin/problem_preview.php 用服务端 Parsedown 渲染，保证与最终题目页一致。
 */
$val = function (string $k) use ($pe_values): string {
    return (string)($pe_values[$k] ?? '');
};
$pe_diffs = $pe_diffs ?? [];
$pe_spj_exists = !empty($pe_spj_exists);
?>
<div class="pe-wrap">
    <div class="pe-edit">
        <form method="post" action="<?= htmlspecialchars($pe_action) ?>" id="problem-form" class="card"
              style="margin-bottom:16px" enctype="multipart/form-data">
            <h2><i class="fa-solid fa-pen-to-square"></i> 题目信息</h2>

            <?php if (!empty($pe_variant)): ?>
            <div class="alert alert-info" style="font-size:12px">
                <i class="fa-solid fa-layer-group"></i> 这是题目「<?= htmlspecialchars($val('title')) ?>」的难度版本；
                标题、标签、出题人由题目组共享，每个版本拥有独立的题面、测试数据与 Special Judge。
            </div>
            <input type="hidden" name="title" value="<?= htmlspecialchars($val('title')) ?>">
            <input type="hidden" name="tags" value="<?= htmlspecialchars($val('tags')) ?>">
            <input type="hidden" name="author" value="<?= htmlspecialchars($val('author')) ?>">
            <?php else: ?>
            <div class="form-row">
                <div class="form-group" style="flex:3">
                    <label>标题 *</label>
                    <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($val('title')) ?>" required>
                </div>
                <div class="form-group" style="flex:1">
                    <label>出题人</label>
                    <input type="text" name="author" class="form-input" value="<?= htmlspecialchars($val('author') ?: ($_SESSION['airoj_user']['user_id'] ?? '')) ?>">
                </div>
            </div>

            <div class="form-group">
                <label>标签 <span class="pe-hint">用逗号分隔，如：动态规划,图论</span></label>
                <input type="text" name="tags" class="form-input" value="<?= htmlspecialchars($val('tags')) ?>" placeholder="入门,数学">
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>题目描述 <span class="pe-md-tag">Markdown</span></label>
                <textarea name="description" class="form-input pe-md md-editor" style="min-height:180px"><?= htmlspecialchars($val('description')) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>输入格式 <span class="pe-md-tag">Markdown</span></label>
                    <textarea name="input" class="form-input pe-md md-editor" style="min-height:110px"><?= htmlspecialchars($val('input')) ?></textarea>
                </div>
                <div class="form-group">
                    <label>输出格式 <span class="pe-md-tag">Markdown</span></label>
                    <textarea name="output" class="form-input pe-md md-editor" style="min-height:110px"><?= htmlspecialchars($val('output')) ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>样例输入</label>
                    <textarea name="sample_input" class="form-input pe-md" style="min-height:100px"><?= htmlspecialchars($val('sample_input')) ?></textarea>
                </div>
                <div class="form-group">
                    <label>样例输出</label>
                    <textarea name="sample_output" class="form-input pe-md" style="min-height:100px"><?= htmlspecialchars($val('sample_output')) ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label>提示 <span class="pe-md-tag">Markdown</span></label>
                <textarea name="hint" class="form-input pe-md md-editor" style="min-height:100px"><?= htmlspecialchars($val('hint')) ?></textarea>
            </div>

            <div class="form-group">
                <label>难度标签 <span class="pe-hint">每个难度版本通常选择一个难度；同一题目的多个版本会显示为难度切换标签</span></label>
                <div id="diff-rows">
                    <?php
                    $diffRows = $pe_diffs ?: [];
                    if (!$diffRows) $diffRows = [['category' => 'BASIC', 'level' => 1, 'position' => 'mid']];
                    foreach ($diffRows as $d): ?>
                    <div class="diff-row" style="display:flex;gap:8px;margin-bottom:6px">
                        <select name="diff_cat[]" class="form-input" style="flex:1">
                            <?php foreach (AIR_OJ_DIFFICULTY as $ck => $cfg): ?>
                            <option value="<?= $ck ?>" <?= $d['category'] === $ck ? 'selected' : '' ?>><?= $cfg['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="diff_level[]" class="form-input" style="width:110px">
                            <?php for ($i = 1; $i <= 15; $i++): ?>
                            <option value="<?= $i ?>" <?= (int)$d['level'] === $i ? 'selected' : '' ?>><?= $i ?> 级</option>
                            <?php endfor; ?>
                        </select>
                        <select name="diff_pos[]" class="form-input" style="width:100px">
                            <option value="low"  <?= $d['position'] === 'low'  ? 'selected' : '' ?>>下位</option>
                            <option value="mid"  <?= $d['position'] === 'mid'  ? 'selected' : '' ?>>中位</option>
                            <option value="high" <?= $d['position'] === 'high' ? 'selected' : '' ?>>上位</option>
                        </select>
                        <button type="button" class="btn btn-outline btn-sm diff-remove" title="删除"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-outline btn-sm" id="diff-add"><i class="fa-solid fa-plus"></i> 添加难度</button>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="spj" id="spj-toggle" value="1" <?= $val('spj') === '1' ? 'checked' : '' ?>>
                    启用 Special Judge（特判）
                </label>
                <div id="spj-box" style="margin-top:8px;<?= $val('spj') === '1' ? '' : 'display:none' ?>">
                    <p class="pe-hint" style="margin-bottom:6px">
                        特判程序接收三个参数：输入文件、标准输出文件、选手输出文件；
                        返回 <code>0</code> 表示通过，非 0 表示不通过。
                        <?php if ($pe_spj_exists): ?><br>已有特判程序，可重新上传覆盖。<?php else: ?><br><strong>启用特判时必须上传特判程序。</strong><?php endif; ?>
                    </p>
                    <input type="file" name="spj_file" class="form-input" accept=".c,.cpp,.cc,.cxx">
                </div>
            </div>

            <?php if (!empty($pe_show_test)): ?>
            <div class="form-group">
                <label>测试数据 <span class="pe-hint">可多选，按文件名配对（如 1.in / 1.out）</span></label>
                <input type="file" name="test_data[]" class="form-input" multiple>
            </div>
            <?php endif; ?>

            <div class="form-row" style="margin-top:4px">
                <div class="form-group">
                    <label>状态</label>
                    <select name="defunct" class="form-input">
                        <option value="N" <?= $val('defunct') === 'Y' ? '' : 'selected' ?>>正常</option>
                        <option value="Y" <?= $val('defunct') === 'Y' ? 'selected' : '' ?>>隐藏</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars($pe_submit) ?></button>
        </form>
    </div>

    <div class="pe-preview card" style="padding:0">
        <div class="pe-preview-head">
            <i class="fa-solid fa-eye"></i> 实时预览
            <span class="pe-preview-hint">Markdown 渲染 · 与题目页一致</span>
        </div>
        <div class="pe-preview-body" id="pe-preview-body"></div>
    </div>
</div>

<script src="/js/md-toolbar.js?v=1"></script>
<script>
(function () {
    // ── 实时预览 ──
    var form = document.getElementById('problem-form');
    var body = document.getElementById('pe-preview-body');
    var timer = null, seq = 0;
    function refresh() {
        var my = ++seq;
        fetch('/admin/problem_preview.php', {
            method: 'POST', body: new FormData(form), credentials: 'same-origin'
        }).then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
          .then(function (html) { if (my === seq) body.innerHTML = html; })
          .catch(function () { if (my === seq) body.innerHTML = '<p style="color:var(--text-muted)">预览加载失败</p>'; });
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(refresh, 220); }
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    refresh();

    // ── 难度行增删 ──
    var rows = document.getElementById('diff-rows');
    document.getElementById('diff-add').addEventListener('click', function () {
        var first = rows.querySelector('.diff-row');
        var clone = first.cloneNode(true);
        clone.querySelector('select').selectedIndex = 0;
        rows.appendChild(clone);
        bindRemove(clone);
    });
    function bindRemove(row) {
        row.querySelector('.diff-remove').addEventListener('click', function () {
            if (rows.querySelectorAll('.diff-row').length > 1) row.remove();
        });
    }
    rows.querySelectorAll('.diff-row').forEach(bindRemove);

    // ── 特判上传区域显隐 ──
    var spj = document.getElementById('spj-toggle'), box = document.getElementById('spj-box');
    spj.addEventListener('change', function () { box.style.display = spj.checked ? '' : 'none'; });
})();
</script>
