# AirOJ Monaco 编辑器构建

`frontend/js/` 里是构建产物（未纳入 git），这里保留构建源码，便于重新生成。

## 为什么需要自己构建

之前使用的精简版 Monaco **不包含 suggest 补全控件**（没有 `editor.action.triggerSuggest` 命令），
无论注册多少补全提供器都不会弹出提示。这里的入口使用完整的
`monaco-editor/esm/vs/editor/editor.main.js`，因此自带补全、悬浮提示、查找替换等全部编辑功能。

## 构建步骤

```bash
cd frontend/js-src
npm install
npm run build

# 产物拷贝到 ../js/，并把样式重命名为 monaco.css（problem.php 里引用的就是这个名字）
rm -f ../js/*.js ../js/*.css && rm -rf ../js/assets
cp -r dist/. ../js/
mv ../js/style-*.css ../js/monaco.css
```

## 说明

- 入口 `src/main.js` 会设置 `window.monaco`，并注册各语言的常用代码片段补全，
  与 `problem.php` 中的加载方式（`<script type="module" src="/js/monaco.js">`）保持一致。
- 语言按需引入：c/cpp、java、python、go、javascript；其余语言是 `editor.main`
  自带的懒加载 chunk，只有真正用到时才会请求。
- Worker（`assets/editor.worker-*.js`）由 Vite 通过 `?worker` 生成，路径相对于 `monaco.js`，
  所以必须保持 `js/assets/` 的目录结构。
- C/C++ 的自动补全（关键字、标准库、STL 成员、预处理指令、代码片段）在
  `frontend/problem.php` 的 `registerCppCompletion()` 中注册，不在本目录。
