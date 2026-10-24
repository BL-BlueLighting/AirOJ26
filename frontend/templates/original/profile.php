<div class="page-header"><h1><i class="fa-solid fa-user"></i> <?= $is_self ? '个人中心' : '用户主页' ?></h1></div>

<div class="profile-wrap">
    <div class="profile-side">
        <!-- 头像 -->
        <div class="profile-avatar">
            <img src="<?= htmlspecialchars(user_avatar_url($profile_user, 200)) ?>" alt="avatar">
            <div class="pname"><?= user_role_html($uid, $profile_user['nick'] ?: $uid) ?></div>
            <div class="pid"><?= htmlspecialchars($uid) ?></div>
        </div>

        <!-- 统计数据 -->
        <div class="profile-stats">
            <div class="row"><span class="lbl">Rating</span><span class="val" style="color:var(--accent-gold)"><?= $rating ?></span></div>
            <div class="row"><span class="lbl">已通过</span><span class="val" style="color:var(--green)"><?= $ac_count ?></span></div>
            <div class="row"><span class="lbl">总提交</span><span class="val" style="color:var(--accent)"><?= $total_sub ?></span></div>
            <div class="row"><span class="lbl">通过率</span><span class="val" style="color:var(--yellow)"><?= $total_sub > 0 ? round($ac_sub / $total_sub * 100, 1) : 0 ?>%</span></div>
            <div class="row"><span class="lbl">AC 提交</span><span class="val" style="color:var(--green)"><?= $ac_sub ?></span></div>
        </div>

        <!-- 个人信息 -->
        <div class="profile-info">
            <div class="row"><span class="lbl">邮箱</span><span class="val"><?= htmlspecialchars($profile_user['email'] ?? '-') ?></span></div>
            <div class="row"><span class="lbl">学校</span><span class="val"><?= htmlspecialchars($profile_user['school'] ?? '-') ?></span></div>
            <div class="row"><span class="lbl">注册时间</span><span class="val"><?= $profile_user['reg_time'] ?? '-' ?></span></div>
        </div>

        <?php if ($is_self || $viewer): ?>
        <div style="margin-top:12px;text-align:center;display:flex;gap:8px;justify-content:center">
            <?php if ($is_self): ?>
            <a href="/settings.php?tab=profile" class="btn btn-outline btn-sm"><i class="fa-solid fa-gear"></i> 修改资料</a>
            <?php endif; ?>
            <a href="/messages.php?compose=1&amp;to=<?= urlencode($uid) ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-envelope"></i> 发站内信</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="profile-main">
        <?php if ($userBio !== ''): ?>
        <!-- 个人简介 / 签名 -->
        <div class="card">
            <h2><i class="fa-solid fa-signature"></i> 个人简介</h2>
            <div class="mdui-prose" style="font-size:14px;line-height:1.8"><?= $pd->text($userBio) ?></div>
        </div>
        <?php endif; ?>

        <!-- 成就 -->
        <div class="card">
            <h2><i class="fa-solid fa-trophy"></i> 成就</h2>
            <?php if ($achievements): ?>
            <div class="achievement-grid">
                <?php foreach ($achievements as $a): $unlocked = !empty($a['unlocked_at']); ?>
                <div class="achievement-card<?= $unlocked ? '' : ' locked' ?>" style="<?= $unlocked ? '--ach-color:' . htmlspecialchars($a['color']) : '' ?>">
                    <div class="achievement-icon"><i class="fa-solid <?= htmlspecialchars($a['icon']) ?>"></i></div>
                    <div class="achievement-info">
                        <strong><?= htmlspecialchars($a['name']) ?></strong>
                        <small><?= htmlspecialchars($a['description'] ?? '') ?></small>
                        <?php if ($unlocked): ?><span class="achievement-time"><?= htmlspecialchars($a['unlocked_at']) ?> 解锁</span>
                        <?php else: ?><span class="achievement-time">未解锁</span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted)">暂无成就。</p>
            <?php endif; ?>
            <p style="margin-top:12px"><a href="/achievements.php?uid=<?= urlencode($uid) ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-trophy"></i> 查看全部成就</a></p>
        </div>

        <!-- 收藏品 -->
        <div class="card">
            <h2><i class="fa-solid fa-gem"></i> 收藏品</h2>
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:12px">已拥有 <?= $ownedCount ?> 件收藏品。</p>
            <div class="equip-summary">
                <?php foreach (AIR_OJ_COLLECTIBLE_TYPES as $type => $meta): ?>
                <div class="equip-item">
                    <span class="equip-label"><i class="fa-solid <?= $meta['icon'] ?>"></i> <?= htmlspecialchars($meta['label']) ?></span>
                    <span class="equip-value">
                    <?php if (!empty($equipped[$type])): ?>
                        <?= htmlspecialchars($equipped[$type]['name']) ?>
                    <?php else: ?>
                        <span style="color:var(--text-muted)">未装备</span>
                    <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($is_self): ?>
            <a href="/settings.php?tab=collectibles" class="btn btn-outline btn-sm" style="margin-top:12px"><i class="fa-solid fa-gem"></i> 管理收藏品</a>
            <?php endif; ?>
        </div>

        <!-- 已通过题目 -->
        <div class="card">
            <h2><i class="fa-solid fa-circle-check"></i> 已通过题目 (<?= $ac_count ?>)</h2>
            <?php if ($ac_problems): ?>
            <div style="line-height:2">
                <?php foreach ($ac_problems as $p): ?>
                <a href="/problem.php?id=<?= $p['problem_id'] ?>" class="solved-tag">#<?= $p['problem_id'] ?></a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted)">暂时还没有通过的题目。</p>
            <?php endif; ?>
        </div>

        <!-- 最近提交 -->
        <div class="card" style="padding:0">
            <div style="padding:16px 20px 0;font-weight:700;font-size:14px"><i class="fa-solid fa-chart-simple"></i> 最近提交</div>
            <?php if ($subs): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>题目</th><th>语言</th><th>状态</th><th>分数</th><th>操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($subs as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><a href="/problem.php?id=<?= $s['problem_id'] ?>"><?= $s['problem_id'] ?></a></td>
                        <td><?= $s['judge_lang'] ?></td>
                        <td><span class="badge badge-<?= $s['status'] ?>"><?= $s['status'] ?></span></td>
                        <td style="font-weight:600;color:<?= ($s['score']??0)>=100 ? 'var(--green)' : 'var(--red)' ?>"><?= $s['score'] ?? '-' ?></td>
                        <td><a href="/result.php?id=<?= $s['id'] ?>" class="btn btn-primary btn-xs">查看</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="padding:20px;text-align:center;color:var(--text-muted)">暂无提交记录。</div>
            <?php endif; ?>
        </div>
    </div>
</div>

