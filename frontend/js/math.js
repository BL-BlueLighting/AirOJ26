/**
 * AirOJ — LaTeX 公式渲染（KaTeX auto-render）
 * 支持 $...$、$$...$$、\(...\)、\[...\]
 * 只渲染 Markdown 内容容器（.mdui-prose / .math-content），不会误伤页面 UI 中的 $。
 */
(function () {
    'use strict';

    var DELIMS = [
        { left: '$$', right: '$$', display: true },
        { left: '$', right: '$', display: false },
        { left: '\\[', right: '\\]', display: true },
        { left: '\\(', right: '\\)', display: false }
    ];

    function renderIn(el) {
        if (!el || typeof window.renderMathInElement !== 'function') return;
        try {
            window.renderMathInElement(el, {
                delimiters: DELIMS,
                throwOnError: false,
                ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code', 'option']
            });
        } catch (e) { /* 单个容器渲染失败不影响页面 */ }
    }

    // 供动态内容（后台实时预览等）调用
    window.renderMath = renderIn;

    function init() {
        document.querySelectorAll('.mdui-prose, .math-content').forEach(renderIn);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
