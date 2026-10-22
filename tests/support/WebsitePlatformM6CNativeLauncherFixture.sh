#!/usr/bin/env bash
# Synthetic only: all engine/systemd/OS admission leaves are doubles. No Docker invocation.
set -Eeuo pipefail
source "${BASH_SOURCE[0]%/*}/../RunM6CNative.sh"
scenario=$1; fixture=$2; php=$3
if command -v cygpath >/dev/null; then fixture=$(cygpath -u "$fixture"); php=$(cygpath -u "$php"); fi
repo=$(cd "${BASH_SOURCE[0]%/*}/../.." && pwd)
token=0123456789abcdef0123456789abcdef
sha=1111111111111111111111111111111111111111
mysql_digest=sha256:2222222222222222222222222222222222222222222222222222222222222222
php_base=sha256:3333333333333333333333333333333333333333333333333333333333333333
db_image=sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
runtime_image=sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc
expected_db=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
expected_runtime=eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee
expected_network=dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd
network_id='';db_id='';runtime_id='';network_attempted=0;db_attempted=0;runtime_attempted=0
started=$SECONDS;interrupted='';supervisor='';disk='';lock_identity=''
input=/synthetic/input; evidence="$fixture/evidence";evidence_path=$evidence;log="$fixture/commands"
mkdir "$evidence";printf 'synthetic fixture only\n' > "$evidence/report.txt"; : > "$log"
if [[ -f $fixture/ack.txt ]];then cp "$fixture/ack.txt" "$evidence/child.stderr.txt";fi
docker() { printf 'FORBIDDEN docker binary\n' >> "$log"; return 97; }
systemctl() { printf 'FORBIDDEN systemctl\n' >> "$log"; return 97; }
m6n_volume() { [[ $scenario != mount-loss ]]; }
m6n_inspect() { [[ $scenario != inspection-mismatch ]]; }
m6n_docker() {
    printf '%s\n' "$1 ${2:-}" >> "$log" || return $?
    case "$1 ${2:-}" in
        'network ls')
            if [[ -f $fixture/network ]]; then printf '%s\n' "$expected_network" || return $?;fi
            return 0;;
        'container ls')
            [[ $scenario != list-failure || -z ${cleanup_deadline:-} ]] || return 28
            if [[ $* == *"$expected_runtime"* || $* == *"ubo-m6c-runtime-$token"* ]]; then
                [[ ! -f $fixture/runtime ]] || { printf '%s\n' "$expected_runtime" || return $?; }
            elif [[ $* == *"$expected_db"* || $* == *"ubo-m6c-db-$token"* ]]; then
                [[ ! -f $fixture/db ]] || { printf '%s\n' "$expected_db" || return $?; }
            fi
            return 0;;
        'network create') touch "$fixture/network" || return $?; printf '%s\n' "$expected_network";return $?;;
        'network inspect')
            if [[ ${4:-} == --format ]]; then echo "$expected_network";
            elif [[ -f $fixture/db || -f $fixture/runtime ]]; then cat "$fixture/network-occupied.json";
            else cat "$fixture/network.json";fi;return $?;;
        'network rm') rm "$fixture/network";return $?;;
    esac
    if [[ $1 == image && $2 == inspect ]]; then
        if [[ $* == *--format* ]]; then if [[ $scenario == oversized-images ]];then echo 6442450944;else echo 2048;fi;return $?;fi
        if [[ $3 == "docker.io/library/mysql@$mysql_digest" ]]; then
            [[ $scenario != uncached-mysql ]] || return 1;cat "$fixture/db-image.json"
        else [[ $scenario != uncached-runtime ]] || return 1;cat "$fixture/runtime-image.json";fi;return $?
    fi
    if [[ $1 == create ]]; then
        local role=db; [[ " $* " != *" --name ubo-m6c-runtime-"* ]] || role=runtime
        [[ $scenario != create-failure || $role != runtime ]] || return 21
        touch "$fixture/$role" || return $?
        [[ $scenario != lost-create-reply || $role != runtime ]] || return 22
        if [[ $role == db ]];then echo "$expected_db";else echo "$expected_runtime";fi;return $?
    fi
    if [[ $1 == start ]]; then [[ $scenario != start-failure || $2 != "$expected_runtime" ]];return $?;fi
    if [[ $1 == cp ]]; then
        [[ $scenario != tmpfs-copy-unavailable ]] || return 66
        [[ $scenario != missing-evidence && $scenario != failed-child-missing ]] || return 1
        [[ $scenario != malformed-evidence ]] || { printf '{broken' > "$3";return $?; }
        cp "$fixture/report.json" "$3";return $?
    fi
    if [[ $1 == exec ]]; then
        [[ $2 == "$expected_runtime" && $3 == /usr/local/bin/php && $4 == -n && $5 == -d && $6 == memory_limit=128M \
            && $7 == /opt/ubo/tests/support/WebsitePlatformM6CNativeTransport.php && $8 == read && $9 == "$sha" && ${10} == "$token" && $# == 10 ]] || return 97
        [[ -f $fixture/runtime ]] || return 45
        case $scenario in
            missing-evidence|failed-child-missing|pending-only) return 44;;
            fallback-success|failed-fallback-success) [[ -f $evidence/capture-normal.txt ]] || return 44;;
            malformed-evidence) printf '{broken';return $?;;
            empty-report) return 0;;
            partial-report) head -c 20 "$fixture/report.json";return $?;;
            reader-nonzero) cat "$fixture/report.json";return 43;;
        esac
        cat "$fixture/report.json";return $?
    fi
    local role=db; [[ ${2:-} != "$expected_runtime" && ${2:-} != "ubo-m6c-runtime-$token" ]] || role=runtime
    if [[ $1 == inspect ]]; then
        [[ $scenario != inspect-failure || $role != runtime ]] || return 29
        [[ -f $fixture/$role ]] || return 1
        case ${4:-} in
            '{{.Id}}') if [[ $role == db ]];then echo "$expected_db";else echo "$expected_runtime";fi;;
            '{{.HostConfig.NetworkMode}}') echo "$expected_network";;
            '{{json .State}}') printf '{"Running":false,"ExitCode":143,"OOMKilled":false}\n';;
            '{{.State.Running}}') echo false;;
            *) if [[ $scenario == foreign-cleanup && $role == runtime ]]; then cat "$fixture/foreign.json";else cat "$fixture/$role.json";fi;;
        esac;return $?
    fi
    if [[ $1 == stop ]]; then [[ $scenario != stop-failure && ${fault:-} != stop ]];return $?;fi
    if [[ $1 == rm ]]; then rm "$fixture/$role";return $?;fi
    printf 'UNEXPECTED fake command\n' >> "$log";return 97
}
# Synthetic hooks wrap the real finalization functions. The PHP parent releases an
# exact PID/signal/phase handshake. This foreground child signals only its recorded
# shell PID (never a process group); INT is not inherited ignored by an async shell.
exec 3>&1 4<&0
owner_pid=$$; signal_plan=''; signal_phase=''; fault=''; primary_code=0
if [[ $scenario == fault-* ]]; then fault=${scenario#fault-};scenario=success;fi
if [[ $scenario == lost-create-stop ]]; then fault=stop;scenario=lost-create-reply;fi
if [[ $scenario == interrupt-* ]]; then
    spec=${scenario#interrupt-};signal_plan=${spec%%-*};spec=${spec#*-}
    signal_phase=${spec%%-*};[[ $spec != *-* ]] || fault=${spec#*-}
    scenario=success
fi
case $fault in primary)primary_code=17;;cleanup)scenario=stop-failure;;primarycleanup)primary_code=17;scenario=stop-failure;;primarypublish)primary_code=17;;esac
m6f_barrier() {
    [[ $1 == "$signal_phase" && ! -f $fixture/barrier ]] || return 0
    printf '%s\n' "$1" > "$fixture/barrier" || return 98
    local reply sig
    for sig in ${signal_plan//_/ }; do
        printf 'READY %s %s %s\n' "$owner_pid" "$sig" "$1" >&3 || return 98
        read -r -t 5 reply <&4 && [[ $reply == "GO $owner_pid $sig $1" ]] || return 98
        [[ $owner_pid =~ ^[1-9][0-9]*$ && $owner_pid == "$(< "$fixture/owner")" ]] || return 98
        kill -s "$sig" "$owner_pid" || return 98
        printf 'sent %s phase=%s\n' "$sig" "$1" >> "$fixture/deliveries" || return 98
    done
    return 0
}
printf '%s\n' "$owner_pid" > "$fixture/owner"
eval "$(declare -f m6n_docker | sed '1s/m6n_docker/m6f_docker/')"
m6n_docker() {
    if [[ -n ${cleanup_deadline:-} ]]; then
        case "$1 ${2:-}" in
            'stop --time')
                if [[ ${4:-} == "$expected_runtime" ]];then m6f_barrier runtime || return $?;else m6f_barrier db || return $?;fi;;
            'network inspect') m6f_barrier network || return $?;;
            "inspect $expected_runtime") [[ ${4:-} != '{{json .State}}' ]] || { m6f_barrier inspection || return $?; };;
        esac
    fi
    m6f_docker "$@"
}
eval "$(declare -f m6n_signal | sed '1s/m6n_signal/m6f_signal/')"
m6n_signal() {
    m6f_signal "$1"
    printf 'observed=%s first=%s deadline=%s\n' "$1" "$interrupted" "${cleanup_deadline:-operation}" >> "$fixture/signals"
}
eval "$(declare -f m6n_close_signals | sed '1s/m6n_close_signals/m6f_close_signals/')"
m6n_close_signals() {
    m6f_barrier boundary || publication=FAIL
    m6f_close_signals
    printf 'deadline=%s\n' "$cleanup_deadline" >> "$fixture/boundary"
    m6f_barrier frozen || publication=FAIL
}
eval "$(declare -f m6n_status | sed '1s/m6n_status/m6f_status/')"
m6n_status() {
    [[ ${mode:-} != commit || $fault != commitstatus ]] || return 32
    if [[ ${mode:-} == prepare ]]; then
        m6f_barrier status || return $?
        [[ $fault != status && $scenario != status-failure ]] || return 32
    fi
    m6f_status "$@"
}
printf() {
    [[ ${fault:-} != receiptwrite || ${1:-} != manifest_sha256=* ]] || return 36
    builtin printf "$@"
}
sha256sum() {
    if [[ ${mode:-} == prepare ]];then m6f_barrier hash || return $?;fi
    if [[ $fault == hash-timeout && ${mode:-} == prepare ]];then sleep 20;fi
    [[ $fault != hash && $scenario != publication-failure ]] || return 31
    if [[ $fault == receipthash && $* == *SHA256SUMS.pending* ]];then return 33;fi
    command sha256sum "$@"
}
mv() {
    if [[ $* == *PUBLICATION.pending* ]]; then
        m6f_barrier receipt || return $?
        if [[ $fault == commit-timeout ]];then sleep 10;fi
        [[ $fault != publish && $fault != primarypublish ]] || return 34
    fi
    [[ $fault != rename || $* != *SHA256SUMS.pending* ]] || return 35
    command mv "$@"
}
export fixture owner_pid signal_plan signal_phase fault scenario
export -f m6f_barrier m6f_status sha256sum mv printf
if [[ $scenario == leaf-status ]]; then
    leaf() {
        local expected=$1 label=$2 code output;shift 2
        output=$(m6n_docker "$@" 2>/dev/null);code=$?
        printf 'leaf=%s expected=%s actual=%s stdout=%s\n' "$label" "$expected" "$code" "$output"
        [[ $code == "$expected" ]] || exit 99
    }
    leaf_exit() {
        local operation=$?;trap - EXIT;set +e
        printf 'operation=%s\n' "$operation"
        leaf 0 network-create network create
        leaf 0 network-id network inspect "$expected_network" --format '{{.Id}}'
        leaf 0 network-metadata network inspect "$expected_network"
        leaf 0 network-list network ls
        leaf 0 image-mysql image inspect "docker.io/library/mysql@$mysql_digest"
        leaf 0 image-runtime image inspect "$runtime_image"
        leaf 0 image-size image inspect "$runtime_image" --format '{{.Size}}'
        leaf 0 create create --name "ubo-m6c-runtime-$token"
        leaf 0 start start "$expected_runtime"
        leaf 0 id inspect "$expected_runtime" --format '{{.Id}}'
        leaf 0 metadata inspect "$expected_runtime"
        leaf 0 binding inspect "$expected_runtime" --format '{{.HostConfig.NetworkMode}}'
        leaf 0 state inspect "$expected_runtime" --format '{{json .State}}'
        leaf 0 running inspect "$expected_runtime" --format '{{.State.Running}}'
        leaf 0 copy cp dummy "$fixture/copied"
        leaf 0 stop stop --time 5 "$expected_runtime"
        leaf 0 remove rm "$expected_runtime"
        leaf 0 absent-list container ls --filter "id=$expected_runtime"
        leaf 0 network-remove network rm "$expected_network"
        leaf 1 failed-remove rm "$expected_runtime"
        leaf 1 failed-network-remove network rm "$expected_network"
        leaf 1 absent-inspection inspect "$expected_runtime"
        leaf 1 failed-copy cp dummy "$fixture/absent/copied"
        scenario=malformed-evidence;leaf 0 malformed-copy cp dummy "$fixture/broken";leaf 1 failed-write cp dummy "$fixture/absent/broken"
        scenario=uncached-mysql;leaf 1 uncached-mysql image inspect "docker.io/library/mysql@$mysql_digest"
        scenario=uncached-runtime;leaf 1 uncached-runtime image inspect "$runtime_image"
        scenario=create-failure;leaf 21 create-failure create --name "ubo-m6c-runtime-$token"
        scenario=lost-create-reply;leaf 22 lost-create-reply create --name "ubo-m6c-runtime-$token"
        scenario=start-failure;leaf 1 start-failure start "$expected_runtime"
        scenario=stop-failure;leaf 1 stop-failure stop --time 5 "$expected_runtime"
        scenario=missing-evidence;leaf 1 missing-copy cp dummy "$fixture/copied"
        scenario=failed-child-missing;leaf 1 failed-child-copy cp dummy "$fixture/copied"
        scenario=success;leaf 97 unexpected unexpected
        rm "$fixture/runtime"
        m6n_owned_remove runtime '' "$runtime_image";printf 'verified_absent=%s\n' "$?"
        scenario=list-failure;cleanup_deadline=$((SECONDS+90))
        m6n_owned_remove runtime '' "$runtime_image";printf 'uncertain_absent=%s\n' "$?"
        touch "$fixture/runtime"
        scenario=inspect-failure
        m6n_owned_remove runtime '' "$runtime_image";printf 'uncertain_present=%s\n' "$?"
        scenario=foreign-cleanup
        m6n_owned_remove runtime "$expected_runtime" "$runtime_image";printf 'foreign_owner=%s\n' "$?"
        [[ -f $fixture/runtime ]] && printf 'foreign_marker_retained=1\n'
        scenario=success
        cleanup_deadline=$((SECONDS+8))
        m6n_evidence_run prepare "$evidence" "$sha" 17 0 PASS PASS 17;printf 'prepare_reserve=%s\n' "$?"
        cleanup_deadline=$((SECONDS+3))
        m6n_evidence_run commit "$evidence" "$sha" 17 0 PASS PASS 17;printf 'commit_reserve=%s\n' "$?"
        cleanup_deadline=$SECONDS
        m6n_evidence_run failure "$evidence" "$sha" 17 0 PASS FAIL 17;printf 'expired_deadline=%s\n' "$?"
        exit "$operation"
    }
    trap leaf_exit EXIT
    exit 17
