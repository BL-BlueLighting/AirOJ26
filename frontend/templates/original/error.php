<div class="page-header"><h1><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($title ?? '出错了') ?></h1></div>
<div class="card">
    <div class="empty">
        <div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <p><?= htmlspecialchars($error_message ?? '') ?></p>
    </div>
</div>
