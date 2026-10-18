<?php
/**
 * 独立管理页的通用布局（左：功能块标题 + 按钮；右：功能页面）
 * 使用前设置：$admin_current_group、$admin_current_item
 */
require_once __DIR__ . '/_admin_nav.php';
$admin_group = $admin_groups[$admin_current_group] ?? null;
require __DIR__ . '/../inc/header.php';
?>
<div class="admin-shell">
    <aside class="admin-menu">
        <?php if ($admin_group): ?>
        <div class="admin-menu-title"><i class="fa-solid <?= $admin_group['icon'] ?>"></i> <?= htmlspecialchars($admin_group['title']) ?></div>
        <div class="admin-menu-divider"></div>
        <?php foreach ($admin_group['items'] as $itemKey => $item): ?>
        <a href="<?= $item[0] ?>" class="<?= $itemKey === $admin_current_item ? 'active' : '' ?>"><?= htmlspecialchars($item[1]) ?></a>
        <?php endforeach; ?>
        <a href="/admin/index.php" class="admin-menu-home"><i class="fa-solid fa-arrow-left"></i> 返回管理首页</a>
        <?php endif; ?>
    </aside>
    <div class="admin-page">
        <div class="admin-content">
