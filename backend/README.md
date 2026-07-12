# AirOJ Backend

后端服务，对接 [QingdaoU JudgeServer](../judge/)。

## API

### POST `/backend/request_judge`

提交代码进行评测。

**请求格式** (JSON):

| 字段 | 类型 | 说明 |
|------|------|------|
| `judge_token` | string | 鉴权令牌 (见下方 Token 算法) |
| `judge_type` | string | 评测类型 (见下方) |
| `judge_code` | string | 源代码，**base64 编码** |
| `judge_lang` | string | 编程语言 (见下方) |
| `problem_id` | string | 题目 ID |

**返回** (JSON):

```json
{ "judge_commitid": 1 }
```

---

### POST `/backend/judge_state`

查询评测结果。

**请求格式** (JSON):

| 字段 | 类型 | 说明 |
|------|------|------|
| `commitid` | int | `request_judge` 返回的提交 ID |
| `problem_id` | string | 题目 ID |

**返回** (JSON):

```json
{
  "commitid": 1,
  "problem_id": "1001",
  "status": "done",
  "score": 50.0,
  "total_cases": 2,
  "passed_cases": 1,
  "cases": [
    { "name": "1", "verdict": "AC" },
    { "name": "2", "verdict": "WA" }
  ]
}
```

`status` 取值: `pending` → `judging` → `done` / `failed`

---

### GET `/backend/info`

查看后端支持的评测类型和编程语言。

---

## Token 算法

```
dayhour = int(YYYYMMDDHH)        — 当前日期+小时，如 2026071212
SECRET = 114514 * 1919810        — 2199102448340
token_input = str(dayhour * SECRET)
judge_token = MD5(token_input)

Token 在 ±2 小时内有效。
```

前端计算示例 (Python)：
```python
import hashlib, datetime
now = datetime.datetime.now()
dayhour = int(now.strftime("%Y%m%d%H"))
raw = str(dayhour * 2199102448340)
token = hashlib.md5(raw.encode()).hexdigest()
```

---

## 测试用例

路径: `/inputs_outputs/{problem_id}/`

文件命名: 配对 `.in` / `.out` 文件，按文件名排序

```
inputs_outputs/1001/
├── 1.in
├── 1.out
├── 2.in
├── 2.out
└── 3.in       ← 没有 3.out，自动忽略，不参与测试
```

---

## 支持的评测类型 (`judge_type`)

| 类型 | 说明 |
|------|------|
| `standard` | 标准 IO 评测 — 比较程序输出与标准答案文件 |
| `spj` | 特判 (Special Judge) — 使用自定义判题程序 |

---

## 支持的编程语言 (`judge_lang`)

| 语言 | 标识符 | 需要编译 | 说明 |
|------|--------|---------|------|
| C (GCC) | `c` | 是 | `gcc -O2 -w` |
| C++ (G++) | `cpp` | 是 | `g++ -O2 -w` |
| Python 3 | `python3` | 否 | `python3` |
| Java | `java` | 是 | `javac` + `java` |
| Go | `go` | 是 | `go build` |
| JavaScript (Node.js) | `javascript` | 否 | `node` |

---

## 判题结果 (verdict)

| 代码 | 含义 |
|------|------|
| `AC` | Accepted — 通过 |
| `WA` | Wrong Answer — 答案错误 |
| `TLE` | Time Limit Exceeded — 超时 |
| `MLE` | Memory Limit Exceeded — 超内存 |
| `RE` | Runtime Error — 运行时错误 |
| `SE` | System Error — 系统错误 |
| `UK` | Unknown — 未知错误 |
