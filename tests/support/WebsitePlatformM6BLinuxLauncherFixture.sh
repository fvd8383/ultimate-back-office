#!/usr/bin/env bash
# Each scenario runs in its own process. All Docker/systemd commands are doubles.
set -Eeuo pipefail
export PATH="/usr/bin:/bin:$PATH"
source "${BASH_SOURCE[0]%/*}/../RunM6BMySql.sh"
if command -v cygpath >/dev/null; then
    # Model Linux absolute paths even when this Git Bash realpath emits C:/... paths.
    realpath() { local resolved; resolved=$(command realpath "$@") || return; cygpath -u "$resolved"; }
fi
scenario=$1; fixture=$(realpath "$2"); test_php=$3
uid=1001; expected_user=codex-validation; expected_host=ubo-m6b-validate; host_mode=dedicated
layout=home; cli_config=''; cli_identity=''; disk_scratch=''; base_fd=''; evidence_safe=1; volume_uuid=''; volume_mount_id=''; boot_floor=1073741824
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
    unit-*)
        unit="ubo-m6b-$token.service"; unit_attempted=1; php_cg="$fixture/cgroup"; mkdir -p "$php_cg"
        printf 'populated 0\nfrozen 0\n' > "$php_cg/cgroup.events"
        m6_group_path() { [[ $1 == "/user.slice/user-$uid.slice/app.slice/$unit" ]] || return 1; echo "$php_cg"; }
        if [[ $scenario == unit-populated-descendant ]]; then mkdir "$php_cg/child"; printf 'populated 1\n' > "$php_cg/cgroup.events"; fi
        if [[ $scenario == unit-unreadable ]]; then cat() { return 1; }; fi
        m6_system() {
            if [[ $1 == stop ]]; then
                printf 'stop-unit\n' >> "$log"
                [[ $scenario != unit-stop-failure ]] || return 1
                touch "$fixture/stopped"
                if [[ $scenario == unit-unload-removed ]]; then rm "$php_cg/cgroup.events"; rmdir "$php_cg"; fi
                return 0
            fi
            [[ $scenario != unit-manager-inaccessible && $scenario != unit-arbitrary-failure ]] || return 1
            if [[ $scenario == unit-absent || ( $scenario == unit-unload* && -f $fixture/stopped ) ]]; then
                printf 'LoadState=not-found\nActiveState=inactive\nDescription=%s\nControlGroup=\n' "$unit"; return 1
            fi
            description="M6B validation $token"; state=active
            [[ ! -f $fixture/stopped ]] || state=inactive
            [[ $scenario != unit-still-running ]] || state=active
            [[ $scenario != unit-foreign && ! ( $scenario == unit-replaced && -f $fixture/stopped ) ]] || description=foreign
            printf 'LoadState=loaded\nActiveState=%s\nDescription=%s\nControlGroup=/user.slice/user-%s.slice/app.slice/%s\n' "$state" "$description" "$uid" "$unit"
        }
        m6_stop_unit || exit 2;;
    client-*)
        m6_init; account_home=$fixture; endpoint=unix:///run/user/1001/docker.sock
        mkdir -p "$fixture/bin" "$fixture/user-docker"
        printf '{"proxies":{"default":{"httpProxy":"PROXY_CREDENTIAL_SENTINEL"}}}\n' > "$fixture/user-docker/config.json"
        export DOCKER_CONFIG="$fixture/user-docker" HTTP_PROXY=PROXY_CREDENTIAL_SENTINEL https_proxy=PROXY_CREDENTIAL_SENTINEL FIXTURE_LOG="$log"
        stat() { if [[ $2 == '%u:%a' ]]; then echo "$uid:700"; else command stat "$@"; fi; }
        cat > "$fixture/bin/docker" <<'DOCKER'
