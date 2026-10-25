<?php
$problemTitle  = $problem['title'] ?? ('Problem ' . $row['problem_id']);
$problemAuthor = trim((string)($problem['author'] ?? '')) ?: '未知出题人';
$problemTags   = preg_split('/[,，]/u', (string)($problem['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
$userNick      = $userRow['nick'] ?? $row['user_id'];
$scoreBar      = min(100, max(0, $score));
$judgeVisible  = ['AC', 'WA', 'TLE', 'MLE', 'RE', 'SE', 'UK'];
?>
<div class="rs-page">
    <div class="rs-crumbs">
        <a href="/status.php"><i class="fa-solid fa-arrow-left"></i> 返回评测状态</a>
        <span>提交 #<?= $id ?> · <?= htmlspecialchars($row['created_at'] ?? '') ?></span>
    </div>

    <div class="rs-shell">
        <section class="rs-hero" style="<?= htmlspecialchars($heroStyle, ENT_COMPAT) ?>">
            <?php if ($rcUrl): ?><img class="rs-hero-art" src="<?= htmlspecialchars($rcUrl) ?>" alt="" aria-hidden="true"><?php endif; ?>

            <div class="rs-topline">
                <span>AirOJ // Recent Score</span>
                <span><?= htmlspecialchars($row['created_at'] ?? '') ?></span>
            </div>

            <div class="rs-hero-content">
                <div class="rs-profile">
                    <?php if ($avatarUrl): ?>
                    <img class="rs-avatar" src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($userNick) ?>">
                    <?php else: ?>
                    <div class="rs-avatar rs-avatar-empty"><i class="fa-solid fa-user"></i></div>
                    <?php endif; ?>
                    <div class="rs-profile-main">
                        <div class="rs-profile-name"><?= user_role_html($row['user_id'], $userNick) ?></div>
                        <div class="rs-profile-sub">#<?= $idDisplay ?> · <?= htmlspecialchars($row['judge_lang'] ?: '—') ?> · <?= htmlspecialchars($row['created_at'] ?? '') ?></div>
                        <div class="rs-profile-stats">
                            <span><b><?= $rating ?></b>Rating</span>
                            <span><b><?= (int)($userRow['solved_count'] ?? 0) ?></b>已解决</span>
                            <span><b><?= (int)($userRow['submit_count'] ?? 0) ?></b>提交</span>
                        </div>
                    </div>
                </div>

                <div class="rs-grade-wrap">
                    <div class="rs-grade rs-grade-<?= htmlspecialchars($gradeClass) ?>"><?= htmlspecialchars($grade) ?></div>
                    <div class="rs-grade-label"><?= htmlspecialchars($verdict) ?> · <?= htmlspecialchars($verdictCn) ?></div>
                </div>

                <div class="rs-hero-score">
                    <div class="rs-hero-score-label">Score</div>
                    <div class="rs-hero-score-value"><b><?= htmlspecialchars($scoreDisplay) ?></b><span>/ 100</span></div>
                    <div class="rs-meter"><i style="width:<?= round($scoreBar, 2) ?>%"></i></div>
                </div>
            </div>
        </section>

        <div class="rs-body">
            <!-- 左栏：难度、测试点分布、提交信息 -->
            <div class="rs-col">
                <div class="rs-panel">
                    <div class="rs-panel-head"><span>难度 / Star Rating</span><b><?= htmlspecialchars($difficultyMain) ?></b></div>
                    <div class="rs-big"><?= htmlspecialchars(number_format($quality, 0)) ?><small>Q</small></div>
                    <div class="rs-meter rs-meter-warm"><i style="width:<?= round($qualityBar, 2) ?>%"></i></div>
                    <div class="rs-sub">题目质量由最高难度标签计算，用作本次结算的星级参考。</div>
                </div>

                <div class="rs-panel">
                    <div class="rs-panel-head"><span>测试点分布</span><b><?= $passed ?> / <?= $total ?></b></div>
                    <?php if ($cases): ?>
                    <div class="rs-case-strip">
                        <?php foreach ($cases as $i => $c):
                            $v = strtolower(strtoupper((string)($c['verdict'] ?? 'UK')));
                            $v = in_array($v, ['ac','wa','tle','mle','re','se'], true) ? $v : 'uk';
                        ?>
                        <i class="rs-case-<?= $v ?>" title="测试点 #<?= $i + 1 ?> · <?= htmlspecialchars(strtoupper((string)($c['verdict'] ?? 'UK'))) ?>"></i>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="rs-sub">暂无测试点数据。</div>
                    <?php endif; ?>
                    <div class="rs-split">
                        <span>通过率 <b><?= $accDisplay ?>%</b></span>
                        <span>未通过 <b><?= max(0, $total - $passed) ?></b></span>
                    </div>
                </div>

                <div class="rs-panel">
                    <div class="rs-panel-head"><span>提交信息</span><b>#<?= $idDisplay ?></b></div>
                    <div class="rs-stat-grid">
                        <div class="rs-stat"><span>语言</span><b><?= htmlspecialchars($row['judge_lang'] ?: '—') ?></b></div>
                        <div class="rs-stat"><span>代码长度</span><b><?= number_format(mb_strlen((string)$row['code'])) ?> B</b></div>
                        <div class="rs-stat"><span>题目编号</span><b>#<?= htmlspecialchars((string)$row['problem_id']) ?></b></div>
                        <div class="rs-stat"><span>测试点</span><b><?= $passed ?> / <?= $total ?></b></div>
                        <div class="rs-stat"><span>提交时间</span><b><?= htmlspecialchars($row['created_at'] ? date('m-d H:i', strtotime($row['created_at'])) : '—') ?></b></div>
                        <div class="rs-stat"><span>评测类型</span><b><?= $row['judge_type'] === 'standard' ? '标准评测' : htmlspecialchars((string)$row['judge_type']) ?></b></div>
                        <div class="rs-stat"><span>比赛</span><b><?= (int)($row['contest_id'] ?? 0) > 0 ? '#' . (int)$row['contest_id'] : '—' ?></b></div>
                        <div class="rs-stat"><span>单点分值</span><b><?= htmlspecialchars(rtrim(rtrim(number_format($caseScore, 2), '0'), '.')) ?></b></div>
                    </div>
                </div>
            </div>

            <!-- 中栏：题目、操作、测试点明细 -->
            <div class="rs-col rs-col-center">
                <div class="rs-panel rs-problem-panel">
                    <div class="rs-problem-top">
                        <span class="rs-pid">PROBLEM #<?= htmlspecialchars((string)$row['problem_id']) ?></span>
                        <span class="rs-verdict-pill rs-pill-<?= htmlspecialchars($verdictClass) ?>"><?= htmlspecialchars($verdict) ?></span>
                    </div>
                    <h1><?= htmlspecialchars($problemTitle) ?></h1>
                    <div class="rs-byline">by <?= htmlspecialchars($problemAuthor) ?> · <?= htmlspecialchars($row['created_at'] ?? '') ?></div>
                    <div class="rs-tags">
                        <?php foreach ($diffs as $d): ?>
                        <span class="rs-tag rs-tag-diff"><?= htmlspecialchars(difficulty_label((string)$d['category'], (int)$d['level'], (string)$d['position'])) ?></span>
                        <?php endforeach; ?>
                        <?php foreach ($problemTags as $tag): ?>
                        <span class="rs-tag"><?= htmlspecialchars(trim($tag)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="rs-actions">
                        <?php if ($sourceAllowed): ?><a href="/source.php?id=<?= $id ?>" class="rs-btn"><i class="fa-solid fa-code"></i> 查看源代码</a><?php endif; ?>
                        <a href="/status.php" class="rs-btn"><i class="fa-solid fa-list"></i> 评测状态</a>
                        <a href="/problem.php?id=<?= urlencode((string)$row['problem_id']) ?>" class="rs-btn rs-btn-primary"><i class="fa-solid fa-rotate-right"></i> 再试一次</a>
                    </div>
                </div>

                <div class="rs-panel rs-cases-panel">
                    <div class="rs-panel-head"><span>测试点明细</span><b>共 <?= $total ?> 个</b></div>
                    <?php if ($row['status'] === 'failed' && trim((string)$row['result_json']) !== ''): ?>
                    <div class="rs-error"><?= htmlspecialchars((string)$row['result_json']) ?></div>
                    <?php elseif ($cases): ?>
                    <div class="rs-case-grid">
                        <?php foreach ($cases as $i => $c):
                            $v = strtoupper((string)($c['verdict'] ?? 'UK'));
                            $vc = strtolower($v);
                            $vName = ['AC' => 'Accepted', 'WA' => 'Wrong Answer', 'TLE' => 'Time Limit', 'MLE' => 'Memory Limit', 'RE' => 'Runtime Error', 'SE' => 'System Error'][$v] ?? $v;
                        ?>
                        <div class="rs-case rs-case-<?= htmlspecialchars($vc) ?>">
                            <span class="rs-case-idx">#<?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="rs-case-status" title="<?= htmlspecialchars($vName) ?>"><?= htmlspecialchars($v) ?> · <?= htmlspecialchars($vName) ?></span>
                            <span class="rs-case-score"><?= $v === 'AC' ? htmlspecialchars(rtrim(rtrim(number_format($caseScore, 1), '0'), '.')) : '0' ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="rs-empty"><?= $row['status'] === 'pending' || $row['status'] === 'judging' ? '评测进行中…' : '暂无测试点详情' ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 右栏：Rating、得分、准确率、判定 -->
            <div class="rs-col">
                <div class="rs-panel">
                    <div class="rs-panel-head"><span>Rating</span><b><?= htmlspecialchars($tierLabel) ?></b></div>
                    <div class="rs-big" style="color:<?= htmlspecialchars($ratingColor) ?>"><?= $rating ?></div>
                    <div class="rs-meter"><i style="width:<?= round($tierProgress, 2) ?>%;background:<?= htmlspecialchars($ratingColor) ?>"></i></div>
                    <div class="rs-sub">当前段位 <?= htmlspecialchars($tierLabel) ?>，继续积累 Rating 向下一段位前进。</div>
                </div>

                <div class="rs-panel">
                    <div class="rs-panel-head"><span>Score</span><b>#<?= $idDisplay ?></b></div>
                    <div class="rs-big"><?= htmlspecialchars($scoreDisplay) ?><small>/ 100</small></div>
                    <div class="rs-sub">提交编号 <?= $id ?> · <?= $total > 0 ? $passed . ' / ' . $total . ' 个测试点通过' : '等待测试点结果' ?></div>
                    <div class="rs-acc-grid">
                        <div class="rs-acc"><span>Accuracy</span><b><?= $accDisplay ?>%</b></div>
                        <div class="rs-acc"><span>Passed</span><b><?= $passed ?> / <?= $total ?></b></div>
                    </div>
                </div>

                <div class="rs-panel">
                    <div class="rs-panel-head"><span>Judgment</span><b>MAX <?= $judgeMax ?></b></div>
                    <?php if ($cases): ?>
                        <?php foreach ($judgeVisible as $j): ?>
                        <div class="rs-jrow" title="<?= htmlspecialchars($judgeNames[$j]) ?>">
                            <span><?= $j ?></span>
                            <div class="rs-jbar"><i class="rs-j-<?= strtolower($j) ?>" style="width:<?= round($judgeCounts[$j] / $judgeMax * 100, 2) ?>%"></i></div>
                            <b><?= $judgeCounts[$j] ?></b>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="rs-empty">暂无判定数据</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="rs-footer">
            <span>AirOJ · 评测结算</span>
            <span><?= htmlspecialchars($row['created_at'] ?? '') ?> · #<?= $idDisplay ?></span>
        </div>
    </div>
</div>
