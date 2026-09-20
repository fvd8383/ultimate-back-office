#!/usr/bin/env bash
# Each scenario runs in its own process. All Docker/systemd commands are doubles.
set -Eeuo pipefail
export PATH="/usr/bin:/bin:$PATH"
source "${BASH_SOURCE[0]%/*}/../RunM6BMySql.sh"
scenario=$1; fixture=$(realpath "$2"); test_php=$3
uid=1001; expected_user=codex-validation; expected_host=ubo-m6b-validate; host_mode=dedicated
token=0123456789abcdef0123456789abcdef; password=abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789
container_id=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
image_id=sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
export M6B_MYSQL_RUN_TOKEN=$token M6B_MYSQL_IMAGE_ID=$image_id M6B_MYSQL_CONTAINER_ID=$container_id
log="$fixture/commands"; evidence="$fixture/evidence"; runtime_dir="$fixture/runtime"; scratch="$runtime_dir/ubo-m6b.test"
mkdir -p "$evidence" "$scratch"
touch "$evidence/report.txt" "$evidence/test-output.txt" "$evidence/mysql-tail.txt"
docker() { printf 'UNEXPECTED Docker\n' >> "$log"; return 97; }
systemctl() { printf 'UNEXPECTED systemctl\n' >> "$log"; return 97; }
uname() { echo Linux; }
id() { echo codex-validation; }
hostname() { echo "$expected_host"; }
case $scenario in
    identity-*)
        case $scenario in
            identity-host) hostname() { echo unexpected; };;
            identity-user) id() { echo somebody; };;
            identity-root) uid=0;;
            identity-stage-default) expected_host=ubo-stage-app;;
            identity-shared) host_mode=shared-staging; expected_host=ubo-stage-app;;
            identity-shared-user) host_mode=shared-staging; expected_host=ubo-stage-app; expected_user=somebody; id() { echo somebody; };;
            identity-production) host_mode=shared-staging; expected_host=ubo-prod-app;;
            identity-production-default) expected_host=ubo-prod-app;;
        esac
        m6_identity;;
    checkout-*)
        base="$fixture"; repo="$base/checkouts/repo"; expected_sha=cccccccccccccccccccccccccccccccccccccccc
        baseline=dddddddddddddddddddddddddddddddddddddddd; migration_hash=eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee
        mkdir -p "$repo/.git" "$repo/database/migrations"
        for number in {1..24}; do printf -v number '%03d' "$number"; touch "$repo/database/migrations/${number}_fixture.sql"; done
        m6_git() {
            case "$*" in
                'rev-parse --absolute-git-dir') echo "$repo/.git";; 'rev-parse --git-common-dir') echo .git;; 'rev-parse --show-toplevel') echo "$repo";;
                'rev-parse HEAD') [[ $scenario != checkout-sha ]] && echo "$expected_sha" || echo wrong;;
                status*) [[ $scenario != checkout-dirty ]] || echo modified; return 0;;
                ls-files*) [[ $scenario != checkout-untracked ]] || echo .env; return 0;;
                cat-file*) return 0;;
                hash-object*) [[ $scenario != checkout-history ]] && echo blob || echo changed;;
                rev-parse*) echo blob;; *) return 97;;
            esac
        }
        sha256sum() { [[ $scenario != checkout-hash ]] && printf '%s  file\n' "$migration_hash" || echo wrong; }
        [[ $scenario != checkout-deployed ]] || repo=/var/www/ubo-repo
        [[ $scenario != checkout-webroot ]] || repo="$base/checkouts/www/repo"
        [[ $scenario != checkout-worktree ]] || { rmdir "$repo/.git"; touch "$repo/.git"; }
        m6_checkout;;
    endpoint-*)
        socket=/run/user/1001/docker.sock
        unset DOCKER_HOST DOCKER_CONTEXT DOCKER_TLS_VERIFY DOCKER_CERT_PATH DOCKER_API_VERSION
        realpath() { echo "$1"; }
        stat() { [[ $2 == '%F:%u' ]] && echo socket:1001 || echo 1001:700; }
        docker() {
            printf '%s\n' "$*" >> "$log"
            case "$*" in 'context show') echo rootless;; 'context inspect rootless --format {{.Endpoints.docker.Host}}') [[ $scenario != endpoint-remote ]] && echo unix:///run/user/1001/docker.sock || echo ssh://remote;; *) return 97;; esac
        }
        case $scenario in endpoint-ambiguous) export DOCKER_HOST=unix:///run/user/1001/docker.sock DOCKER_CONTEXT=rootless;; endpoint-conflict) export DOCKER_HOST=tcp://elsewhere;; endpoint-rootful) stat() { echo socket:0; };; esac
        m6_endpoint;;
    delegation-*)
        stat() { [[ $1 == -f ]] && echo cgroup2fs || echo 1001; }
        cat() { [[ $scenario != delegation-controller ]] && echo 'cpu memory pids' || echo 'memory pids'; }
        m6_system() { case "$*" in *ActiveState*) echo active;; *Delegate*) [[ $scenario != delegation-ignored ]] && echo yes || echo no;; *MainPID*) echo 4321;; *) return 97;; esac; }
        m6_delegation;;
    headroom-*)
        base=$fixture; docker_root=$fixture
        m6_available_memory() { [[ $scenario != headroom-memory ]] && echo 4294967296 || echo 1024; }
        m6_free() { [[ $scenario != headroom-disk ]] && echo 10737418240 || echo 1024; }
        getconf() { [[ $scenario != headroom-cpu ]] && echo 4 || echo 2; }
        [[ $scenario == headroom-dedicated ]] || host_mode=shared-staging
        m6_headroom;;
    cgroup-*)
        m6_system() { echo /user.slice/user-1001.slice/docker.scope; }
        realpath() { echo "$1"; }
        cat() { case $1 in */cpu.max) [[ $scenario != cgroup-ignored ]] && echo '100000 100000' || echo 'max 100000';; */memory.max) echo 1610612736;; */memory.swap.max) echo 0;; */pids.max) echo 128;; esac; }
        m6_cgroup;;
    run-*)
        # Exercise the real run/trap flow with process-local doubles; no Docker, systemd or SQL.
        rmdir "$scratch"
        base=$fixture; mkdir -p "$base/evidence"; repo=$(realpath "${BASH_SOURCE[0]%/*}/../..")
        expected_sha=cccccccccccccccccccccccccccccccccccccccc; migration_hash=eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee
        digest=$image_id; platform=linux/amd64; socket=/run/user/1001/docker.sock; endpoint=unix://$socket
        account_home=$fixture; docker_root=$fixture; disk_floor=1073741824; memory_floor=268435456; image_bytes=1024
        fake_php() { case "$*" in *'random_bytes(16)'*) printf '%s' "$token";; *'random_bytes(32)'*) printf '%s' "$password";; *) echo 'PHP fixture';; esac; }
        php=fake_php
        flock() { return 0; }
        m6_available_memory() { echo 4294967296; }; m6_free() { echo 10737418240; }
        m6_guard() {
            local path="$repo/tests/support/WebsitePlatformM6BLinuxGuard.php"
            if command -v cygpath >/dev/null; then path=$(cygpath -w "$path"); fi
            "$test_php" -n "$path" "$@"
        }
        m6_docker() {
            printf '%s\n' "$1" >> "$log"
            case $1 in
                version) echo 'Docker fixture';;
                create) echo aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa;;
                start) [[ $scenario != run-partial-start ]];;
                inspect)
                    if [[ $* == *SizeRw* ]]; then echo 1024
                    elif [[ $* == *OOMKilled* ]]; then echo false
                    elif [[ $scenario == run-foreign && -f $fixture/test-done ]]; then cat "$fixture/foreign.json"
                    else cat "$fixture/container.json"; fi;;
                logs) printf 'database diagnostic %s %s\n' "$token" "$password";;
                rm) [[ $scenario != run-cleanup-failure ]];;
                *) return 97;;
            esac
        }
        m6_cgroup() { cg="$fixture/cgroup"; mkdir -p "$cg"; printf 'oom 0\noom_kill 0\n' > "$cg/memory.events"; printf 'max 0\n' > "$cg/pids.events"; }
        m6_php_cgroup() { php_cg=$cg; : > "$php_cg/cgroup.procs"; [[ $scenario != run-supervisor-limit ]] || m6_fail php_limits_not_effective; }
        m6_system() {
            case "$*" in
                *LoadState*) [[ -f $fixture/unit ]] && echo loaded || echo not-found;;
                *Description*) [[ ! -f $fixture/unit ]] || echo "M6B validation $token";;
                *MemorySwapMax*) echo 0;; *MemoryMax*) echo 1073741824;; *TasksMax*) echo 32;; *KillMode*) echo control-group;;
                *Result*) case $scenario in run-timeout) echo timeout;; run-resource) echo oom-kill;; *) echo success;; esac;;
                stop*) printf 'stop-unit\n' >> "$log"; touch "$fixture/stopped";;
                *ActiveState*) [[ -f $fixture/stopped ]] && echo inactive || echo active;;
                *) return 97;;
            esac
        }
        systemctl() { echo 'systemd fixture'; }
        # Git Bash cannot provide Linux FIFOs/systemd. Model the stream with a gated private file.
        mkfifo() { touch "${@: -1}"; }
        eval "$(declare -f m6_redact | sed '1s/m6_redact/m6_fixture_redact/')"
        m6_redact() { if [[ ! -f $fixture/test-done ]]; then for i in {1..100}; do [[ ! -f $fixture/test-done ]] || break; sleep 0.05; done; fi; m6_fixture_redact; }
        timeout() { shift 2; "$@"; }
        systemd-run() {
            touch "$fixture/unit"
            for i in {1..100}; do [[ ! -f $scratch/go && ! -f $fixture/stopped ]] || break; sleep 0.05; done
            printf 'synthetic result %s %s\n' "$password" "$token"
            touch "$fixture/test-done"
            case $scenario in run-test-failure) return 17;; run-timeout) return 124;; run-resource) return 137;; *) return 0;; esac
        }
        # No process groups exist in this fake supervisor; refuse group signals in the fixture.
        kill() { [[ $* != *--* ]] || return 0; builtin kill "$@"; }
        m6_run;;
    check-only)
        m6_preflight() { printf 'prerequisites\n' >> "$log"; }
        m6_run() { printf 'UNEXPECTED credentials container SQL workers\n' >> "$log"; exit 97; }
        m6_main --check-only --expected-sha cccccccccccccccccccccccccccccccccccccccc --expected-host ubo-m6b-validate --expected-user codex-validation --php /usr/bin/php --docker-socket /run/user/1001/docker.sock --image-digest sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb --platform linux/amd64;;
    cleanup-*|exit-*|partial-start)
        container_attempted=1; unit_attempted=0; collector=''; supervisor=''; test_exit=17; failure=test_failure
        m6_guard() {
            local path="${BASH_SOURCE[0]%/*}/WebsitePlatformM6BLinuxGuard.php"
            if command -v cygpath >/dev/null; then path=$(cygpath -w "$path"); fi
            "$test_php" -n "$path" "$@"
        }
        m6_docker() {
            printf '%s\n' "$1" >> "$log"
            case $1 in inspect) [[ $scenario != cleanup-unknown ]] || return 2; cat "$fixture/container.json";; logs) printf 'diagnostic %s %s %s\n' "$token" "$password" "${token:0:16}";; rm) [[ $scenario != cleanup-failed ]];; *) return 97;; esac
        }
        if [[ $scenario == partial-start ]]; then container_id=''; unset M6B_MYSQL_CONTAINER_ID; fi
        trap m6_finish EXIT
        case $scenario in
            exit-timeout) failure=timeout; test_exit=124; exit 124;;
            exit-interrupt) failure=interrupted; test_exit=130; trap 'exit 130' INT; kill -INT $$;;
            exit-test) exit 17;;
            partial-start) m6_fail container_start;;
            *) test_exit=0; failure=none; exit 0;;
        esac;;
    redact)
        { printf '%04090d' 0; printf '%s %s %s\n' "$password" "$token" "${token:0:16}"; } | m6_redact
        ;;
    output-bound)
        head -c 2100000 /dev/zero | tr '\0' x | m6_redact > "$evidence/test-output.txt"
        [[ -f $scratch/output-limit && $(wc -c < "$evidence/test-output.txt") -le 2097152 ]];;
    *) exit 97;;
esac