#!/usr/bin/env bash
set -eu
[[ $1 == --config && $(< "$2/config.json") == '{}' && $3 == --host && $4 == unix:///run/user/1001/docker.sock ]]
[[ -z ${DOCKER_CONFIG:-}${DOCKER_HOST:-}${DOCKER_CONTEXT:-}${HTTP_PROXY:-}${https_proxy:-} ]]
printf 'isolated-client %s\n' "$5" >> "$FIXTURE_LOG"
DOCKER
        chmod +x "$fixture/bin/docker"; export PATH="$fixture/bin:$PATH"
        m6_cli_config
        m6_docker info
        if [[ $scenario == client-create ]]; then m6_docker create; fi
        if [[ $scenario == client-cleanup-failure ]]; then touch "$cli_config/unexpected"; fi
        if [[ $scenario == client-signal ]]; then kill -TERM $$; fi
        exit 0;;
    volume-*)
        layout=volume; host_mode=shared-staging; expected_host=ubo-stage-app; expected_user=codex-validation
        volume_mount=/mnt/ubo_stage_testdata; base="$volume_mount/codex-validation"
        m6_volume_directory() { [[ $scenario != volume-unsafe-directory ]]; }
        m6_docker_directory() { [[ $1 == "$base/docker" ]] && m6_volume_directory "$base"; }
        timeout() { return 0; }
        # Metadata adapters model the operator-owned helper/marker without touching /etc or /mnt.
        m6_root_anchor() { [[ $scenario != volume-unsafe-marker ]]; }
        stat() { case $2 in %s) echo 37;; %d) [[ $3 == / ]] && echo 1 || echo 2;; %u) echo 1001;; *) return 97;; esac; }
        cat() { echo aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee; }
        realpath() { [[ $scenario != volume-symlink ]] && echo "$1" || echo /var/www/ubo-repo; }
        findmnt() {
            case "${@: -1}" in
                TARGET) [[ $scenario != volume-missing ]] && echo "$volume_mount" || echo /;;
                UUID) [[ $scenario != volume-wrong-uuid ]] && echo aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee || echo ffffffff-bbbb-cccc-dddd-eeeeeeeeeeee;;
                ID) echo 42;; *) return 97;;
            esac
        }
        # Executability is a separate filesystem leaf in production; the fixture has no host helper.
        m6_volume_helper() { return 0; }
        if [[ $scenario == volume-mount-loss ]]; then volume_mount_id=41; fi
        m6_volume_ok || exit 2
        if [[ $scenario == volume-wrong-docker-root ]]; then docker_root=/home/codex-validation/.local/share/docker; m6_docker_root; fi;;
    permission-*)
        host_mode=shared-staging; layout=volume; expected_host=ubo-stage-app
        volume_mount="$fixture/volume"; base="$volume_mount/codex-validation"
        mkdir -p "$base/docker" "$base/checkouts" "$base/evidence" "$base/tmp"
        docker_mode=710; docker_owner=$uid; docker_group=codex-validation; private_mode=700; private_path=''
        case $scenario in
            permission-docker-0700) docker_mode=700;;
            permission-docker-*) docker_mode=${scenario#permission-docker-}; docker_mode=${docker_mode#0};;
            permission-owner) docker_owner=2000;; permission-group) docker_group=foreign;;
            permission-base) private_path=$base; private_mode=710;;
            permission-checkouts|permission-evidence|permission-tmp) private_path="$base/${scenario#permission-}"; private_mode=710;;
        esac
        m6_root_anchor() { return 0; }; m6_volume_helper() { return 0; }
        stat() {
            local path=${@: -1} mode=700 owner=$uid group=codex-validation
            [[ $path != "$base/docker" ]] || { mode=$docker_mode; owner=$docker_owner; group=$docker_group; }
            [[ $path != "$private_path" ]] || mode=$private_mode
            case $2 in
                %s) echo 37;; %d) [[ $path != / ]] && echo 2 || echo 1;;
                %u) echo "$owner";; '%u:%a') echo "$owner:$mode";; '%u:%G:%a') echo "$owner:$group:$mode";; *) return 97;;
            esac
        }
        cat() { echo aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee; }
        realpath() { if [[ $scenario == permission-symlink && $1 == "$base/docker" ]]; then echo "$fixture/elsewhere"; else echo "$1"; fi; }
        findmnt() {
            case "${@: -1}" in
                UUID) echo aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee;; ID) echo 42;;
                TARGET) if [[ $scenario == permission-mount && $* == *"--target $base/docker"* ]]; then echo "$base/docker"; else echo "$volume_mount"; fi;;
                *) return 97;;
            esac
        }
        m6_volume_ok || exit 2
        docker_root="$base/docker"
        [[ $scenario != permission-other-path ]] || docker_root="$base/evidence"
        m6_docker_root
        # The same checks run again during monitoring and cleanup.
        [[ $scenario != permission-change-docker ]] || docker_mode=770
        [[ $scenario != permission-change-base ]] || { private_path=$base; private_mode=710; }
        m6_volume_ok || exit 2;;
    space-*)
        host_mode=shared-staging; layout=volume; base=$fixture; docker_root=$fixture
        nproc() { [[ $scenario != space-cpu-below ]] && echo 4 || echo 3; }
        m6_available_memory() { [[ $scenario != space-memory-below ]] && echo 4563402752 || echo 4563402751; }
        stat() { echo 2; }
        m6_free() {
            if [[ $1 == / ]]; then [[ $scenario != space-boot-below ]] && echo 1073741824 || echo 1073741823
            else [[ $scenario != space-volume-below ]] && echo 10737418240 || echo 10737418239; fi
        }
        m6_headroom
        if [[ $scenario == space-reserve* ]]; then
            evidence=$fixture; container_id=test; image_bytes=1024
            m6_volume_ok() { return 0; }; m6_docker() { echo 0; }
            m6_available_memory() { [[ $scenario != space-reserve-memory ]] && echo 1610612736 || echo 1610612735; }
            m6_free() { if [[ $1 == / ]]; then [[ $scenario != space-reserve-boot ]] && echo 1073741824 || echo 1073741823; else [[ $scenario != space-reserve-volume ]] && echo 4294967296 || echo 4294967295; fi; }
            m6_budget || exit 2
        fi;;
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
        case $scenario in endpoint-ambiguous) export DOCKER_HOST=unix:///run/user/1001/docker.sock DOCKER_CONTEXT=rootless;; endpoint-remote) export DOCKER_HOST=ssh://remote;; endpoint-context) export DOCKER_CONTEXT=rootless;; endpoint-conflict) export DOCKER_HOST=tcp://elsewhere;; endpoint-rootful) stat() { echo socket:0; };; esac
        m6_endpoint;;
    delegation-*)
        stat() { [[ $1 == -f ]] && echo cgroup2fs || echo 1001; }
        cat() { [[ $scenario != delegation-controller ]] && echo 'cpu memory pids' || echo 'memory pids'; }
        m6_system() { case "$*" in *ActiveState*) echo active;; *Delegate*) [[ $scenario != delegation-ignored ]] && echo yes || echo no;; *MainPID*) echo 4321;; *) return 97;; esac; }
        m6_delegation;;
    headroom-*)
        base=$fixture; docker_root=$fixture
        m6_available_memory() { [[ $scenario != headroom-memory ]] && echo 4563402752 || echo 1024; }
        m6_free() { [[ $scenario != headroom-disk ]] && echo 10737418240 || echo 1024; }
        nproc() { [[ $scenario != headroom-cpu ]] && echo 4 || echo 2; }
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
        m6_init
        signal_name=TERM; signal_point=''
        if [[ $scenario == run-signal-* ]]; then
            signal_point=${scenario#run-signal-}; signal_name=${signal_point%%-*}; signal_point=${signal_point#*-}
        fi
        fixture_await() {
            local end=$((SECONDS + 10))
            until "$@"; do (( SECONDS < end )) || { printf 'Fixture barrier timed out\n' >&2; exit 97; }; command sleep 0.01; done
        }
        fixture_signal() {
            local delivered=$signal_name
            if [[ ( $signal_point == repeated || $signal_point == accepted-published ) && -f $fixture/signalled ]]; then
                [[ $signal_name != TERM ]] && delivered=TERM || delivered=INT
            fi
            printf 'signal-%s\n' "$delivered" >> "$log"
            : > "$fixture/signalled"
            builtin kill -"$delivered" "$fixture_main_pid"
        }
        base=$fixture; mkdir -p "$base/evidence" "$base/tmp"; repo=$(realpath "${BASH_SOURCE[0]%/*}/../..")
        expected_sha=cccccccccccccccccccccccccccccccccccccccc; migration_hash=eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee
        digest=sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd; platform=linux/amd64; socket=/run/user/1001/docker.sock; endpoint=unix://$socket
        account_home=$fixture; docker_root=$fixture; disk_floor=1073741824; memory_floor=268435456; image_bytes=1024
        fake_php() { case "$*" in *'random_bytes(16)'*) printf '%s' "$token";; *'random_bytes(32)'*) printf '%s' "$password";; *) echo 'PHP fixture';; esac; }
        php=fake_php
        nproc() { echo 4; }
        flock() { return 0; }
        stat() { if [[ $2 == '%u:%a' ]]; then echo "$uid:700"; else command stat "$@"; fi; }
        m6_pin_storage() { storage_base=$base; }
        m6_group_path() { [[ $1 == "/user.slice/user-$uid.slice/"*"/$unit" ]] || return 1; echo "$fixture/cgroup"; }
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
                create) touch "$fixture/owned-container"; echo aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa;;
                start) [[ $scenario != run-partial-start ]];;
                inspect)
                    if [[ $* == *SizeRw* ]]; then echo 1024
                    elif [[ $* == *OOMKilled* ]]; then
                        [[ ! -f $fixture/signalled ]] || printf 'UNEXPECTED phase after signal\n' >> "$log"
                        echo false
                    elif [[ $scenario == run-foreign && -f $fixture/test-done ]]; then cat "$fixture/foreign.json"
                    elif [[ $# == 3 ]]; then cat "$fixture/inspection.json"
                    else cat "$fixture/container.json"; fi;;
                logs) printf 'database diagnostic %s %s\n' "$token" "$password";;
                rm)
                    if [[ $signal_point == repeated ]]; then fixture_signal; fi
                    [[ $scenario != run-cleanup-failure && $signal_point != cleanup-failure ]] || return 1
                    rm -f "$fixture/owned-container";;
                *) return 97;;
            esac
        }
        m6_cgroup() { cg="$fixture/cgroup"; mkdir -p "$cg"; printf 'populated 0\n' > "$cg/cgroup.events"; printf 'oom 0\noom_kill 0\n' > "$cg/memory.events"; printf 'max 0\n' > "$cg/pids.events"; }
        m6_php_cgroup() { php_cg=$cg; : > "$php_cg/cgroup.procs"; [[ $scenario != run-supervisor-limit ]] || m6_fail php_limits_not_effective; }
        m6_system() {
            case "$*" in
                *'-p LoadState -p ActiveState -p Description -p ControlGroup')
                    if [[ ( $scenario == run-unload || $scenario == run-timeout || $scenario == run-headroom || $scenario == run-interrupt ) && -f $fixture/stopped ]]; then printf 'LoadState=not-found\nActiveState=inactive\nDescription=%s\nControlGroup=\n' "$unit"; return 1; fi
                    printf 'LoadState=loaded\nActiveState=%s\nDescription=M6B validation %s\nControlGroup=/user.slice/user-%s.slice/app.slice/%s\n' "$([[ -f $fixture/stopped ]] && echo inactive || echo active)" "$token" "$uid" "$unit";;
                *LoadState*) [[ -f $fixture/unit ]] && echo loaded || echo not-found;;
                *Description*) [[ ! -f $fixture/unit ]] || echo "M6B validation $token";;
                *MemorySwapMax*) echo 0;; *MemoryMax*) echo 1073741824;; *TasksMax*) echo 32;; *KillMode*) echo control-group;;
                *Result*)
                    [[ ! -f $fixture/signalled ]] || printf 'UNEXPECTED phase after signal\n' >> "$log"
                    if [[ $signal_point == status ]]; then fixture_signal; fi
                    case $scenario in run-timeout) echo timeout;; run-resource) echo oom-kill;; *) echo success;; esac;;
                stop*)
                    printf 'stop-unit\n' >> "$log"
                    if [[ $signal_point == cleanup || $signal_point == repeated ]]; then fixture_signal; fi
                    touch "$fixture/stopped";;
                *ActiveState*) [[ -f $fixture/stopped ]] && echo inactive || echo active;;
                *) return 97;;
            esac
        }
        systemctl() { echo 'systemd fixture'; }
        sha256sum() {
            if [[ $signal_point == evidence && ! -f $fixture/signalled ]]; then fixture_signal; fi
            if [[ $scenario == run-publish-checksum-failure || $scenario == run-publish-checksum-permanent || $signal_point == boundary-publish-failure ]]; then
                if [[ ! -f $fixture/publication-failed || $scenario == run-publish-checksum-permanent ]]; then touch "$fixture/publication-failed"; return 1; fi
            fi
            if [[ $scenario == run-publish-timeout && ! -f $fixture/publication-failed ]]; then
                touch "$fixture/publication-failed"
                command sleep 30 & local sleeper=$!
                echo "$BASHPID $sleeper" > "$fixture/publisher.pids"
                builtin wait "$sleeper"
            fi
            command sha256sum "$@"
        }
        # Git Bash cannot provide Linux FIFOs/systemd. Model the stream with a gated private file.
        mkfifo() { touch "${@: -1}"; }
        eval "$(declare -f m6_redact | sed '1s/m6_redact/m6_fixture_redact/')"
        m6_redact() {
            if [[ ! -f $fixture/collector.pid ]]; then
                trap 'rm -f "$fixture/owned-collector"' EXIT
                echo "$BASHPID" > "$fixture/collector.pid"
                touch "$fixture/owned-collector"
            fi
            if [[ ! -f $fixture/test-done ]]; then for i in {1..100}; do [[ ! -f $fixture/test-done ]] || break; sleep 0.05; done; fi
            m6_fixture_redact
        }
        timeout() { shift 2; "$@"; }
        systemd-run() {
            trap 'rm -f "$fixture/owned-supervisor"' EXIT
            echo "$BASHPID" > "$fixture/supervisor.pid"
            touch "$fixture/owned-supervisor"
            touch "$fixture/unit"
            for i in {1..100}; do [[ ! -f $scratch/go && ! -f $fixture/stopped ]] || break; sleep 0.05; done
            case $signal_point in
                active|sleep-race|repeated|cleanup-failure|accepted-published)
                    touch "$fixture/active"
                    fixture_await test -f "$fixture/release";;
            esac
            printf 'synthetic result %s %s\n' "$password" "$token"
            touch "$fixture/test-done"
            if [[ -n $signal_point ]]; then
                touch "$fixture/completing"
                [[ $signal_point != before ]] || fixture_await test -f "$fixture/release"
            fi
            if [[ $scenario == run-interrupt ]]; then builtin kill -TERM "$fixture_main_pid"; return 143; fi
            case $scenario in run-test-failure|run-signal-*-published-failure) return 17;; run-timeout) return 124;; run-resource) return 137;; *) return 0;; esac
        }
        # The fake supervisor is one shell, not a GNU timeout process group.
        kill() {
            if [[ $* == *--* ]]; then builtin kill "$1" "${3#-}" 2>/dev/null; return; fi
            if [[ $1 == -0 && ${2:-} == "${supervisor:-}" && ${test_exit:-} == NO_COMPLETED_RESULT && ! -f $fixture/signalled ]]; then
                case $signal_point in
                    active|before|repeated|cleanup-failure|accepted-published)
                        if [[ $signal_point == before ]]; then fixture_await test -f "$fixture/completing"
                        else fixture_await test -f "$fixture/active"; fi
                        fixture_signal
                        [[ $signal_point != before ]] || touch "$fixture/release";;
                    after)
                        fixture_await test -f "$fixture/completing"
                        fixture_exited() { ! builtin kill -0 "$supervisor" 2>/dev/null; }
                        fixture_await fixture_exited
                        fixture_signal;;
                esac
            fi
            builtin kill "$@"
        }
        wait() {
            if [[ ${1:-} == "${supervisor:-}" && $signal_point == wait && ! -f $fixture/signalled ]]; then
                fixture_signal
                # Model Bash wait interrupted before it delivers the cached child status.
                [[ $signal_name != TERM ]] && return 130 || return 143
            fi
            if [[ ${1:-} == "${collector:-}" && $signal_point == collector && ! -f $fixture/signalled ]]; then fixture_signal; fi
            local code=0
            builtin wait "$@" || code=$?
            if ! builtin kill -0 "$1" 2>/dev/null; then
                [[ $1 != "${supervisor:-}" ]] || rm -f "$fixture/owned-supervisor"
                [[ $1 != "${collector:-}" ]] || rm -f "$fixture/owned-collector"
            fi
            return "$code"
        }
        sleep() {
            if [[ $BASHPID == "$fixture_main_pid" && $signal_point == sleep-race && ! -f $fixture/signalled && $1 == 1 ]]; then fixture_signal; fi
            command sleep "$@"
        }
        fixture_main_pid=$$
        printf() {
            if [[ ( $signal_point == published* || $signal_point == accepted-published ) && $1 == 'Private evidence: %s\n' ]]; then fixture_signal; fi
            if [[ $signal_point == report && $1 == '%s\n' && ${2:-} == *'Publication:'* && ! -f $fixture/signalled ]]; then fixture_signal; fi
            if [[ $scenario == run-publish-report-failure && $1 == '%s\n' && ${2:-} == *'Publication:'* && ! -f $fixture/publication-failed ]]; then touch "$fixture/publication-failed"; return 1; fi
            builtin printf "$@"
        }
        trap() {
            if [[ $# == 3 && $1 == '' && $2 == INT && $3 == TERM ]]; then
                if [[ $signal_point == boundary* ]]; then fixture_signal; fi
                printf 'finalization-boundary\n' >> "$log"
            fi
            builtin trap "$@"
        }
        eval "$(declare -f m6_bounded_publish | sed '1s/m6_bounded_publish/m6_fixture_publish/')"
        m6_bounded_publish() {
            local code=0
            printf 'publish-attempt\n' >> "$log"
            export -f printf sha256sum fixture_signal
            export signal_point signal_name fixture fixture_main_pid log scenario
            m6_fixture_publish "$@" || code=$?
            touch "$fixture/evidence-exit-signal-ready"
            return "$code"
        }
        exit() {
            if [[ $signal_point == exit && -f $fixture/evidence-exit-signal-ready ]]; then fixture_signal; fi
            builtin exit "$@"
        }
        if [[ $scenario == run-headroom ]]; then m6_budget() { return 1; }; fi
        if [[ $scenario == run-mount-loss ]]; then volume_ready=1; m6_volume_ok() { [[ ! -f $fixture/test-done ]]; }; fi
        m6_cli_config
        m6_run;;
    check-only)
        m6_preflight() {
            stat() { if [[ $2 == '%u:%a' ]]; then echo "$uid:700"; else command stat "$@"; fi; }
            m6_cli_config; printf 'prerequisites\n' >> "$log"
        }
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
