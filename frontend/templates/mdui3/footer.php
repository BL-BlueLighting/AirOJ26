        </div><!-- /.mdui-container -->

        <footer class="mdui-footer">
            <div class="mdui-container">
                <?php if (($custom_footer ?? '') !== ''): ?>
                <div class="footer-custom mdui-prose"><?= $footer_pd->text($custom_footer) ?></div>
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
            </div>
        </footer>
    </mdui-layout-main>
</mdui-layout>

<script>
(function () {
    if (!window.customElements || !customElements.whenDefined) return;
    customElements.whenDefined('mdui-navigation-drawer').then(function () {
        var drawer = document.getElementById('main-drawer');
        if (!drawer) return;
        var mq = window.matchMedia('(max-width: 839.98px)');
        function sync() { drawer.open = !mq.matches; }
        sync();
        if (mq.addEventListener) mq.addEventListener('change', sync);
        else if (mq.addListener) mq.addListener(sync);
    });
})();
</script>
</body>
</html>
