<style>
.help-toc { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.help-toc a { padding: 4px 12px; border-radius: 999px; background: var(--bg-secondary); border: 1px solid var(--border); font-size: 12px; }
.help-sec { scroll-margin-top: 70px; }
.help-sec h2 { display: flex; align-items: center; gap: 8px; }
.help-table td:first-child { white-space: nowrap; font-weight: 600; }
.help-formula { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 16px; font-family: "JetBrains Mono", Consolas, monospace; font-size: 12px; line-height: 1.9; overflow-x: auto; white-space: pre; }
</style>

<div class="page-header"><h1><i class="fa-solid fa-circle-question"></i> 帮助</h1></div>

<div class="help-toc">
    <a href="#start">快速开始</a>
    <a href="#judge">评测结果</a>
    <a href="#difficulty">难度分级</a>
    <a href="#rating">Rating 与名字颜色</a>
    <a href="#contest">比赛</a>
    <a href="#team">团队与讨论区</a>
    <a href="#faq">常见问题</a>
</div>

<div class="card help-sec" id="start">
    <h2><i class="fa-solid fa-rocket"></i> 快速开始</h2>
    <ol style="padding-left: 20px; color: var(--text-secondary); line-height: 2; font-size: 14px">
        <li>注册账号并登录（可在「账号设置」中开启二步验证）。</li>
        <li>进入「题目」列表，点击题目名称打开题目页。</li>
        <li>在右侧编辑器中选择语言、编写代码，确认测试数据后点击「提交」。</li>
        <li>评测完成后会自动跳转到结果页，显示每个测试点的通过情况。</li>
        <li>在「状态」页可以按题目、用户、语言和结果筛选提交记录。</li>
    </ol>
    <p style="margin-top:10px">题目页左侧是题面，右侧是代码编辑器；编辑器下方是可拖动调整高度的「测试」面板，可以自定义输入并运行代码（Ctrl+Enter 快捷运行）。</p>
</div>

<div class="card help-sec" id="judge">
    <h2><i class="fa-solid fa-bolt"></i> 评测结果</h2>
    <div class="table-wrap"><table class="help-table">
        <tbody>
        <tr><td><span class="badge badge-ac">AC</span></td><td>Accepted —— 答案正确，通过全部测试点。</td></tr>
        <tr><td><span class="badge badge-wa">WA</span></td><td>Wrong Answer —— 输出与标准答案不一致。</td></tr>
        <tr><td><span class="badge badge-tle">TLE</span></td><td>Time Limit Exceeded —— 运行超时。</td></tr>
        <tr><td><span class="badge badge-mle">MLE</span></td><td>Memory Limit Exceeded —— 超出内存限制。</td></tr>
        <tr><td><span class="badge badge-re">RE</span></td><td>Runtime Error —— 运行时错误（数组越界、除零、非零退出等）。</td></tr>
        <tr><td><span class="badge badge-se">SE</span></td><td>System Error —— 评测系统错误，可稍后重试。</td></tr>
        <tr><td><span class="badge badge-uk">CE</span></td><td>Compile Error —— 编译错误，结果页会显示编译器输出。</td></tr>
        </tbody>
    </table></div>
    <p style="margin-top:10px">支持 C、C++、Python 3、Java、Go、JavaScript。所有题目按测试点计分，全部通过即为 AC。</p>
</div>

<div class="card help-sec" id="difficulty">
    <h2><i class="fa-solid fa-gauge-high"></i> 难度分级</h2>
    <p>为使用户确定自身的实力以及做题等级，AirOJ 建立了题目分级体系。</p>

    <h3 style="margin-top:16px">等级与分类</h3>
    <p>难度共分为 <strong>1 ~ 15 级</strong>，由出题人选择，分为五个类别：</p>
    <div class="table-wrap"><table class="help-table">
        <thead><tr><th>类别</th><th>可选等级</th></tr></thead>
        <tbody>
        <tr><td><span class="diff-tag diff-basic">BASIC</span></td><td>1 ~ 7 级</td></tr>
        <tr><td><span class="diff-tag diff-advanced">ADVANCED</span></td><td>4 ~ 12 级</td></tr>
        <tr><td><span class="diff-tag diff-expert">EXPERT</span></td><td>10 ~ 13 级</td></tr>
        <tr><td><span class="diff-tag diff-master">MASTER</span></td><td>12 ~ 14 级</td></tr>
        <tr><td><span class="diff-tag diff-remaster">Re:MASTER</span></td><td>14 ~ 15 级</td></tr>
        </tbody>
    </table></div>

    <h3 style="margin-top:16px">上位 / 中位 / 下位</h3>
    <p>同一等级内还会区分精细位置：</p>
    <ul style="padding-left: 20px; color: var(--text-secondary); line-height: 1.9">
        <li><strong>下位</strong>：X.0 ~ X.4</li>
        <li><strong>中位</strong>：X.5</li>
        <li><strong>上位</strong>：X.6 ~ X.9（如 15 级上位为 15.6、15.7、15.8、15.9）</li>
    </ul>
    <p>题目列表与题目页只显示「等级 + 上/中/下位」，不显示精细分数。一道题目可以挂多个<strong>难度版本</strong>：每个版本拥有独立的题面、测试数据与 Special Judge，共享同一个标题，题目页会显示难度切换标签；题目分值与越级判定按当前选择的版本计算。</p>

    <h3 style="margin-top:16px">颜色分级与可做题范围</h3>
    <div class="table-wrap"><table class="help-table">
        <thead><tr><th>名字颜色</th><th>Rating 区间</th><th>可覆盖难度</th></tr></thead>
        <tbody>
        <tr><td style="color:#22c55e">绿名</td><td>0 ~ 3000</td><td><span class="diff-tag diff-basic">BASIC</span><span class="diff-tag diff-advanced">ADVANCED</span></td></tr>
        <tr><td style="color:#eab308">黄名</td><td>3000 ~ 9000</td><td>绿名 + <span class="diff-tag diff-expert">EXPERT</span></td></tr>
        <tr><td style="color:#3b82f6">蓝名</td><td>9000 ~ 12000</td><td>黄名 + <span class="diff-tag diff-master">MASTER</span></td></tr>
        <tr><td style="color:#ef4444">红名</td><td>12000 ~ 15000</td><td>蓝名 + <span class="diff-tag diff-remaster">Re:MASTER</span> 14 级</td></tr>
        <tr><td style="background:<?= rainbow_gradient_css() ?>;-webkit-background-clip:text;background-clip:text;color:transparent;font-weight:700">彩虹名</td><td>15000 ~ 20000</td><td>红名 + <span class="diff-tag diff-remaster">Re:MASTER</span> 15 级</td></tr>
        </tbody>
    </table></div>

    <h3 style="margin-top:16px">越级做题</h3>
    <p>作答超出当前颜色分级的题目时，题目页右上角会提示：</p>
    <blockquote style="margin:10px 0;padding:10px 16px;border-left:3px solid var(--accent-gold);background:var(--bg-secondary);color:var(--text-secondary);font-size:13px">
        该题目也许超出您能力范围，若您能力范围支持做此题，在您通过本题后您的 Rating 将会提升到（题目所在颜色分级最低积分 - 500）。
    </blockquote>
    <p><span class="diff-tag diff-remaster">Re:MASTER</span> <strong>15 级题目不允许越级作答</strong>，低颜色分级用户无法提交。</p>
</div>

<div class="card help-sec" id="rating">
    <h2><i class="fa-solid fa-chart-line"></i> Rating 与名字颜色</h2>
    <p>Rating 初始为 0 分（未定级用户按 1500 参与计算），最高 20000 分。首次通过一道有难度标签的题目时会按以下公式更新：</p>
    <div class="help-formula">L    = ℓ + s / 10,  s ∈ {0,1,…,9}          // 难度精确值
Q    = Q_lo + (L - L_lo) / (L_hi - L_lo) × (Q_hi - Q_lo)
Q_p  = max{ Q(τ) | τ ∈ tags(P) }               // 题目分值取最高难度
p    = (n_AC + 1) / (n_attempt + 2)            // 该题通过率
Q_eff = Q_p + clip(300 × log10((1-p)/p), -300, 300)
E    = 1 / (1 + 10^((Q_eff - R) / 800))        // 期望胜率
K_u  = 40 (n_AC < 30) / 30 (30 ≤ n_AC ≤ 100) / 20 (n_AC > 100)
K_eff = K_u × (1 + min(4, max(0, (Q_eff - R) / 4000)))
η    = max(0.5, 1 - 0.03 × n_WA)               // 错误惩罚
ΔR   = K_eff × (1 - E) × η
R'   = clip(R + ΔR, 0, 20000)</div>
    <p style="margin-top:10px">
        <code>n_AC</code> 为用户已通过题数，<code>n_attempt</code> / <code>n_WA</code> 为本题此前的提交与错误次数。
        越级通过题目时，Rating 最高只能提升到「题目所在颜色分级最低积分 - 500」。<br>
        例如 MASTER 题目对应蓝色分级（9000 ~ 12000），越级通过后 Rating 最高到 8500。
    </p>

    <h3 style="margin-top:16px">名字颜色规则</h3>
    <ul style="padding-left:20px;color:var(--text-secondary);line-height:1.9">
        <li>普通用户默认按 Rating 显示绿 / 黄 / 蓝 / 红 / 彩虹名。</li>
        <li><strong>管理员 / 版主</strong>可在「账号设置 → 主题」中自行选择：Rating 分级颜色、管理颜色、平分（左 Rating / 右管理），或管理专属的<strong>特殊四色渐变</strong>。</li>
        <li>管理员还可以为任意用户指定专属名字颜色（紫色 / 粉色 / 灰色 / 特殊渐变）。</li>
        <li>为避免与 Rating 分级混淆，<strong>不允许选择与绿、黄、蓝、红相近的颜色</strong>。</li>
        <li><strong>作弊者</strong>只显示棕色名字，不参与颜色分级。</li>
    </ul>
</div>

<div class="card help-sec" id="contest">
    <h2><i class="fa-solid fa-flag-checkered"></i> 比赛</h2>
    <ul style="padding-left:20px;color:var(--text-secondary);line-height:1.9">
        <li>比赛分为 ICPC 赛制（按通过题数与罚时）和 OI 赛制（按每题最高分求和）。</li>
        <li>比赛开始前题目列表不会公布；进行中从比赛进入题目提交，提交会标记比赛来源。</li>
        <li>排名只统计比赛时间窗内、从比赛入口提交的记录。</li>
    </ul>
</div>

<div class="card help-sec" id="team">
    <h2><i class="fa-solid fa-users"></i> 团队与讨论区</h2>
    <ul style="padding-left:20px;color:var(--text-secondary);line-height:1.9">
        <li>团队支持头像、短介绍、邀请成员、踢出成员、转让与解散。</li>
        <li>团队所有者可以提交认证申请（需填写申请理由），由管理员审核；认证通过后显示认证徽章。</li>
        <li>讨论区支持 Markdown，可以在题目页点击「讨论」进入该题的讨论区。</li>
        <li>题解同样支持 Markdown，可在题目页点击「题解」查看或撰写。</li>
    </ul>
</div>

<div class="card help-sec" id="faq">
    <h2><i class="fa-solid fa-circle-question"></i> 常见问题</h2>
    <h3>提交后一直显示「评测中」？</h3>
    <p>评测在服务器端同步执行，通常几秒内完成；若长时间未更新，刷新结果页即可。</p>
    <h3>为什么我的程序在本地能过，提交却 WA？</h3>
    <p>请检查是否使用了未定义行为、多组数据读取方式，以及是否输出了多余的空格或空行。题目的测试数据可能包含边界情况。</p>
    <h3>可以查看测试数据吗？</h3>
    <p>题目页的测试面板会载入第一组测试数据便于调试，其余测试点不公开。</p>
    <h3>颜色什么时候更新？</h3>
    <p>首次通过题目后立即更新，刷新页面即可看到新的名字颜色。</p>
</div>

