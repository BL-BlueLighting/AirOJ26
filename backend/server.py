"""AirOJ Backend Server

Provides:
  POST /backend/request_judge  — submit code for judging
  POST /backend/judge_state    — query submission result
"""

import base64
import datetime
import hashlib
import json
import os
import sqlite3
import threading
import time

import requests
from flask import Flask, jsonify, request, send_from_directory

from config import (
    BACKEND_HOST, BACKEND_PORT, DEBUG,
    SECRET, JUDGE_SERVER_URL, JUDGE_SERVER_TOKEN,
    INPUTS_OUTPUTS_DIR, DB_PATH, RESULT_MAP,
    JUDGE_TYPES, LANGUAGE_CONFIGS,
)

# ========== App Setup ==========

app = Flask(__name__)

# ========== Database ==========

def get_db():
    conn = sqlite3.connect(DB_PATH, check_same_thread=False)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA foreign_keys=ON")
    return conn


def init_db():
    os.makedirs(os.path.dirname(os.path.abspath(DB_PATH)), exist_ok=True)
    conn = get_db()
    conn.executescript("""
        CREATE TABLE IF NOT EXISTS submissions (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            problem_id      TEXT    NOT NULL,
            judge_type      TEXT    NOT NULL DEFAULT 'standard',
            judge_lang      TEXT    NOT NULL,
            code            TEXT    NOT NULL,
            status          TEXT    NOT NULL DEFAULT 'pending',
            result_json     TEXT,
            total_cases     INTEGER NOT NULL DEFAULT 0,
            passed_cases    INTEGER NOT NULL DEFAULT 0,
            score           REAL    NOT NULL DEFAULT 0.0,
            created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
            updated_at      TEXT    NOT NULL DEFAULT (datetime('now'))
        );
    """)
    conn.commit()
    conn.close()


init_db()

# ========== Helper: Token ==========

def generate_token():
    """Generate judge token for the current hour.
    token = MD5( dayhour * SECRET )
    dayhour = int(YYYYMMDDHH)  — e.g. 2026071212 for 2026-07-12 12:xx
    """
    now = datetime.datetime.now()
    dayhour = int(now.strftime("%Y%m%d%H"))
    raw = str(dayhour * SECRET)
    return hashlib.md5(raw.encode()).hexdigest()


def verify_token(token):
    """Check if token matches any hour within ±2h window."""
    now = datetime.datetime.now()
    for offset in range(-2, 3):
        t = now + datetime.timedelta(hours=offset)
        dayhour = int(t.strftime("%Y%m%d%H"))
        raw = str(dayhour * SECRET)
        expected = hashlib.md5(raw.encode()).hexdigest()
        if token == expected:
            return True
    return False


# ========== Helper: Test Cases ==========

def load_test_cases(problem_id):
    """Load paired .in / .out files from /inputs_outputs/{problem_id}/.
    Returns (test_cases_list, error_message).
    Only pairs where both .in and .out exist are included.
    """
    test_dir = os.path.join(INPUTS_OUTPUTS_DIR, str(problem_id))
    if not os.path.isdir(test_dir):
        return None, f"Problem '{problem_id}' not found"

    files = os.listdir(test_dir)
    in_map = {}
    out_map = {}

    for f in files:
        if f.endswith(".in"):
            stem = f[:-3]
            in_map[stem] = f
        elif f.endswith(".out"):
            stem = f[:-4]
            out_map[stem] = f

    test_cases = []
    for stem in sorted(in_map):
        if stem in out_map:
            in_path = os.path.join(test_dir, in_map[stem])
            out_path = os.path.join(test_dir, out_map[stem])
            try:
                with open(in_path, "r") as fh:
                    inp = fh.read()
                with open(out_path, "r") as fh:
                    out = fh.read()
                test_cases.append({
                    "name": stem,
                    "input": inp,
                    "output": out,
                })
            except OSError as e:
                continue  # skip unreadable files silently

    if not test_cases:
        return None, f"No valid test case pairs found for problem '{problem_id}'"

    return test_cases, None


# ========== Helper: JudgeServer Client ==========

def _judge_server_token():
    return hashlib.sha256(JUDGE_SERVER_TOKEN.encode()).hexdigest()


def _build_language_config(lang):
    """Build full config dict for JudgeServer from LANGUAGE_CONFIGS."""
    cfg = LANGUAGE_CONFIGS.get(lang)
    if not cfg:
        return None

    lang_cfg = {
        "run": {
            "command": cfg["run_command"],
            "seccomp_rule": cfg["seccomp_rule"],
            "exe_name": cfg["exe_name"],
        }
    }

    if cfg.get("compile") and cfg.get("compile_command"):
        lang_cfg["compile"] = {
            "src_name": cfg["src_name"],
            "exe_name": cfg["exe_name"],
            "max_cpu_time": 30000,
            "max_real_time": 60000,
            "max_memory": 536870912,  # 512 MB
            "compile_command": cfg["compile_command"],
        }

    return lang_cfg


