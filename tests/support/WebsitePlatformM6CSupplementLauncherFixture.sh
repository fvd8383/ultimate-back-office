#!/usr/bin/env bash
# Synthetic launcher dependencies only: never invokes Docker, SQL, or host admission.
set -Eeuo pipefail
scenario=$1; shift
source "${BASH_SOURCE[0]%/*}/../RunM6CNative.sh"
if [[ $scenario == select ]]; then
    m6n_preflight() { printf 'SELECTED=%s\n' "$profile_version"; }
    m6n_run() { printf 'UNEXPECTED-RUN\n'; exit 91; }
    m6n_finish() { :; }
    # Environment variables alone must not select the supplement.
    profile=supplement-v1; profile_version=m6c-native-supplement-v1
    m6n_main "$@"
elif [[ $scenario == collect || $scenario == capture ]]; then
    evidence=$1; php=$2; profile_version=$3; sha=$4; token=$5
    if command -v cygpath >/dev/null; then evidence=$(cygpath -u "$evidence"); php=$(cygpath -u "$php"); fi
    repo=$(realpath "${BASH_SOURCE[0]%/*}/../.."); started=$SECONDS
    if [[ $scenario == collect ]]; then
        m6n_capture() { return 0; }
        m6n_guard() { printf 'UNEXPECTED-CORE-GUARD\n'; exit 92; }
    else
        runtime_id=$(printf '%064d' 0); runtime_image=synthetic; network_id=synthetic
        # Metadata admission, runtime reader and file-size limit are explicit doubles;
        # the selected transport and acceptance PHP calls below are real local code.
        ulimit() { [[ $# == 2 && $1 == -f && $2 == 4 ]]; }
        m6n_guard() { [[ $1 == capture-owner ]]; }
        m6n_docker() {
            if [[ $1 == inspect ]]; then printf '[]'; return; fi
            [[ $# == 11 && $1 == exec && $2 == "$runtime_id" && $8 == read && $9 == "$sha" && ${10} == "$token" && ${11} == m6c-native-supplement-v1 ]] || return 94
            cat "$evidence/source.json"
        }
    fi
    m6n_collect_result 0
else exit 93; fi