fi

case $scenario in
    cached-images|uncached-mysql|uncached-runtime|oversized-images) m6n_cached_images;exit;;
    help) m6n_preflight(){ printf 'UNEXPECTED preflight\n' >> "$log";return 97; };m6n_main --help;exit;;
    check-only|missing-prerequisite|unavailable-image)
        m6n_preflight(){
            [[ $scenario != missing-prerequisite ]] || m6n_fail missing_tool
            [[ $scenario != unavailable-image ]] || m6n_fail prepare_cached_runtime_image
        }
        m6n_main --check-only --expected-sha "$sha" --mysql-digest "$db_image" --runtime-image "$runtime_image" --php-base-digest "$runtime_image" --php "$php";exit;;
    source-pack)
        if command -v cygpath >/dev/null; then
            fixture_git=$(command -v git)
            m6n_git() { env -i PATH="${fixture_git%/*}:/usr/bin:/bin" HOME=/nonexistent GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null "$fixture_git" --no-optional-locks -c core.fsmonitor=false -c core.hooksPath=/dev/null -C "$repo" "$@"; }
        fi
        real_repo=$repo; repo="$fixture/source";mkdir "$repo"
        git -C "$repo" init -q
        git -C "$repo" config user.name 'Synthetic fixture'
        git -C "$repo" config user.email 'synthetic@example.invalid'
        git -C "$repo" config core.autocrlf false
        printf 'deleted historical private bytes\n' > "$repo/deleted"
        git -C "$repo" add deleted;git -C "$repo" commit -qm before
        old=$(git -C "$repo" rev-parse HEAD:deleted)
        git -C "$repo" rm -q deleted
        printf 'ignored.env\n' > "$repo/.gitignore"
        printf 'exact tracked bytes\n' > "$repo/current"
        printf 'ignored operational secret\n' > "$repo/ignored.env"
        git -C "$repo" add .gitignore current;git -C "$repo" commit -qm current
        sha=$(git -C "$repo" rev-parse HEAD);m6n_export_source "$fixture/source.pack"
        checkout="$fixture/checkout";git init -q "$checkout"
        git -C "$checkout" config core.autocrlf false
        git -C "$checkout" index-pack --stdin < "$fixture/source.pack" >/dev/null
        printf '%s\n' "$sha" > "$checkout/.git/shallow"
        git -C "$checkout" update-ref --no-deref HEAD "$sha"
        git -C "$checkout" checkout --detach --force "$sha" >/dev/null
        git -C "$checkout" fsck --full
        [[ $(git -C "$checkout" rev-parse HEAD) == "$sha" && ! -e $checkout/ignored.env && ! -e $checkout/deleted ]]
        ! git -C "$checkout" cat-file -e "$old" 2>/dev/null
        cmp "$repo/current" "$checkout/current"
        printf 'exact commit/tree preserved; ignored and deleted historical bytes excluded\n';exit;;
esac
trap m6n_finish EXIT
trap 'm6n_signal 130' INT
trap 'm6n_signal 143' TERM
m6n_start_containers
if [[ $scenario == tmpfs-copy-unavailable ]]; then
    code=0;m6n_docker cp dummy "$fixture/copy-api-result" || code=$?
    printf 'SYNTHETIC copy_api_exit=%s; mounted report remains readable by exec\n' "$code" >> "$evidence/report.txt"
fi
if [[ -n $signal_plan ]]; then
    m6n_collect_result 0
    m6f_barrier operation || exit 98
    exit "$primary_code"
fi
case $scenario in
    timeout) started=$((SECONDS-1200));m6n_checkpoint;;
    signal) kill -TERM $$;m6n_checkpoint;;
    failed-child|failed-child-missing|valid-fail|failed-fallback-success) m6n_collect_result 17;;
    primary-and-cleanup-failure) scenario=stop-failure;exit 17;;
    publication-failure|status-failure) m6n_collect_result 0;;
    *) m6n_collect_result 0;;
esac
exit "$primary_code"
