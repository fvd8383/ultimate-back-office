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
docker() { printf 'FORBIDDEN docker binary\n' >> "$log"; return 97; }
systemctl() { printf 'FORBIDDEN systemctl\n' >> "$log"; return 97; }
m6n_volume() { [[ $scenario != mount-loss ]]; }
m6n_inspect() { [[ $scenario != inspection-mismatch ]]; }
m6n_docker() {
    printf '%s\n' "$1 ${2:-}" >> "$log"
    case "$1 ${2:-}" in
        'network ls') return 0;;
        'container ls') return 0;;
        'network create') touch "$fixture/network"; printf '%s\n' "$expected_network";return;;
        'network inspect')
            if [[ ${3:-} == --format ]]; then echo "$expected_network";
            elif [[ -f $fixture/db || -f $fixture/runtime ]]; then cat "$fixture/network-occupied.json";
            else cat "$fixture/network.json";fi;return;;
        'network rm') rm "$fixture/network";return;;
    esac
    if [[ $1 == image && $2 == inspect ]]; then
        if [[ $* == *--format* ]]; then [[ $scenario == oversized-images ]] && echo 6442450944 || echo 2048;return;fi
        if [[ $3 == "docker.io/library/mysql@$mysql_digest" ]]; then
            [[ $scenario != uncached-mysql ]] || return 1;cat "$fixture/db-image.json"
        else [[ $scenario != uncached-runtime ]] || return 1;cat "$fixture/runtime-image.json";fi;return
    fi
    if [[ $1 == create ]]; then
        local role=db; [[ " $* " != *" --name ubo-m6c-runtime-"* ]] || role=runtime
        [[ $scenario != create-failure || $role != runtime ]] || return 21
        touch "$fixture/$role"
        [[ $scenario != lost-create-reply || $role != runtime ]] || return 22
        [[ $role == db ]] && echo "$expected_db" || echo "$expected_runtime";return
    fi
    if [[ $1 == start ]]; then [[ $scenario != start-failure || $2 != "$expected_runtime" ]];return;fi
    if [[ $1 == cp ]]; then
        [[ $scenario != missing-evidence && $scenario != failed-child-missing ]] || return 1
        [[ $scenario != malformed-evidence ]] || { printf '{broken' > "$3";return; }
        cp "$fixture/report.json" "$3";return
    fi
    local role=db; [[ ${2:-} != "$expected_runtime" && ${2:-} != "ubo-m6c-runtime-$token" ]] || role=runtime
    if [[ $1 == inspect ]]; then
        [[ -f $fixture/$role ]] || return 1
        case ${4:-} in
            '{{.Id}}') [[ $role == db ]] && echo "$expected_db" || echo "$expected_runtime";;
            '{{.HostConfig.NetworkMode}}') echo "$expected_network";;
            '{{json .State}}') printf '{"Running":false,"ExitCode":143,"OOMKilled":false}\n';;
            '{{.State.Running}}') echo false;;
            *) if [[ $scenario == foreign-cleanup && $role == runtime ]]; then cat "$fixture/foreign.json";else cat "$fixture/$role.json";fi;;
        esac;return
    fi
    if [[ $1 == stop ]]; then [[ $scenario != stop-failure ]];return;fi
    if [[ $1 == rm ]]; then rm "$fixture/$role";return;fi
    printf 'UNEXPECTED fake command\n' >> "$log";return 97
}
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
trap 'interrupted=143' TERM
m6n_start_containers
case $scenario in
    timeout) started=$((SECONDS-1200));m6n_checkpoint;;
    signal) kill -TERM $$;m6n_checkpoint;;
    failed-child|failed-child-missing) m6n_collect_result 17;;
    primary-and-cleanup-failure) scenario=stop-failure;exit 17;;
    publication-failure) sha256sum(){ return 31; };;
    *) m6n_collect_result 0;;
esac
exit 0
