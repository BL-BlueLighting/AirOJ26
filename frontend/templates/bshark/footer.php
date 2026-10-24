        </div><!-- /.column -->
    </div><!-- /.grid -->
</div><!-- /.bsharkMain -->

<div class="ui center aligned container bshark-footer" style="margin-top:40px;margin-bottom:30px">
    <?php if (($custom_footer ?? '') !== ''): ?>
    <div class="footer-custom"><?= $footer_pd->text($custom_footer) ?></div>
    <?php endif; ?>
    <!-- 版权行：强制保留，不随自定义页脚变化 -->
    <?php
    /*
    请务必保留该强制 footer，修改 footer 不仅不尊重版权，更不尊重作者。
    */
    ?>
    <div class="footer-copyright" style="color:#5b6b7c;font-size:12px">
        &copy; Copyright <?= date('Y') ?> <strong>BL.BlueLighting</strong> &amp; <strong>AirOJ DX Team</strong>.
        Judging Module by <strong>QingdaoU Organization</strong>.
    </div>
</div>

<script src="<?= theme_asset('scripts', 'jquery.min.js') ?>"></script>
<script src="<?= theme_asset('scripts', 'semantic.min.js') ?>"></script>
<script>if (window.jQuery) { jQuery('.ui.dropdown').dropdown(); }</script>
</body>
</html>