def dispatch_judge(submission_id, problem_id, code, lang, judge_type):
    """Run judging in a background thread."""
    thread = threading.Thread(
        target=_do_judge,
        args=(submission_id, problem_id, code, lang, judge_type),
        daemon=True,
    )
    thread.start()


def _do_judge(submission_id, problem_id, code, lang, judge_type):
    """Actually perform judging via JudgeServer."""
    conn = get_db()
    try:
        # Update status
        conn.execute(
            "UPDATE submissions SET status='judging', updated_at=datetime('now') WHERE id=?",
            (submission_id,)
        )
        conn.commit()

        # Load test cases
        test_cases, err = load_test_cases(problem_id)
        if err:
            _finish(conn, submission_id, "failed", 0, 0, 0, err)
            return

        # Build language config
        lang_cfg = _build_language_config(lang)
        if not lang_cfg:
            _finish(conn, submission_id, "failed", 0, 0, 0, f"Unsupported language: {lang}")
            return

        # Prepare JudgeServer request
        judge_payload = {
            "language_config": lang_cfg,
            "src": code,
            "max_cpu_time": 2000,
            "max_memory": 268435456,  # 256 MB
            "test_case": [{"input": tc["input"], "output": tc["output"]} for tc in test_cases],
            "output": False,
        }

        headers = {
            "X-Judge-Server-Token": _judge_server_token(),
            "Content-Type": "application/json",
        }

        resp = requests.post(
            f"{JUDGE_SERVER_URL}/judge",
            json=judge_payload,
            headers=headers,
            timeout=120,
        )
        result = resp.json()

        if result.get("err"):
            _finish(conn, submission_id, "failed", 0, 0, 0, result["err"])
            return

        results = result.get("data", [])

        # Process results
        case_results = []
        passed = 0
        total = len(test_cases)

        for i, tc in enumerate(test_cases):
            r = results[i] if i < len(results) else {}
            result_code = r.get("result", 5)
            verdict = RESULT_MAP.get(result_code, "UK")
            if verdict == "AC":
                passed += 1
            case_results.append({
                "name": tc["name"],
                "verdict": verdict,
            })

        score = round((passed / total) * 100, 1) if total > 0 else 0

        _finish(conn, submission_id, "done", total, passed, score,
                json.dumps(case_results, ensure_ascii=False))

    except requests.Timeout:
        _finish(conn, submission_id, "failed", 0, 0, 0, "Judge server timeout")
    except requests.ConnectionError:
        _finish(conn, submission_id, "failed", 0, 0, 0, "Judge server unreachable")
    except Exception as e:
        _finish(conn, submission_id, "failed", 0, 0, 0, str(e))
    finally:
        conn.close()


def _finish(conn, submission_id, status, total, passed, score, result_data):
    conn.execute(
        """UPDATE submissions
           SET status=?, total_cases=?, passed_cases=?, score=?, result_json=?, updated_at=datetime('now')
           WHERE id=?""",
        (status, total, passed, score, str(result_data), submission_id)
    )
    conn.commit()


# ========== API Routes ==========

@app.route("/backend/request_judge", methods=["POST"])
def api_request_judge():
    """Submit code for judging.

    Request JSON:
      judge_token  : str  — authentication token
      judge_type   : str  — type of judging (see README)
      judge_code   : str  — source code, base64-encoded
      judge_lang   : str  — programming language (see README)
      problem_id   : str  — problem identifier

    Response JSON:
      judge_commitid : int  — submission ID
    """
    data = request.get_json(silent=True)
    if not data:
        return jsonify({"error": "Invalid JSON body"}), 400

    token = data.get("judge_token", "")
    judge_type = data.get("judge_type", "standard")
    judge_code_b64 = data.get("judge_code", "")
    judge_lang = data.get("judge_lang", "")
    problem_id = str(data.get("problem_id", ""))

    # Validate token
    if not verify_token(token):
        return jsonify({"error": "Invalid or expired token"}), 403

    # Validate judge_type
    if judge_type not in JUDGE_TYPES:
        return jsonify({"error": f"Unsupported judge_type '{judge_type}'. Supported: {list(JUDGE_TYPES.keys())}"}), 400

    # Validate language
    if judge_lang not in LANGUAGE_CONFIGS:
        return jsonify({"error": f"Unsupported language '{judge_lang}'. Supported: {list(LANGUAGE_CONFIGS.keys())}"}), 400

    # Decode code
    try:
        judge_code = base64.b64decode(judge_code_b64).decode("utf-8")
    except Exception:
        return jsonify({"error": "Invalid base64 encoding in judge_code"}), 400

    if not judge_code.strip():
        return jsonify({"error": "judge_code is empty"}), 400

    if not problem_id:
        return jsonify({"error": "problem_id is required"}), 400

    # Insert submission into DB
    conn = get_db()
    try:
        cur = conn.execute(
            """INSERT INTO submissions (problem_id, judge_type, judge_lang, code, status)
               VALUES (?, ?, ?, ?, 'pending')""",
            (problem_id, judge_type, judge_lang, judge_code)
        )
        conn.commit()
        submission_id = cur.lastrowid
    finally:
        conn.close()

    # Dispatch judging in background
    dispatch_judge(submission_id, problem_id, judge_code, judge_lang, judge_type)

    return jsonify({"judge_commitid": submission_id})


