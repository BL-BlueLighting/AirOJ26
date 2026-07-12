"""AirOJ Backend Configuration"""

import os

# === Backend Server ===
BACKEND_HOST = os.environ.get("BACKEND_HOST", "0.0.0.0")
BACKEND_PORT = int(os.environ.get("BACKEND_PORT", "5000"))
DEBUG = os.environ.get("BACKEND_DEBUG", "1") == "1"

# === Judge Token ===
# token = MD5( dayhour * SECRET )
# dayhour = int(YYYYMMDDHH)  (current date + hour, no minutes/seconds)
SECRET = int(os.environ.get("JUDGE_SECRET", str(114514 * 1919810)))
# 114514 * 1919810 = 2199102448340

# === JudgeServer Connection ===
JUDGE_SERVER_URL = os.environ.get("JUDGE_SERVER_URL", "http://localhost:12358")
JUDGE_SERVER_TOKEN = os.environ.get("JUDGE_SERVER_TOKEN", "AIR_JUDGE_TOKEN_DEV")

# === Test Cases Directory ===
# Path: /inputs_outputs/{problem_id}/
# Files: x.in / x.out  (paired by stem name)
INPUTS_OUTPUTS_DIR = os.environ.get(
    "INPUTS_OUTPUTS_DIR",
    os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "inputs_outputs")
)

# === Database ===
DB_PATH = os.environ.get(
    "DB_PATH",
    os.path.join(os.path.dirname(os.path.abspath(__file__)), "judge.db")
)

# === Judge Result Codes ===
RESULT_MAP = {
    0:   "AC",       # Accepted
    -1:  "WA",       # Wrong Answer
    1:   "TLE",      # CPU Time Limit Exceeded
    2:   "TLE",      # Real Time Limit Exceeded
    3:   "MLE",      # Memory Limit Exceeded
    4:   "RE",       # Runtime Error
    5:   "SE",       # System Error
}

# === Supported Judge Types ===
JUDGE_TYPES = {
    "standard": "Standard IO judging — compare program output with reference output file",
    "spj": "Special Judge — uses a custom judge program to evaluate output",
}

# === Supported Languages ===
LANGUAGE_CONFIGS = {
    "c": {
        "name": "C (GCC)",
        "compile": True,
        "compile_command": "/usr/bin/gcc -O2 -w -o {exe_path} {src_path} -lm",
        "run_command": "{exe_path}",
        "seccomp_rule": "c_cpp",
        "src_name": "main.c",
        "exe_name": "main",
    },
    "cpp": {
        "name": "C++ (G++)",
        "compile": True,
        "compile_command": "/usr/bin/g++ -O2 -w -o {exe_path} {src_path} -lm",
        "run_command": "{exe_path}",
        "seccomp_rule": "c_cpp",
        "src_name": "main.cpp",
        "exe_name": "main",
    },
    "python3": {
        "name": "Python 3",
        "compile": False,
        "compile_command": None,
        "run_command": "/usr/bin/python3 {exe_path}",
        "seccomp_rule": "general",
        "src_name": "main.py",
        "exe_name": "main.py",
    },
    "python2": {
        "name": "Python 2",
        "compile": False,
        "compile_command": None,
        "run_command": "/usr/bin/python2 {exe_path}",
        "seccomp_rule": "general",
        "src_name": "main.py",
        "exe_name": "main.py",
    },
    "java": {
        "name": "Java",
        "compile": True,
        "compile_command": "/usr/bin/javac -d {exe_dir} {src_path}",
        "run_command": "/usr/bin/java -cp {exe_dir} Main",
        "seccomp_rule": "general",
        "src_name": "Main.java",
        "exe_name": "Main.class",
    },
    "go": {
        "name": "Go",
        "compile": True,
        "compile_command": "/usr/bin/go build -o {exe_path} {src_path}",
        "run_command": "{exe_path}",
        "seccomp_rule": "golang",
        "src_name": "main.go",
        "exe_name": "main",
    },
    "javascript": {
        "name": "JavaScript (Node.js)",
        "compile": False,
        "compile_command": None,
        "run_command": "/usr/bin/node {exe_path}",
        "seccomp_rule": "node",
        "src_name": "main.js",
        "exe_name": "main.js",
    },
}
