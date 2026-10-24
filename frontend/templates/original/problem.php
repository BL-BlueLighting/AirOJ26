<?php if ($tierInfo['warn']): ?>
<div class="tier-warning<?= $tierInfo['blocked'] ? ' tier-blocked' : '' ?>">
    <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($tierInfo['message']) ?>
</div>
<?php endif; ?>
<div class="pw" id="pw">

    <!-- ═══ 左栏：题目 ═══ -->
    <section class="pw-left">
        <div class="pw-pane-head">
            <span><i class="fa-solid fa-file-lines"></i> <?= $problem ? htmlspecialchars($problem['title']) : 'Problem ' . htmlspecialchars($pid) ?></span>
            <span class="pid">#<?= htmlspecialchars((string)$mainId) ?></span>
            <span class="pw-spacer"></span>
            <a href="/bbs.php?pid=<?= htmlspecialchars($pid) ?>" class="btn btn-outline btn-xs"><i class="fa-solid fa-comments"></i> 讨论</a>
            <a href="/solutions.php?id=<?= htmlspecialchars($pid) ?>" class="btn btn-outline btn-xs"><i class="fa-solid fa-pen-to-square"></i> 题解</a>
        </div>
        <?php if ($isGroup): ?>
        <div class="pw-variants" title="同一道题的多个难度版本">
            <?php foreach ($groupMembers as $m):
                $mv = (int)$m['problem_id'];
                $mdiff = problem_difficulties((string)$mv);
                $mlabel = $mdiff ? difficulty_label($mdiff[0]['category'], (int)$mdiff[0]['level'], $mdiff[0]['position']) : ('版本 ' . $mv);
            ?>
            <a class="pw-variant <?= $mv === (int)$problem['problem_id'] ? 'active' : '' ?>"
               href="/problem.php?id=<?= $mainId ?>&v=<?= $mv ?><?= $cid ? '&cid=' . $cid : '' ?>"><?= htmlspecialchars($mlabel) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="pw-meta">
            <?php if ($problem && ($problem['author'] ?? '') !== ''): ?>
            <span><i class="fa-solid fa-user"></i> 出题人：<?= user_role_html($problem['author']) ?></span>
            <?php endif; ?>
            <span><i class="fa-solid fa-circle-check" style="color:var(--green)"></i> 通过 <?= (int)$acStats['ac'] ?></span>
            <span><i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i> 未通过 <?= (int)$acStats['failed'] ?></span>
            <?php foreach ($diffs as $d): ?>
            <span class="pw-diff"><i class="fa-solid fa-gauge-high"></i> <?= htmlspecialchars(difficulty_label($d['category'], (int)$d['level'], $d['position'])) ?></span>
            <?php endforeach; ?>
            <?php foreach ($tags as $tag): ?>
            <a class="tag tag-blue" href="/problems.php?q=<?= urlencode($tag) ?>">#<?= htmlspecialchars($tag) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($contest): ?>
        <div style="padding:6px 16px;background:var(--bg-hover);border-bottom:1px solid var(--border);font-size:12px;color:var(--text-secondary)">
            <i class="fa-solid fa-flag-checkered"></i> 比赛：<a href="/contest_detail.php?cid=<?= $cid ?>"><?= htmlspecialchars($contest['title']) ?></a>
            · <?= contest_status($contest) ?>
        </div>
        <?php endif; ?>
        <div class="pw-scroll">
            <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

            <?php if ($problem): ?>
                <div class="problem-section">
                    <h3><i class="fa-solid fa-pen-to-square"></i> 题目描述</h3>
                    <?= md($problem['description'] ?? '', $pd) ?>
                </div>
                <div class="problem-section">
                    <h3><i class="fa-solid fa-right-to-bracket"></i> 输入格式</h3>
                    <?= md($problem['input'] ?? '', $pd) ?>
                </div>
                <div class="problem-section">
                    <h3><i class="fa-solid fa-right-from-bracket"></i> 输出格式</h3>
                    <?= md($problem['output'] ?? '', $pd) ?>
                </div>
                <?php if (!empty($problem['sample_input']) || !empty($problem['sample_output'])): ?>
                <div class="problem-section">
                    <h3><i class="fa-solid fa-clipboard-list"></i> 样例</h3>
                    <?php if (!empty($problem['sample_input'])): ?>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">输入</div>
                    <div class="sample-box"><?= htmlspecialchars($problem['sample_input']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($problem['sample_output'])): ?>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">输出</div>
                    <div class="sample-box"><?= htmlspecialchars($problem['sample_output']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($problem['hint'])): ?>
                <div class="problem-section">
                    <h3><i class="fa-solid fa-lightbulb"></i> 提示</h3>
                    <?= md($problem['hint'], $pd) ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="problem-section">
                    <h3>题目描述</h3>
                    <p style="color:var(--text-muted)">请编写程序解决本题。评测系统将使用存储的测试数据对你的代码进行测试。</p>
                </div>
            <?php endif; ?>

            <div style="font-size:12px;color:var(--text-muted);margin-top:16px;padding-top:12px;border-top:1px solid var(--border)">
                测试点数量: <strong><?= $caseCount ?></strong>
                · <a href="/status.php?pid=<?= htmlspecialchars($pid) ?>">本题提交记录</a>
            </div>
        </div>
    </section>

    <div class="pw-vsplit" id="vsplit" title="拖动调整左右宽度"></div>

    <!-- ═══ 右栏：编辑器 + 测试 ═══ -->
    <section class="pw-right" id="pw-right">
        <form method="post" action="/submit.php" id="submit-form" class="pw-form" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="pid" value="<?= htmlspecialchars($pid) ?>">
            <input type="hidden" name="judge_type" value="standard">
            <?php if ($cid): ?><input type="hidden" name="cid" value="<?= $cid ?>"><?php endif; ?>
            <textarea name="source" id="source" style="display:none"></textarea>
            <input type="file" id="source-file" name="source_file" style="display:none"
                   accept=".c,.h,.cpp,.cc,.cxx,.hpp,.py,.java,.go,.js">

            <div class="pw-toolbar">
                <select name="language" id="code-lang" class="form-input">
                    <option value="">— 选择语言 —</option>
                    <?php foreach ($langs as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v['name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-outline" id="btn-upload"><i class="fa-solid fa-file-lines"></i> 上传代码</button>
                <span class="pw-spacer"></span>
                <button type="button" class="btn btn-outline" id="btn-layout" title="切换双栏 / 全屏"><i class="fa-solid fa-expand"></i> 全屏</button>
                <button type="submit" class="btn btn-primary" <?= $tierInfo['blocked'] ? 'disabled title="不允许越级作答"' : '' ?>><i class="fa-solid fa-bolt"></i> 提交</button>
            </div>

            <div class="pw-editor" id="editor-wrap"></div>
        </form>

        <div class="pw-hsplit" id="hsplit" title="拖动调整测试区高度"></div>

        <div class="pw-test" id="pw-test">
            <div class="pw-test-head">
                <span><i class="fa-solid fa-flask"></i> 测试</span>
                <?php if ($firstCase): ?>
                <span style="font-weight:400;color:var(--text-muted)">已载入第 <?= htmlspecialchars($firstCase['name']) ?> 组测试数据</span>
                <?php else: ?>
                <span style="font-weight:400;color:var(--text-muted)">本题暂无测试数据</span>
                <?php endif; ?>
                <span class="pw-spacer"></span>
                <span class="pw-run-status" id="run-status"></span>
                <button type="button" class="btn btn-outline" id="btn-clear">清空输入</button>
                <button type="button" class="btn btn-outline" id="btn-toggle-test">收起</button>
                <button type="button" class="btn btn-primary" id="btn-run"><i class="fa-solid fa-play"></i> 运行</button>
            </div>
            <div class="pw-test-body">
                <div class="pw-test-pane">
                    <div class="pw-pane-label">输入（自定义）</div>
                    <textarea id="test-input" spellcheck="false"><?= htmlspecialchars($firstCase['input'] ?? '') ?></textarea>
                </div>
                <div class="pw-test-pane">
                    <div class="pw-pane-label">输出</div>
                    <pre id="test-output"></pre>
                </div>
            </div>
        </div>
    </section>
</div>

<link rel="stylesheet" href="/js/monaco.css?v=2">
<script type="module" src="/js/monaco.js?v=2"></script>
<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var LANG_MAP = { c: 'c', cpp: 'cpp', python3: 'python', java: 'java', go: 'go', javascript: 'javascript' };
    var EXT_MAP = { c: 'c', h: 'c', cpp: 'cpp', cc: 'cpp', cxx: 'cpp', hpp: 'cpp', py: 'python3', java: 'java', go: 'go', js: 'javascript' };
    var PID = <?= json_encode((string)$pid) ?>;

    var langSel = $('code-lang'), srcTa = $('source'), fileInput = $('source-file');
    var inputTa = $('test-input'), outPre = $('test-output'), runStatus = $('run-status');
    var runBtn = $('btn-run'), editor = null, running = false;

    // ---- 提交 ----
    $('submit-form').addEventListener('submit', function (e) {
        if (editor) srcTa.value = editor.getValue();
        if (!srcTa.value.trim()) {
            e.preventDefault();
            alert('请先编写代码或上传文件');
        }
    });

    // ---- 上传代码文件 ----
    $('btn-upload').addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        var f = fileInput.files[0];
        if (!f) return;
        var reader = new FileReader();
        reader.onload = function () {
            srcTa.value = reader.result;
            if (editor) editor.setValue(reader.result);
        };
        reader.readAsText(f);
        var ext = (f.name.split('.').pop() || '').toLowerCase();
        if (EXT_MAP[ext]) { langSel.value = EXT_MAP[ext]; syncLang(); }
    });

    // ---- 语言同步 ----
    function syncLang() {
        if (editor && LANG_MAP[langSel.value]) {
            monaco.editor.setModelLanguage(editor.getModel(), LANG_MAP[langSel.value]);
        }
    }
    langSel.addEventListener('change', syncLang);

    // ---- 布局切换：双栏 / 全屏 ----
    var layoutBtn = $('btn-layout');
    function setLayout(focus) {
        document.body.classList.toggle('pw-focus', focus);
        document.cookie = 'pw_layout=' + (focus ? 'focus' : 'dual') + ';path=/;max-age=31536000';
        layoutBtn.innerHTML = focus ? '<i class="fa-solid fa-table-columns"></i> 双栏' : '<i class="fa-solid fa-expand"></i> 全屏';
        if (editor) setTimeout(function () { editor.layout(); }, 60);
    }
    layoutBtn.addEventListener('click', function () {
        setLayout(!document.body.classList.contains('pw-focus'));
    });
    setLayout(document.body.classList.contains('pw-focus'));

    // ---- 拖拽：左右分栏 ----
    var page = $('pw'), vsplit = $('vsplit'), vDrag = false;
    var savedLeft = localStorage.getItem('pw_left');
    if (savedLeft) page.style.setProperty('--pw-left', savedLeft + '%');
    vsplit.addEventListener('pointerdown', function (e) {
        vDrag = true; vsplit.classList.add('dragging');
        vsplit.setPointerCapture(e.pointerId); e.preventDefault();
    });
    vsplit.addEventListener('pointermove', function (e) {
        if (!vDrag) return;
        var rect = page.getBoundingClientRect();
        var pct = Math.min(75, Math.max(20, (e.clientX - rect.left) / rect.width * 100));
        page.style.setProperty('--pw-left', pct + '%');
        localStorage.setItem('pw_left', pct);
    });
    vsplit.addEventListener('pointerup', function () { vDrag = false; vsplit.classList.remove('dragging'); });

    // ---- 拖拽：编辑器 / 测试区分隔 ----
    var right = $('pw-right'), testPane = $('pw-test'), hsplit = $('hsplit'), hDrag = false;
    var savedTestH = parseInt(localStorage.getItem('pw_test_h') || '0', 10);
    if (savedTestH >= 60) testPane.style.height = savedTestH + 'px';
    hsplit.addEventListener('pointerdown', function (e) {
        hDrag = true; hsplit.classList.add('dragging');
        hsplit.setPointerCapture(e.pointerId); e.preventDefault();
    });
    hsplit.addEventListener('pointermove', function (e) {
        if (!hDrag) return;
        var rect = right.getBoundingClientRect();
        var h = Math.min(rect.height - 120, Math.max(60, rect.bottom - e.clientY));
        testPane.style.height = h + 'px';
        localStorage.setItem('pw_test_h', h);
    });
    hsplit.addEventListener('pointerup', function () { hDrag = false; hsplit.classList.remove('dragging'); });

    // ---- 测试区收起 / 展开 ----
    testPane.classList.toggle('collapsed', localStorage.getItem('pw_test_collapsed') === '1');
    $('btn-toggle-test').innerHTML = testPane.classList.contains('collapsed') ? '<i class="fa-solid fa-chevron-down"></i> 展开' : '<i class="fa-solid fa-chevron-up"></i> 收起';
    $('btn-toggle-test').addEventListener('click', function () {
        var c = testPane.classList.toggle('collapsed');
        localStorage.setItem('pw_test_collapsed', c ? '1' : '0');
        this.innerHTML = c ? '<i class="fa-solid fa-chevron-down"></i> 展开' : '<i class="fa-solid fa-chevron-up"></i> 收起';
    });
    $('btn-clear').addEventListener('click', function () {
        inputTa.value = '';
        outPre.textContent = '';
        setStatus('', '');
    });

    // ---- 运行测试 ----
    function setStatus(text, cls) {
        runStatus.innerHTML = text;
        runStatus.className = 'pw-run-status' + (cls ? ' ' + cls : '');
    }
    function runTest() {
        if (running) return;
        var code = editor ? editor.getValue() : srcTa.value;
        if (!code.trim()) { setStatus('请先编写代码', 'err'); return; }
        if (!langSel.value) { setStatus('请先选择语言', 'err'); return; }

        running = true; runBtn.disabled = true; runBtn.innerHTML = '<i class="fa-solid fa-hourglass-half"></i> 运行中';
        setStatus('正在运行...', '');
        outPre.textContent = '';

        fetch('/run.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pid: PID, lang: langSel.value, code: code, input: inputTa.value })
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.ok) {
                setStatus(res.compile_error ? '<i class="fa-solid fa-circle-xmark"></i> 编译错误' : '<i class="fa-solid fa-triangle-exclamation"></i> 运行失败', 'err');
                outPre.textContent = res.error || '未知错误';
                return;
            }
            var time = res.real_time + ' ms · ' + (res.memory / 1048576).toFixed(1) + ' MB';
            if (res.verdict === 'OK') setStatus('<i class="fa-solid fa-circle-check"></i> 运行完成 · ' + time, 'ok');
            else setStatus('<i class="fa-solid fa-triangle-exclamation"></i> ' + res.verdict + ' · ' + time, 'err');
            outPre.textContent = res.output || '（无输出）';
        }).catch(function (e) {
            setStatus('<i class="fa-solid fa-triangle-exclamation"></i> 请求失败', 'err');
            outPre.textContent = String(e);
        }).finally(function () {
            running = false; runBtn.disabled = false; runBtn.innerHTML = '<i class="fa-solid fa-play"></i> 运行';
        });
    }
    runBtn.addEventListener('click', runTest);

    // ---- C / C++ 自动补全（关键字 / 标准库 / STL 成员 / 代码片段）----
    var CPP_KEYWORDS = 'alignas alignof auto bool break case catch char class const constexpr continue decltype default delete do double else enum explicit export extern false float for friend goto if inline int long mutable namespace new noexcept nullptr operator private protected public register return short signed sizeof static struct switch template this throw true try typedef typename union unsigned using virtual void volatile while'.split(' ');
    // 预处理指令：label 不能以 # 开头（Monaco 会把 # 当分词符丢弃该补全项），
    // 因此插入文本带 #，并把补全范围扩展到已输入的 #
    var CPP_PREPROC = [
        ['include', '#include <${1:bits/stdc++.h}>', '包含头文件'],
        ['define',  '#define ${1:NAME} ${2:value}', '宏定义'],
        ['ifdef',   '#ifdef ${1:MACRO}\n\t${2:/* code */}\n#endif', '条件编译'],
        ['ifndef',  '#ifndef ${1:MACRO}\n\t${2:/* code */}\n#endif', '条件编译'],
        ['endif',   '#endif', '结束条件编译'],
        ['undef',   '#undef ${1:MACRO}', '取消宏定义'],
        ['pragma',  '#pragma ${1:GCC optimize("O2")}', '编译指示']
    ];
    var CPP_STD = [
        ['printf', '格式化输出'], ['scanf', '格式化输入'], ['puts', '输出字符串'], ['putchar', '输出字符'], ['getchar', '读入字符'],
        ['malloc', '分配内存'], ['free', '释放内存'], ['memset', '内存赋值'], ['memcpy', '内存拷贝'], ['strlen', '字符串长度'],
        ['strcmp', '字符串比较'], ['strcpy', '字符串复制'], ['strcat', '字符串拼接'], ['abs', '绝对值'], ['fabs', '浮点绝对值'],
        ['sqrt', '平方根'], ['pow', '幂'], ['floor', '向下取整'], ['ceil', '向上取整'], ['round', '四舍五入'],
        ['log', '自然对数'], ['log2', '以 2 为底对数'], ['exp', '指数'], ['sin', '正弦'], ['cos', '余弦'],
        ['sort', '排序'], ['stable_sort', '稳定排序'], ['reverse', '反转'], ['unique', '去重'], ['lower_bound', '二分下界'],
        ['upper_bound', '二分上界'], ['binary_search', '二分查找'], ['min', '最小值'], ['max', '最大值'], ['swap', '交换'],
        ['__gcd', '最大公约数'], ['next_permutation', '下一个排列'], ['fill', '填充'], ['count', '计数'], ['find', '查找'],
        ['push_back', '尾部插入'], ['pop_back', '尾部删除'], ['emplace_back', '原位构造插入'], ['size', '大小'], ['empty', '是否为空'],
        ['begin', '起始迭代器'], ['end', '结束迭代器'], ['clear', '清空'], ['erase', '删除'], ['insert', '插入'],
        ['front', '首元素'], ['back', '尾元素'], ['push', '入栈/入队'], ['pop', '出栈/出队'], ['top', '栈顶'],
        ['substr', '子串'], ['length', '长度'], ['c_str', 'C 字符串'], ['string', '字符串类'], ['vector', '动态数组'],
        ['map', '有序映射'], ['unordered_map', '哈希映射'], ['set', '有序集合'], ['unordered_set', '哈希集合'],
        ['queue', '队列'], ['priority_queue', '优先队列'], ['stack', '栈'], ['deque', '双端队列'], ['pair', '二元组'],
        ['tuple', '多元组'], ['array', '定长数组'], ['bitset', '位集合'], ['complex', '复数'], ['list', '链表'],
        ['cout', '标准输出'], ['cin', '标准输入'], ['endl', '换行并刷新'], ['cerr', '标准错误'], ['ios', 'IO 流设置'],
        ['max_element', '区间最大值'], ['min_element', '区间最小值'], ['accumulate', '区间求和'], ['iota', '递增填充'], ['to_string', '转字符串'],
        ['stoi', '字符串转整数'], ['stoll', '字符串转长整数'], ['stod', '字符串转浮点'], ['sqrtl', '长双精度平方根'], ['hypot', '欧氏距离']
    ];
    var CPP_STD_NOT_CALL = ('string vector map unordered_map set unordered_set queue priority_queue stack deque pair tuple array bitset complex list cout cin cerr endl ios').split(' ');
    var CPP_SNIPPETS = [
        ['main', 'C++ main 模板（含快读）', '#include <bits/stdc++.h>\nusing namespace std;\n\nint main() {\n\tios::sync_with_stdio(false);\n\tcin.tie(nullptr);\n\t${1:/* code */}\n\treturn 0;\n}'],
        ['forr', '倒序 for 循环', 'for (int ${1:i} = ${2:n} - 1; ${1:i} >= 0; --${1:i}) {\n\t${3:/* code */}\n}'],
        ['while', 'while 循环', 'while (${1:cond}) {\n\t${2:/* code */}\n}'],
        ['ife', 'if-else 分支', 'if (${1:cond}) {\n\t${2:/* code */}\n} else {\n\t${3:/* code */}\n}'],
        ['vec', 'vector 定义', 'vector<${1:int}> ${2:v};'],
        ['vec2', '二维 vector', 'vector<vector<${1:int}>> ${2:g}(${3:n}, vector<${1:int}>(${4:m}, 0));'],
        ['pii', 'pair<int,int> 定义', 'pair<int, int> ${1:p};'],
        ['read', '读取 n 个整数', 'int ${1:n};\ncin >> ${1:n};\nfor (int i = 0; i < ${1:n}; ++i) cin >> ${2:a}[i];'],
        ['gcd', '最大公约数', 'int gcd(int a, int b) { return b ? gcd(b, a % b) : a; }'],
        ['powmod', '快速幂', 'long long qpow(long long a, long long b, long long mod) {\n\tlong long r = 1;\n\twhile (b) { if (b & 1) r = r * a % mod; a = a * a % mod; b >>= 1; }\n\treturn r;\n}'],
        ['dfs', 'DFS 模板', 'void dfs(int u) {\n\tvis[u] = true;\n\tfor (int v : g[u]) if (!vis[v]) dfs(v);\n}'],
        ['sort', '排序', 'sort(${1:a}.begin(), ${1:a}.end());'],
        ['cmp', '自定义排序', 'sort(${1:a}.begin(), ${1:a}.end(), [](const ${2:auto} &x, const ${2:auto} &y) {\n\treturn ${3:x < y};\n});']
    ];
    var CPP_MEMBERS = {
        vector: 'push_back pop_back emplace_back size empty clear begin end front back resize reserve erase insert at data',
        string: 'size length empty clear substr find rfind erase insert append push_back pop_back begin end c_str at compare replace',
        map: 'insert erase find count size empty clear begin end lower_bound upper_bound at',
        set: 'insert erase find count size empty clear begin end lower_bound upper_bound',
        queue: 'push pop front back size empty',
        stack: 'push pop top size empty',
        deque: 'push_back push_front pop_back pop_front front back size empty clear',
        priority_queue: 'push pop top size empty',
        pair: 'first second',
        unordered_map: 'insert erase find count size empty clear begin end at',
        unordered_set: 'insert erase find count size empty clear begin end'
    };

    function guessContainerType(model, varName) {
        var text = model.getValue();
        var re = new RegExp('(vector|string|map|set|unordered_map|unordered_set|queue|stack|deque|priority_queue|pair)\\s*(<[^;=\\n]*>)?\\s+' + varName + '\\b');
        var m = re.exec(text);
        if (m) return m[1];
        if (new RegExp('vector\\s*<([^>]*)>\\s+' + varName + '\\b').test(text)) return 'vector';
        if (new RegExp('string\\s+' + varName + '\\b').test(text)) return 'string';
        return null;
    }

    function registerCppCompletion() {
        var K = monaco.languages.CompletionItemKind;
        var RULE = monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet;
        ['c', 'cpp'].forEach(function (lang) {
            monaco.languages.registerCompletionItemProvider(lang, {
                triggerCharacters: ['.', '>', ':', '#'],
                provideCompletionItems: function (model, position) {
                    var word = model.getWordUntilPosition(position);
                    var range = {
                        startLineNumber: position.lineNumber, endLineNumber: position.lineNumber,
                        startColumn: word.startColumn, endColumn: word.endColumn
                    };
                    var line = model.getLineContent(position.lineNumber).slice(0, position.column - 1);
                    var suggestions = [];
                    // 已经输入 # 时不再重复插入 #（补全范围只能覆盖当前单词，
                    // 把范围扩展到 # 会被 Monaco 当作“部分覆盖”而丢弃）
                    var hashTyped = word.startColumn > 1 && line.charAt(word.startColumn - 2) === '#';

                    // 刚输入 # 时只提示预处理指令（getWordUntilPosition 返回的字段名是 word）
                    if (hashTyped && (word.word || '') === '') {
                        return { suggestions: CPP_PREPROC.map(function (p) {
                            return {
                                label: p[0], kind: K.Operator, detail: p[2], insertText: p[1].slice(1),
                                insertTextRules: RULE, sortText: '0' + p[0], range: range
                            };
                        }) };
                    }

                    // 成员补全：v. / v-> / v.push
                    var m = /([A-Za-z_]\w*)\s*(?:\.|->)\s*\w*$/.exec(line);
                    if (m) {
                        var type = guessContainerType(model, m[1]);
                        if (type && CPP_MEMBERS[type]) {
                            CPP_MEMBERS[type].split(' ').forEach(function (fn) {
                                var isFn = !(type === 'pair' && (fn === 'first' || fn === 'second'));
                                suggestions.push({
                                    label: fn, kind: isFn ? K.Method : K.Field,
                                    insertText: isFn ? fn + '(${1})' : fn, insertTextRules: RULE,
                                    detail: type, sortText: '0' + fn, range: range
                                });
                            });
                            return { suggestions: suggestions };
                        }
                    }

                    // 代码片段
                    CPP_SNIPPETS.forEach(function (s) {
                        suggestions.push({
                            label: s[0], kind: K.Snippet, insertText: s[2],
                            insertTextRules: RULE, detail: s[1], sortText: '1' + s[0], range: range
                        });
                    });
                    // 标准库（类型/流对象直接插入，函数补全括号）
                    CPP_STD.forEach(function (f) {
                        var notCall = CPP_STD_NOT_CALL.indexOf(f[0]) >= 0;
                        suggestions.push({
                            label: f[0], kind: notCall ? K.Class : K.Function,
                            insertText: notCall ? f[0] : f[0] + '(${1})',
                            insertTextRules: RULE, detail: f[1], sortText: '2' + f[0], range: range
                        });
                    });
                    // 关键字
                    CPP_KEYWORDS.forEach(function (kw) {
                        suggestions.push({
                            label: kw, kind: K.Keyword, insertText: kw,
                            detail: '关键字', sortText: '3' + kw, range: range
                        });
                    });
                    // 预处理指令
                    CPP_PREPROC.forEach(function (p) {
                        suggestions.push({
                            label: p[0], kind: K.Operator, insertText: hashTyped ? p[1].slice(1) : p[1],
                            insertTextRules: RULE, detail: p[2], sortText: '4' + p[0], range: range
                        });
                    });
                    return { suggestions: suggestions };
                }
            });
        });
    }

    // ---- Monaco 初始化（加载失败则退化为普通文本框，不影响提交/运行）----
    var tries = 0;
    function initEditor() {
        if (!window.monaco) {
            if (++tries > 15) { srcTa.style.display = 'block'; srcTa.className = 'form-input'; srcTa.style.height = '100%'; return; }
            setTimeout(initEditor, 200);
            return;
        }
        registerCppCompletion();
        editor = monaco.editor.create($('editor-wrap'), {
            value: srcTa.value || '',
            language: LANG_MAP[langSel.value] || 'cpp',
            theme: 'vs-dark',
            fontSize: 13,
            fontFamily: "'JetBrains Mono', 'Fira Code', Consolas, monospace",
            minimap: { enabled: false },
            scrollBeyondLastLine: false,
            automaticLayout: true,
            tabSize: 4,
            wordWrap: 'on',
            quickSuggestions: { other: true, comments: false, strings: false },
            suggestOnTriggerCharacters: true,
        });
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, runTest);
    }
    initEditor();
})();
</script>