@app.route("/backend/judge_state", methods=["POST"])
def api_judge_state():
    """Query judging result.

    Request JSON:
      commitid   : int  — submission ID from request_judge
      problem_id : str  — problem identifier

    Response JSON:
      commitid   : int
      problem_id : str
      status     : str  — pending / judging / done / failed
      score      : float
      cases      : list of {name, verdict}
    """
    data = request.get_json(silent=True)
    if not data:
        return jsonify({"error": "Invalid JSON body"}), 400

    try:
        commitid = int(data.get("commitid", 0))
    except (ValueError, TypeError):
        return jsonify({"error": "Invalid commitid"}), 400

    problem_id = str(data.get("problem_id", ""))
    if not problem_id:
        return jsonify({"error": "problem_id is required"}), 400

    conn = get_db()
    try:
        row = conn.execute(
            "SELECT * FROM submissions WHERE id=? AND problem_id=?",
            (commitid, problem_id)
        ).fetchone()
    finally:
        conn.close()

    if not row:
        return jsonify({"error": "Submission not found"}), 404

    cases = []
    if row["result_json"]:
        try:
            cases = json.loads(row["result_json"])
        except (json.JSONDecodeError, TypeError):
            pass

    return jsonify({
        "commitid": row["id"],
        "problem_id": row["problem_id"],
        "status": row["status"],
        "score": row["score"],
        "total_cases": row["total_cases"],
        "passed_cases": row["passed_cases"],
        "cases": cases,
    })


# ========== Info Endpoints ==========

@app.route("/backend/info", methods=["GET"])
def api_info():
    """Return supported languages and judge types."""
    return jsonify({
        "judge_types": {k: {"description": v} for k, v in JUDGE_TYPES.items()},
        "languages": {
            k: {
                "name": v["name"],
                "requires_compilation": v.get("compile", False),
            }
            for k, v in LANGUAGE_CONFIGS.items()
        },
    })


# ========== Problems API ==========

@app.route("/backend/problems", methods=["GET"])
def api_problems():
    """List all available problems from inputs_outputs directories."""
    if not os.path.isdir(INPUTS_OUTPUTS_DIR):
        return jsonify([])
    problems = []
    try:
        for name in sorted(os.listdir(INPUTS_OUTPUTS_DIR), key=lambda x: int(x) if x.isdigit() else x):
            d = os.path.join(INPUTS_OUTPUTS_DIR, name)
            if os.path.isdir(d):
                files = os.listdir(d)
                in_count = len([f for f in files if f.endswith(".in")])
                out_count = len([f for f in files if f.endswith(".out")])
                problems.append({
                    "id": name,
                    "test_cases": min(in_count, out_count),
                })
    except Exception:
        pass
    return jsonify(problems)


@app.route("/backend/problem/<problem_id>", methods=["GET"])
def api_problem_detail(problem_id):
    """Get problem detail (test case info)."""
    test_dir = os.path.join(INPUTS_OUTPUTS_DIR, str(problem_id))
    if not os.path.isdir(test_dir):
        return jsonify({"error": "Problem not found"}), 404

    files = os.listdir(test_dir)
    in_map = {f[:-3] for f in files if f.endswith(".in")}
    out_map = {f[:-4] for f in files if f.endswith(".out")}
    pairs = sorted(in_map & out_map)

    return jsonify({
        "id": problem_id,
        "test_cases": len(pairs),
        "test_case_ids": pairs,
    })


# ========== Frontend Static Files ==========

FRONTEND_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "frontend")


@app.route("/")
@app.route("/<path:path>")
def frontend(path=""):
    """Serve frontend static files, with SPA fallback to index.html."""
    if not path:
        return send_from_directory(FRONTEND_DIR, "index.html")
    # Try exact file match first
    file_path = os.path.join(FRONTEND_DIR, path)
    if os.path.isfile(file_path):
        return send_from_directory(FRONTEND_DIR, path)
    # Fallback to index.html for SPA routing
    return send_from_directory(FRONTEND_DIR, "index.html")


# ========== Main ==========

if __name__ == "__main__":
    print(f"AirOJ Backend starting on {BACKEND_HOST}:{BACKEND_PORT}")
    print(f"  Frontend:  {FRONTEND_DIR}")
    print(f"  Token secret: {SECRET}")
    print(f"  Test cases dir: {INPUTS_OUTPUTS_DIR}")
    print(f"  JudgeServer: {JUDGE_SERVER_URL}")
    print(f"  Supported languages: {list(LANGUAGE_CONFIGS.keys())}")
    print(f"  Supported judge types: {list(JUDGE_TYPES.keys())}")
    app.run(host=BACKEND_HOST, port=BACKEND_PORT, debug=DEBUG)
