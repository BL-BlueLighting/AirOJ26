<style>
.home-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }
@media (max-width: 900px) { .home-layout { grid-template-columns: 1fr; } }
.home-main { min-width: 0; }
.home-side { min-width: 0; }

/* Fortune */
.fortune-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; text-align: center; margin-bottom: 16px; }
.fortune-box .label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
.fortune-box .result { font-size: 36px; font-weight: 800; line-height: 1; margin-bottom: 8px; }
.fortune-box .msg { font-size: 14px; color: var(--text-secondary); }

/* User mini card */
.user-mini { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 16px; }
.user-mini .uname { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
.user-mini .ustats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.user-mini .ustat { text-align: center; padding: 8px; background: var(--bg-secondary); border-radius: var(--radius); }
.user-mini .ustat .num { font-size: 18px; font-weight: 700; }
.user-mini .ustat .lbl { font-size: 11px; color: var(--text-muted); }

/* Search */
.search-box { display: flex; gap: 8px; margin-bottom: 16px; }
.search-box input { flex: 1; }
.search-box .btn { flex-shrink: 0; }

/* Ranklist sidebar */
.rank-mini { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; }
.rank-mini h3 { font-size: 14px; font-weight: 700; margin-bottom: 12px; color: var(--accent-gold); }
.rank-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.rank-item:last-child { border-bottom: none; }
.rank-item .pos { font-weight: 700; color: var(--text-muted); width: 24px; text-align: center; }
.rank-item .pos.top3 { color: var(--accent-gold); }
.rank-item .ruser { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rank-item .rsolved { font-weight: 600; color: var(--green); }
.rank-item .rrating { font-weight: 600; color: var(--accent-gold); font-size: 12px; }
</style>

<div class="home-layout">
    <div class="home-main">

        <?php if ($news): ?>
        <div class="card" style="margin-bottom:16px;padding:0">
            <div style="padding:16px 20px 10px;font-weight:700;font-size:14px;display:flex;align-items:center">
                <i class="fa-solid fa-bullhorn"></i> 公告
                <a href="/news.php" style="margin-left:auto;font-size:12px;font-weight:400">全部公告 <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <table class="news-list">
                <tbody>
                <?php foreach ($news as $i => $n): ?>
                <tr>
                    <td>
                        <?php if (!empty($n['pinned'])): ?><span class="tag tag-gold">置顶</span> <?php endif; ?>
                        <a href="/news.php?id=<?= $n['news_id'] ?>"><?= htmlspecialchars($n['title']) ?></a>
                    </td>
                    <td class="news-date"><?= htmlspecialchars(substr((string)$n['time'], 0, 10)) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card" style="margin-bottom:16px;text-align:center;padding:32px">
            <div style="font-size:32px;margin-bottom:8px"><i class="fa-solid fa-bullhorn"></i></div>
            <p style="color:var(--text-muted)">暂无公告</p>
        </div>
        <?php endif; ?>

        <!-- 排行榜 -->
        <?php if ($rank): ?>
        <div class="card" style="padding:0">
            <div style="padding:16px 20px 8px;font-weight:700;font-size:14px;color:var(--accent-gold)"><i class="fa-solid fa-trophy"></i> 排行榜</div>
            <div class="table-wrap">
            <table>
                <thead><tr><th style="width:36px">#</th><th>用户</th><th>已解决</th><th>Rating</th></tr></thead>
                <tbody>
                <?php $i=1; foreach ($rank as $u): ?>
                <tr>
                    <td style="font-weight:700;color:<?=$i<=3?'var(--accent-gold)':'var(--text-muted)'?>"><?=$i++?></td>
                    <td><a href="/profile.php?uid=<?=urlencode($u['user_id'])?>"><?=user_role_html($u['user_id'], $u['nick']?:$u['user_id'])?></a></td>
                    <td style="font-weight:600;color:var(--green)"><?=$u['solved_count']??0?></td>
                    <td style="font-weight:600;color:var(--accent-gold)"><?=round($u['rating']??1500)?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div style="padding:8px 20px 12px;text-align:right"><a href="/ranklist.php" style="font-size:12px">完整排行 <i class="fa-solid fa-arrow-right"></i></a></div>
        </div>
        <?php endif; ?>

    </div>

    <div class="home-side">

        <!-- 搜索 -->
        <form method="get" action="/problem.php" class="search-box">
            <input type="text" name="id" class="form-input" placeholder="搜索题号 (PID)..." required>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>

        <!-- 今日运势（抽取状态保存在服务端，换设备也保持一致） -->
        <div class="fortune-box">
            <div class="label"><i class="fa-solid fa-clover"></i> 今日运势</div>
            <?php if ($fortune_drawn): ?>
            <div class="result" style="color:<?=$fc?>">§ <?= htmlspecialchars($fortune) ?> §</div>
            <div class="msg"><?= $fmsg ?></div>
            <?php else: ?>
            <div class="result" style="color:var(--text-muted)">§ 未抽 §</div>
            <form method="post" action="/index.php" style="margin-top:12px">
                <button type="submit" name="draw_fortune" value="1" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-dice"></i> 抽取
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- 我的信息 -->
        <div class="user-mini">
            <?php if ($user): ?>
            <div class="uname"><?= user_role_html($user['user_id'], $user['nick'] ?: $user['user_id']) ?></div>
            <div class="ustats">
                <div class="ustat"><div class="num" style="color:var(--green)"><?= $user['solved_count'] ?? 0 ?></div><div class="lbl">已解决</div></div>
                <div class="ustat"><div class="num" style="color:var(--accent-gold)"><?= round($user['rating'] ?? 1500) ?></div><div class="lbl">Rating</div></div>
                <div class="ustat"><div class="num" style="color:var(--accent)"><?= Database::fetchOne("SELECT COUNT(*) AS c FROM submissions WHERE user_id=?", [$user['user_id']])['c'] ?? 0 ?></div><div class="lbl">提交</div></div>
                <div class="ustat"><div class="num" style="color:var(--yellow)"><?= $ac ?></div><div class="lbl">总通过</div></div>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:12px 0">
                <p style="color:var(--text-muted);margin-bottom:12px">登录后查看个人信息</p>
                <a href="/login.php" class="btn btn-primary btn-sm">登录</a>
                <a href="/register.php" class="btn btn-outline btn-sm">注册</a>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

