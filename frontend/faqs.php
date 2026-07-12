<?php
$title = '常见问题';
require 'inc/header.php';
?>
<div class="page-header"><h1>❓ 常见问题</h1></div>

<div class="card"><h2>如何提交代码？</h2><p>在题目页面选择编程语言，编写代码后点击提交即可。评测结果将会在状态页面显示。</p></div>
<div class="card"><h2>支持哪些编程语言？</h2><p>支持 C (GCC)、C++ (G++)、Python 3、Java、Go、JavaScript (Node.js)。后续会陆续添加更多语言。</p></div>
<div class="card"><h2>评测结果的含义？</h2>
<p><strong>AC</strong> — 答案正确<br>
<strong>WA</strong> — 答案错误<br>
<strong>TLE</strong> — 超出时间限制<br>
<strong>MLE</strong> — 超出内存限制<br>
<strong>RE</strong> — 运行时错误<br>
<strong>SE</strong> — 系统错误</p></div>
<div class="card"><h2>如何查看测试点详情？</h2><p>在状态页面点击"查看"按钮，即可进入结果详情页查看每个测试点的评判结果。</p></div>
<div class="card"><h2>提交后多久能出结果？</h2><p>通常几秒内即可完成评测。如果长时间未出结果，请刷新页面查看最新状态。</p></div>

<?php require 'inc/footer.php'; ?>
