// AirOJ Monaco 打包入口
// 使用完整版 editor.main（含 suggest 补全控件），语言按需引入，保持 /js/monaco.js 的加载约定。
import * as monaco from 'monaco-editor/esm/vs/editor/editor.main.js';

import 'monaco-editor/esm/vs/basic-languages/cpp/cpp.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/java/java.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/python/python.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/go/go.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/javascript/javascript.contribution.js';

import EditorWorker from 'monaco-editor/esm/vs/editor/editor.worker?worker';

self.MonacoEnvironment = {
    getWorker() {
        return new EditorWorker();
    },
};

// ── 各语言常用代码片段（原有的轻量补全，保留） ──
const SNIPPETS = {
    c: [
        ['main', 'C main 模板', '#include <stdio.h>\nint main() {\n\t${1:/* code */}\n\treturn 0;\n}'],
        ['printf', '格式化输出', 'printf("${1:%d\\n}", ${2:var});'],
        ['scanf', '格式化输入', 'scanf("${1:%d}", &${2:var});'],
        ['for', 'for 循环', 'for (int ${1:i} = 0; ${1:i} < ${2:n}; ++${1:i}) {\n\t${3:/* code */}\n}'],
        ['if', 'if 分支', 'if (${1:cond}) {\n\t${2:/* code */}\n}'],
    ],
    cpp: [
        ['main', 'C++ main 模板', '#include <iostream>\nusing namespace std;\nint main() {\n\t${1:/* code */}\n\treturn 0;\n}'],
        ['cout', '输出', 'cout << ${1:value} << endl;'],
        ['cin', '输入', 'cin >> ${1:var};'],
        ['for', 'for 循环', 'for (int ${1:i} = 0; ${1:i} < ${2:n}; ++${1:i}) {\n\t${3:/* code */}\n}'],
        ['vector', 'vector 容器', 'vector<${1:int}> ${2:v};'],
    ],
    python: [
        ['main', '主函数模板', 'if __name__ == "__main__":\n\t${1:pass}'],
        ['print', '输出', 'print(${1:value})'],
        ['input', '读取一行', '${1:line} = input().strip()'],
        ['for', 'for 循环', 'for ${1:i} in range(${2:n}):\n\t${3:pass}'],
        ['def', '定义函数', 'def ${1:func}(${2:args}):\n\t${3:pass}'],
        ['split', '切分输入', '${1:a} = input().split()'],
    ],
    java: [
        ['main', 'Java main 模板', 'public class Main {\n\tpublic static void main(String[] args) {\n\t\t${1:/* code */}\n\t}\n}'],
        ['println', '输出', 'System.out.println(${1:value});'],
        ['scan', 'Scanner 输入', 'Scanner ${1:sc} = new Scanner(System.in);\n${2:int} ${3:n} = ${1:sc}.nextInt();'],
        ['for', 'for 循环', 'for (int ${1:i} = 0; ${1:i} < ${2:n}; ${1:i}++) {\n\t${3:/* code */}\n}'],
    ],
    go: [
        ['main', 'Go main 模板', 'package main\n\nimport "fmt"\n\nfunc main() {\n\t${1:/* code */}\n}'],
        ['println', '输出', 'fmt.Println(${1:value})'],
        ['for', 'for 循环', 'for ${1:i} := 0; ${1:i} < ${2:n}; ${1:i}++ {\n\t${3:/* code */}\n}'],
        ['scan', '读取输入', 'fmt.Scan(&${1:var})'],
    ],
    javascript: [
        ['main', '入口函数', 'function main() {\n\t${1:/* code */}\n}\nmain();'],
        ['console', '控制台输出', 'console.log(${1:value});'],
        ['function', '函数定义', 'function ${1:name}(${2:args}) {\n\t${3:/* code */}\n}'],
        ['for', 'for 循环', 'for (let ${1:i} = 0; ${1:i} < ${2:n}; ${1:i}++) {\n\t${3:/* code */}\n}'],
    ],
};

for (const [lang, items] of Object.entries(SNIPPETS)) {
    monaco.languages.registerCompletionItemProvider(lang, {
        provideCompletionItems(model, position) {
            const word = model.getWordUntilPosition(position);
            const range = {
                startLineNumber: position.lineNumber,
                endLineNumber: position.lineNumber,
                startColumn: word.startColumn,
                endColumn: word.endColumn,
            };
            return {
                suggestions: items.map(([label, detail, insertText]) => ({
                    label,
                    detail,
                    insertText,
                    kind: monaco.languages.CompletionItemKind.Snippet,
                    insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                    range,
                })),
            };
        },
    });
}

window.monaco = monaco;
export default monaco;
