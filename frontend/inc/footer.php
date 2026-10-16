<?php
/**
 * AirOJ — 页脚
 *
 * 自定义页脚内容由管理员在「后台 → 系统设置 → 页脚设置」中配置（system_config.site_footer），
 * 支持 Markdown，显示在下方版权行之上。
 */
$__custom_footer = '';
if (class_exists('Database')) {
    try {
        $__custom_footer = trim(Database::getConfig('site_footer'));
    } catch (Exception $e) {}
}
if ($__custom_footer !== '') {
    require_once __DIR__ . '/Parsedown.php';
    $__footer_pd = new Parsedown();
    $__footer_pd->setSafeMode(true);
}
?>
</main>

<footer class="site-footer">
    <?php if ($__custom_footer !== ''): ?>
    <div class="footer-custom"><?= $__footer_pd->text($__custom_footer) ?></div>
    <?php endif; ?>

    <!-- ↓↓↓ 版权行：强制保留，不随自定义页脚变化 ↓↓↓ -->
    <?php
    /*
    请务必保留该强制 footer，修改 footer 不仅不尊重版权，更不尊重作者。
    */
    ?>
    <div class="footer-copyright">
        &copy; Copyright <?= date('Y') ?> <strong>BL.BlueLighting</strong> &amp; <strong>AirOJ DX Team</strong>.
        Judging Module by <strong>QingdaoU Organization</strong>.
    </div>
    <!-- ↑↑↑ 版权行：强制保留 ↑↑↑ -->
</footer>

</body>
</html>
