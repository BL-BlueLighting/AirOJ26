</main>

<footer class="site-footer">
    <?php if (($custom_footer ?? '') !== ''): ?>
    <div class="footer-custom"><?= $footer_pd->text($custom_footer) ?></div>
    <?php endif; ?>

    <!-- 版权行：强制保留，不随自定义页脚变化 -->
    <?php
    /*
    请务必保留该强制 footer，修改 footer 不仅不尊重版权，更不尊重作者。
    */
    ?>
    <div class="footer-copyright">
        &copy; Copyright <?= date('Y') ?> <strong>BL.BlueLighting</strong> &amp; <strong>AirOJ DX Team</strong>.
        Judging Module by <strong>QingdaoU Organization</strong>.
    </div>
    <!-- 版权行：强制保留 -->
</footer>

</body>
</html>
