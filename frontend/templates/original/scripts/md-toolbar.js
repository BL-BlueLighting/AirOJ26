/**
 * AirOJ — Markdown 工具栏
 * 给带 md-editor 类的 textarea 自动添加工具栏（粗体/斜体/代码/链接/列表/引用/标题/代码块）。
 */
(function () {
    'use strict';

    var BUTTONS = [
        { html: '<b>B</b>', title: '粗体', wrap: ['**', '**'], ph: '粗体' },
        { html: '<i>I</i>', title: '斜体', wrap: ['*', '*'], ph: '斜体' },
        { html: '<i class="fa-solid fa-code"></i>', title: '行内代码', wrap: ['`', '`'], ph: 'code' },
        { html: '<i class="fa-solid fa-file-code"></i>', title: '代码块', fence: true, ph: '在此粘贴代码' },
        { html: '<i class="fa-solid fa-link"></i>', title: '链接', wrap: ['[', '](https://)'], ph: '链接文字' },
        { html: '<i class="fa-solid fa-list-ul"></i>', title: '无序列表', prefix: '- ' },
        { html: '<i class="fa-solid fa-list-ol"></i>', title: '有序列表', prefix: '1. ' },
        { html: '<i class="fa-solid fa-quote-left"></i>', title: '引用', prefix: '> ' },
        { html: 'H2', title: '二级标题', prefix: '## ' },
        { html: '<i class="fa-solid fa-minus"></i>', title: '分割线', insert: '\n---\n' }
    ];

    function fire(ta) {
        ta.dispatchEvent(new Event('input', { bubbles: true }));
        ta.focus();
    }

    function wrapSelection(ta, before, after, placeholder) {
        var s = ta.selectionStart, e = ta.selectionEnd;
        var sel = ta.value.slice(s, e) || placeholder || '';
        ta.setRangeText(before + sel + after, s, e, 'end');
        ta.selectionStart = s + before.length;
        ta.selectionEnd = s + before.length + sel.length;
        fire(ta);
    }

    function prefixLines(ta, prefix) {
        var s = ta.value.lastIndexOf('\n', ta.selectionStart - 1) + 1;
        var e = ta.selectionEnd;
        var chunk = ta.value.slice(s, e);
        var lines = chunk.split('\n').map(function (l) { return prefix + l; });
        ta.setRangeText(lines.join('\n'), s, e, 'end');
        fire(ta);
    }

    function insertFence(ta, placeholder) {
        var s = ta.selectionStart, e = ta.selectionEnd;
        var sel = ta.value.slice(s, e) || placeholder || '';
        var text = '```\n' + sel + '\n```';
        ta.setRangeText(text, s, e, 'end');
        ta.selectionStart = s + 4;
        ta.selectionEnd = s + 4 + sel.length;
        fire(ta);
    }

    function build(ta) {
        if (ta.dataset.mdReady) return;
        ta.dataset.mdReady = '1';

        var bar = document.createElement('div');
        bar.className = 'md-toolbar';

        BUTTONS.forEach(function (b) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'md-toolbar-btn';
            btn.title = b.title;
            btn.innerHTML = b.html;
            btn.addEventListener('click', function () {
                if (b.wrap) wrapSelection(ta, b.wrap[0], b.wrap[1], b.ph);
                else if (b.prefix) prefixLines(ta, b.prefix);
                else if (b.fence) insertFence(ta, b.ph);
                else if (b.insert) {
                    ta.setRangeText(b.insert, ta.selectionStart, ta.selectionEnd, 'end');
                    fire(ta);
                }
            });
            bar.appendChild(btn);
        });

        // 支持 Ctrl+B / Ctrl+I
        ta.addEventListener('keydown', function (e) {
            if (!(e.ctrlKey || e.metaKey)) return;
            if (e.key === 'b') { e.preventDefault(); wrapSelection(ta, '**', '**', '粗体'); }
            if (e.key === 'i') { e.preventDefault(); wrapSelection(ta, '*', '*', '斜体'); }
        });

        ta.parentNode.insertBefore(bar, ta);
    }

    function init() {
        document.querySelectorAll('textarea.md-editor').forEach(build);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
