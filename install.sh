#!/bin/bash
set -euo pipefail

# ============================================================
#  AirOJ — One-Click Install Script
#  Installs: Judger + JudgeServer + Backend
# ============================================================

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok()    { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
err()   { echo -e "${RED}[ERROR]${NC} $*"; }
sudorun() { echo -e "${YELLOW}>>>${NC} sudo $*"; sudo "$@" || err "Command failed: sudo $*"; }

# ------ Config (tweakable) ------
BASE_DIR="$(cd "$(dirname "$0")" && pwd)"
VENV_DIR="${BASE_DIR}/venv"
JUDGE_DIR="${BASE_DIR}/judge"
BACKEND_DIR="${BASE_DIR}/backend"
INPUTS_DIR="${BASE_DIR}/inputs_outputs"
JUDGER_REPO="https://github.com/QingdaoU/Judger.git"
JUDGESERVER_REPO="https://github.com/QingdaoU/JudgeServer.git"

JUDGE_SERVER_TOKEN="${JUDGE_SERVER_TOKEN:-AIR_JUDGE_TOKEN_DEV}"
BACKEND_PORT="${BACKEND_PORT:-5000}"
SECRET_A=$((114514 * 1919810))  # 219845122340

# ============================================================
#  1.  System Dependencies
# ============================================================
install_system_deps() {
    info "Checking system dependencies..."

    local missing=()
    for cmd in cmake gcc g++ redis-server mysql git python3 nproc; do
        if ! command -v "${cmd#*:}" &>/dev/null; then
            missing+=("$cmd")
        fi
    done
    # libseccomp check
    if ! ldconfig -p 2>/dev/null | grep -q libseccomp; then missing+=("libseccomp"); fi

    if [[ ${#missing[@]} -gt 0 ]]; then
        warn "Missing: ${missing[*]}"
        warn "Install them first, e.g.:"
        echo "  sudo pacman -S cmake redis mariadb libseccomp gcc git"
        echo "  sudo apt install cmake redis-server mysql-server libseccomp-dev gcc g++ git"
        echo ""
        read -rp "Continue anyway? [Y/n] " yn
        [[ "${yn:-Y}" =~ ^[Nn] ]] && exit 1
    else
        ok "All system deps found"
    fi
}

# ============================================================
#  2.  System Users
# ============================================================
setup_users() {
    info "Creating system users (compiler:901, code:902, spj:903)..."
    for pair in "compiler:901:compiler" "code:902:code" "spj:903:spj,code"; do
        local user="${pair%%:*}"
        local uid=$(echo "$pair" | cut -d: -f2)
        local groups=$(echo "$pair" | cut -d: -f3)
        if id "$user" &>/dev/null; then
            ok "User $user already exists"
        else
            sudorun useradd -u "$uid" -r -s /sbin/nologin -M "$user"
            if [[ "$groups" != "$user" ]]; then
                sudorun usermod -aG "${groups#*,}" "$user"
            fi
            ok "User $user created"
        fi
    done
}

# ============================================================
#  3.  Directories
# ============================================================
setup_dirs() {
    info "Creating working directories..."
    for d in /judger/run /judger/spj /log; do
        if [[ ! -d "$d" ]]; then
            sudorun mkdir -p "$d"
        fi
    done
    sudorun chmod 777 /judger/run /judger/spj /log
    ok "Directories ready"

    mkdir -p "$INPUTS_DIR"
}

# ============================================================
#  4.  Clone Judger & Build
# ============================================================
build_judger() {
    if [[ -d "${JUDGE_DIR}/Judger" ]]; then
        info "Judger already cloned, updating..."
        cd "${JUDGE_DIR}/Judger" && git pull --ff-only 2>/dev/null || true
        cd "$BASE_DIR"
    else
        info "Cloning Judger..."
        mkdir -p "$JUDGE_DIR"
        git clone --config 'url.https://github.com/.insteadof=' "$JUDGER_REPO" "${JUDGE_DIR}/Judger"
    fi

    info "Building libjudger.so..."
    cd "${JUDGE_DIR}/Judger"
    # Fix cmake version requirement for newer cmake
    sed -i 's/cmake_minimum_required(VERSION 2.5)/cmake_minimum_required(VERSION 3.5)/' CMakeLists.txt
    mkdir -p build
    cd build
    cmake .. -DCMAKE_BUILD_TYPE=Release
    make -j"$(nproc)"
    cd "$BASE_DIR"
    ok "libjudger.so built at ${JUDGE_DIR}/Judger/output/libjudger.so"
}

install_libjudger() {
    if [[ ! -f "${JUDGE_DIR}/Judger/output/libjudger.so" ]]; then
        warn "libjudger.so not built, run build step first" && return
    fi
    info "Installing libjudger.so to /usr/lib/judger/..."
    sudorun mkdir -p /usr/lib/judger
    sudorun cp "${JUDGE_DIR}/Judger/output/libjudger.so" /usr/lib/judger/
    sudorun chmod 755 /usr/lib/judger/libjudger.so
    ok "libjudger.so installed"

    # Python binding — patch to use system path as default
    local init_py="${JUDGE_DIR}/Judger/bindings/Python/_judger/__init__.py"
    if grep -q 'os.environ.get' "$init_py" 2>/dev/null; then
        ok "Python binding already patched"
    else
        sed -i "s|proc_args = \[.*\]|import os\n_judger_path = os.environ.get(\"JUDGER_PATH\", \"/usr/lib/judger/libjudger.so\")\nproc_args = [_judger_path]|" "$init_py"
    fi
}

# ============================================================
#  5.  Clone JudgeServer & Setup
# ============================================================
setup_judgeserver() {
    if [[ -d "${JUDGE_DIR}/JudgeServer" ]]; then
        info "JudgeServer already cloned, updating..."
        cd "${JUDGE_DIR}/JudgeServer" && git pull --ff-only 2>/dev/null || true
        cd "$BASE_DIR"
    else
        info "Cloning JudgeServer..."
        git clone --config 'url.https://github.com/.insteadof=' "$JUDGESERVER_REPO" "${JUDGE_DIR}/JudgeServer"
    fi

    # Patch compiler.py — handle non-root judger gracefully
    local compiler_py="${JUDGE_DIR}/JudgeServer/server/compiler.py"
    if grep -q 'result.get("error"' "$compiler_py" 2>/dev/null; then
        ok "compiler.py already patched"
    else
        sed -i 's/if result\["result"\] != _judger.RESULT_SUCCESS:/if result["result"] != _judger.RESULT_SUCCESS or result.get("error", 0) != 0:/' "$compiler_py"
        sed -i 's/os\.remove(compiler_out)/try:\n                    os.remove(compiler_out)\n                except OSError:\n                    pass/g' "$compiler_py"
    fi

    # Patch server.py — skip chown if not root
    local server_py="${JUDGE_DIR}/JudgeServer/server/server.py"
    if grep -q 'except PermissionError' "$server_py" 2>/dev/null; then
        ok "server.py already patched"
    else
        # Wrap chown calls in try/except
        sed -i 's/os\.chown(self\.work_dir, COMPILER_USER_UID, RUN_GROUP_GID)/try:\n                os.chown(self.work_dir, COMPILER_USER_UID, RUN_GROUP_GID)\n            except PermissionError:\n                logger.warning("chown failed (not root), continuing anyway")/' "$server_py"
        sed -i 's/os\.chown(src_path, COMPILER_USER_UID, 0)/try:\n                    os.chown(src_path, COMPILER_USER_UID, 0)\n                except PermissionError:\n                    logger.warning("src_path chown failed (not root), continuing anyway")/' "$server_py"
    fi

    # Patch judge_client.py — skip chown if not root
    local jc_py="${JUDGE_DIR}/JudgeServer/server/judge_client.py"
    if grep -q 'except PermissionError' "$jc_py" 2>/dev/null; then
        ok "judge_client.py already patched"
    else
        sed -i 's/os\.chown(self\._submission_dir, SPJ_USER_UID, 0)/try:\n            os.chown(self._submission_dir, SPJ_USER_UID, 0)\n            os.chown(user_out_file_path, SPJ_USER_UID, 0)\n        except PermissionError:\n            logger.warning("spj chown failed (not root), continuing anyway")/' "$jc_py"
    fi

    # Patch config.py — support env var overrides
    local cfg_py="${JUDGE_DIR}/JudgeServer/server/config.py"
    if grep -q 'os.environ.get("JUDGER_WORKSPACE_BASE"' "$cfg_py" 2>/dev/null; then
        ok "config.py already patched"
    else
        cat > /tmp/airoj_config_patch.py << 'PYEOF'
import os
import pwd
import grp

JUDGER_WORKSPACE_BASE = os.environ.get("JUDGER_WORKSPACE_BASE", "/judger/run")
LOG_BASE = os.environ.get("LOG_BASE", "/log")

COMPILER_LOG_PATH = os.path.join(LOG_BASE, "compile.log")
JUDGER_RUN_LOG_PATH = os.path.join(LOG_BASE, "judger.log")
SERVER_LOG_PATH = os.path.join(LOG_BASE, "judge_server.log")


def _get_uid(username, default=None):
    try:
        return pwd.getpwnam(username).pw_uid
    except KeyError:
        if default is not None:
            return default
        raise


def _get_gid(groupname, default=None):
    try:
        return grp.getgrnam(groupname).gr_gid
    except KeyError:
        if default is not None:
            return default
        raise


RUN_USER_UID = _get_uid(os.environ.get("RUN_USER", "code"))
RUN_GROUP_GID = _get_gid(os.environ.get("RUN_GROUP", "code"))

COMPILER_USER_UID = _get_uid(os.environ.get("COMPILER_USER", "compiler"))
COMPILER_GROUP_GID = _get_gid(os.environ.get("COMPILER_GROUP", "compiler"))

SPJ_USER_UID = _get_uid(os.environ.get("SPJ_USER", "spj"))
SPJ_GROUP_GID = _get_gid(os.environ.get("SPJ_GROUP", "spj"))

TEST_CASE_DIR = os.environ.get("TEST_CASE_DIR", "/test_case")
SPJ_SRC_DIR = os.environ.get("SPJ_SRC_DIR", "/judger/spj")
SPJ_EXE_DIR = os.environ.get("SPJ_EXE_DIR", "/judger/spj")
PYEOF
        cp "$cfg_py" "${cfg_py}.bak"
        cp /tmp/airoj_config_patch.py "$cfg_py"
        ok "config.py patched"
    fi

    # Compile unbuffer.so
    gcc -shared -fPIC -o "${JUDGE_DIR}/JudgeServer/server/unbuffer.so" "${JUDGE_DIR}/JudgeServer/server/unbuffer.c" 2>/dev/null || true

    ok "JudgeServer ready"
}

# ============================================================
#  6.  Venv & Python Dependencies
# ============================================================
setup_python() {
    if [[ -d "$VENV_DIR" ]]; then
        info "Virtual env exists, updating pip..."
    else
        info "Creating Python virtual environment..."
        python3 -m venv "$VENV_DIR"
    fi

    info "Installing Python packages..."
    "${VENV_DIR}/bin/pip" install --upgrade pip setuptools wheel
    "${VENV_DIR}/bin/pip" install flask gunicorn psutil requests idna

    # Install judger Python binding
    if [[ -f "${JUDGE_DIR}/Judger/bindings/Python/pyproject.toml" ]]; then
        # Create judger symlink for backward compat
        ln -sf _judger "${JUDGE_DIR}/Judger/bindings/Python/judger" 2>/dev/null || true
        "${VENV_DIR}/bin/pip" install -e "${JUDGE_DIR}/Judger/bindings/Python" --force-reinstall 2>/dev/null
    fi

    ok "Python environment ready"
}

# ============================================================
#  7.  Backend Server
# ============================================================
setup_backend() {
    if [[ -f "${BACKEND_DIR}/server.py" ]]; then
        info "Backend already exists, skipping..."
        return
    fi

    info "Backend setup not included in this script —"
    info "Run: cd ${BASE_DIR}/backend && ${VENV_DIR}/bin/python3 server.py"
}

# ============================================================
#  8.  Systemd Service
# ============================================================
setup_systemd() {
    info "Installing systemd service for JudgeServer..."
    cat > /tmp/judge_server.service << EOF
[Unit]
Description=AirOJ Judge Server
After=network.target

[Service]
Type=simple
User=root
Environment=TOKEN=${JUDGE_SERVER_TOKEN}
Environment=BACKEND_URL=http://localhost:8080/api/judge_server_heartbeat
Environment=SERVICE_URL=http://localhost:12358
Environment=JUDGER_PATH=${JUDGE_DIR}/Judger/output/libjudger.so
Environment=LOG_BASE=/log
WorkingDirectory=${JUDGE_DIR}/JudgeServer/server
ExecStart=${VENV_DIR}/bin/gunicorn server:app \
    --workers 2 --threads 4 \
    --bind 0.0.0.0:12358 \
    --error-logfile /log/gunicorn.log \
    --access-logfile /log/access.log
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
    sudorun cp /tmp/judge_server.service /etc/systemd/system/judge_server.service
    sudorun systemctl daemon-reload
    sudorun systemctl enable judge_server 2>/dev/null || true
    ok "Systemd service installed (start with: sudo systemctl start judge_server)"
}

# ============================================================
#  9.  Test JudgeServer Quick Check
# ============================================================
verify_installation() {
    info "Verifying installation..."

    # Check libjudger
    if [[ -f /usr/lib/judger/libjudger.so ]]; then
        ok "libjudger.so installed at /usr/lib/judger/"
    else
        warn "libjudger.so not at system path"
    fi

    # Check users
    for u in compiler code spj; do
        id "$u" &>/dev/null && ok "User $u exists" || warn "User $u missing"
    done

    # Check Python
    if "${VENV_DIR}/bin/python3" -c "import _judger; print('_judger OK')" 2>/dev/null; then
        ok "Python _judger module OK"
    else
        warn "Python _judger module not importable"
    fi

    # Check directories
    for d in /judger/run /judger/spj /log; do
        [[ -d "$d" ]] && ok "Dir $d exists" || warn "Dir $d missing"
    done

    ok "Verification complete"
}

# ============================================================
#  Main
# ============================================================
main() {
    echo ""
    echo -e "${CYAN}========================================${NC}"
    echo -e "${CYAN}   AirOJ — One-Click Installer${NC}"
    echo -e "${CYAN}========================================${NC}"
    echo "  Target: ${BASE_DIR}"
    echo "  Token:  ${JUDGE_SERVER_TOKEN}"
    echo ""

    # Detect package manager hint
    if command -v pacman &>/dev/null; then
        PKG_HINT="sudo pacman -S --noconfirm cmake redis mariadb libseccomp gcc git"
    elif command -v apt &>/dev/null; then
        PKG_HINT="sudo apt install -y cmake redis-server mariadb-server libseccomp-dev gcc g++ git python3 python3-pip python3-venv"
    elif command -v dnf &>/dev/null; then
        PKG_HINT="sudo dnf install -y cmake redis mariadb libseccomp-devel gcc gcc-c++ git python3"
    else
        PKG_HINT="(install cmake, redis, mariadb, libseccomp, gcc, git, python3 manually)"
    fi

    echo -e "${YELLOW}Prerequisites:${NC}"
    echo "  ${PKG_HINT}"
    echo ""

    read -rp "Proceed with installation? [Y/n] " ok
    [[ "${ok:-Y}" =~ ^[Nn] ]] && exit 0

    install_system_deps
    setup_users
    setup_dirs
    build_judger
    install_libjudger
    setup_judgeserver
    setup_python
    setup_systemd
    verify_installation

    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}   Installation Complete!${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
    echo "  Start JudgeServer:  sudo systemctl start judge_server"
    echo "  Start Backend:      cd ${BACKEND_DIR} && ${VENV_DIR}/bin/python3 server.py"
    echo ""
    echo "  JudgeServer URL:    http://localhost:12358"
    echo "  Backend URL:        http://localhost:${BACKEND_PORT}"
    echo "  Token:              ${JUDGE_SERVER_TOKEN}"
    echo ""
    echo "  Test with:"
    echo "    HASH=\$(echo -n '${JUDGE_SERVER_TOKEN}' | sha256sum | cut -d' ' -f1)"
    echo "    curl -X POST http://localhost:12358/ping \\"
    echo "      -H \"X-Judge-Server-Token: \$HASH\" -H \"Content-Type: application/json\" -d '{}'"
    echo ""
}

main "$@"
