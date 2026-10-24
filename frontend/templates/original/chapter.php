<div class="chapter-hero<?= $cover === '' ? ' chapter-hero-empty' : '' ?>" style="<?= htmlspecialchars($cover, ENT_COMPAT) ?>">
    <div class="chapter-hero-inner">
        <h1><?= htmlspecialchars($chapter['title']) ?></h1>
        <div class="chapter-hero-meta">
            <span><i class="fa-solid fa-user"></i> 创建人：<?= user_role_html($chapter['creator']) ?></span>
            <span><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($chapter['created_at']) ?></span>
            <span><i class="fa-solid fa-list-check"></i> <?= count($problems) ?> 道题</span>
        </div>
        <?php if (trim((string)$chapter['description']) !== ''): ?>
        <div class="chapter-hero-desc mdui-prose"><?= $pd->text((string)$chapter['description']) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="padding:0">
    <div style="padding:16px 20px 8px;font-weight:700;font-size:14px"><i class="fa-solid fa-list-check"></i> 题目列表</div>
    <?php if (!$problems): ?>
    <div class="empty"><p>本章节还没有添加题目</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr>
            <th style="width:60px">#</th><th>题目</th><th style="width:190px">难度</th>
            <th style="width:120px">通过 / 提交</th><th style="width:110px">我的状态</th><th style="width:90px">操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($problems as $i => $p):
            $pid = (int)$p['problem_id'];
            $st = $stats[$pid] ?? null;
            $diffs = problem_difficulties((string)$pid);
        ?>
        <tr>
            <td style="font-weight:700;color:var(--accent)"><?= $i + 1 ?></td>
            <td>
                <?php if ($p['title'] === null): ?>
                <span style="color:var(--text-muted)">题目 #<?= $pid ?>（已删除）</span>
                <?php else: ?>
                <a href="/problem.php?id=<?= $pid ?>"><?= htmlspecialchars($p['title']) ?></a>
                <?php endif; ?>
            </td>
            <td>
                <?php foreach ($diffs as $d): ?>
                <span class="diff-tag diff-<?= $diffClass[$d['category']] ?? 'basic' ?>"><?= htmlspecialchars(difficulty_label($d['category'], (int)$d['level'], $d['position'])) ?></span>
                <?php endforeach; ?>
            </td>
            <td>
                <span style="color:var(--green);font-weight:600"><?= (int)($st['ac'] ?? 0) ?></span>
                <span style="color:var(--text-muted)"> / <?= (int)($st['total'] ?? 0) ?></span>
            </td>
            <td><?= isset($solved[$pid]) ? '<span class="tag tag-green"><i class="fa-solid fa-circle-check"></i> 已通过</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
            <td><a href="/problem.php?id=<?= $pid ?>" class="btn btn-primary btn-xs">做题</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

