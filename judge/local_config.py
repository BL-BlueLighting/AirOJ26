# Local config override for JudgeServer (AirOJ)
# Overrides hardcoded paths in server/config.py
# Imported before server code to patch os.environ or monkey-patch config module

import os
import pwd
import grp

# Allow override via environment variables
JUDGER_WORKSPACE_BASE = os.environ.get("JUDGER_WORKSPACE_BASE", "/judger/run")
LOG_BASE = os.environ.get("LOG_BASE", "/log")
TEST_CASE_DIR = os.environ.get("TEST_CASE_DIR", "/test_case")
SPJ_SRC_DIR = os.environ.get("SPJ_SRC_DIR", "/judger/spj")
SPJ_EXE_DIR = os.environ.get("SPJ_EXE_DIR", "/judger/spj")

# Try to get uid/gid for sandbox users
def _get_uid(username, default=0):
    try:
        return pwd.getpwnam(username).pw_uid
    except KeyError:
        return default

def _get_gid(groupname, default=0):
    try:
        return grp.getgrnam(groupname).gr_gid
    except KeyError:
        return default

RUN_USER_UID = _get_uid("code", 902)
RUN_GROUP_GID = _get_gid("code", 902)
COMPILER_USER_UID = _get_uid("compiler", 901)
COMPILER_GROUP_GID = _get_gid("compiler", 901)
SPJ_USER_UID = _get_uid("spj", 903)
SPJ_GROUP_GID = _get_gid("spj", 903)
