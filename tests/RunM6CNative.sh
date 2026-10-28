#!/usr/bin/env bash
# Explicit native execution only. Ordinary *Test.php discovery never invokes this entry.
set +x
set -Eeuo pipefail
export LC_ALL=C
umask 077

m6n_fail() { printf 'M6C native prerequisite/run failure: %s\n' "$1" >&2; exit "${2:-2}"; }
m6n_git() { timeout --kill-after=2s 15s env -i PATH=/usr/bin:/bin HOME=/nonexistent GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null LC_ALL=C git --no-optional-locks -c core.fsmonitor=false -c core.hooksPath=/dev/null -C "$repo" "$@"; }
m6n_client() { env -i PATH=/usr/bin:/bin HOME=/nonexistent LC_ALL=C docker --config "$client" --host unix:///run/user/1000/docker.sock "$@"; }
m6n_docker() {
    local seconds=15
    if [[ -n ${capture_deadline:-} ]]; then
        (( capture_deadline-SECONDS>2 )) || return 124
        (( capture_deadline-SECONDS-2>=seconds )) || seconds=$((capture_deadline-SECONDS-2))
    fi
    if [[ -n ${cleanup_deadline:-} ]]; then
        # Leave eight seconds for terminal publication/failure evidence, plus kill grace.
        (( cleanup_deadline-SECONDS>10 )) || return 124
        (( cleanup_deadline-SECONDS-10>=seconds )) || seconds=$((cleanup_deadline-SECONDS-10))
    fi
    timeout --kill-after=2s "${seconds}s" env -i PATH=/usr/bin:/bin HOME=/nonexistent LC_ALL=C docker --config "$client" --host unix:///run/user/1000/docker.sock "$@"
}
m6n_cleanup_command() {
    local seconds=$((cleanup_deadline-SECONDS-9))
    (( seconds>0 )) || return 124
    (( seconds<=15 )) || seconds=15
    timeout --kill-after=1s "${seconds}s" "$@"
}
m6n_guard() {
    if [[ -n ${capture_deadline:-} ]]; then
        local seconds=$((capture_deadline-SECONDS-1))
        (( seconds>0 )) || return 124
        timeout --kill-after=1s "${seconds}s" "$php" -n -d memory_limit=128M "$repo/tests/support/WebsitePlatformM6CNativeContract.php" "$@"
    elif [[ -n ${cleanup_deadline:-} ]]; then
        m6n_cleanup_command "$php" -n -d memory_limit=128M "$repo/tests/support/WebsitePlatformM6CNativeContract.php" "$@"
    else "$php" -n -d memory_limit=128M "$repo/tests/support/WebsitePlatformM6CNativeContract.php" "$@"; fi
}
m6n_private() { [[ -d $1 && ! -L $1 && $(realpath "$1") == "$1" && $(stat -c '%u:%a' "$1") == 1000:700 ]]; }
m6n_checkpoint() {
    # EXIT owns the interruption result; do not relabel a successful operation as a primary failure.
    [[ -z ${interrupted:-} ]] || exit 0
    (( SECONDS-started < 1200 )) || m6n_fail overall_deadline 124
}
m6n_volume() {
    [[ $(realpath /mnt/ubo_stage_testdata) == /mnt/ubo_stage_testdata \
        && $(findmnt -rn --mountpoint /mnt/ubo_stage_testdata -o TARGET) == /mnt/ubo_stage_testdata \
        && $(findmnt -rn --mountpoint /mnt/ubo_stage_testdata -o UUID) == 7d255792-0283-4773-b7a1-20596ed0d8fa \
        && $(stat -c %d /mnt/ubo_stage_testdata) != "$(stat -c %d /)" ]] || return 1
    local mount_id path
    mount_id=$(findmnt -rn --mountpoint /mnt/ubo_stage_testdata -o ID)
    [[ $mount_id =~ ^[0-9]+$ && ( -z ${volume_id:-} || $volume_id == "$mount_id" ) ]] || return 1
    for path in "$base" "$base/checkouts" "$base/tmp" "$base/evidence"; do
        m6n_private "$path" && [[ $(findmnt -rn --target "$path" -o TARGET) == /mnt/ubo_stage_testdata ]] || return 1
    done
    [[ $(realpath "$base/docker") == "$base/docker" && $(findmnt -rn --target "$base/docker" -o TARGET) == /mnt/ubo_stage_testdata \
        && $(stat -c '%u:%G:%a' "$base/docker") =~ ^1000:codex-validation:(700|710)$ ]] || return 1
    volume_id=$mount_id
}
m6n_memory() { local k v unit; while read -r k v unit; do [[ $k != MemAvailable: ]] || { printf '%s\n' "$((v*1024))"; return; }; done < /proc/meminfo; return 1; }
m6n_free() { df -B1 --output=avail "$1" | tail -n1 | tr -d ' '; }
m6n_export_source() {
    # Exact commit + current trees/blobs only. Do not export deleted historical data.
    { printf '%s\n' "$sha"; m6n_git rev-parse "$sha^{tree}"; m6n_git ls-tree -rt --format='%(objectname)' "$sha"; } |
        sort -u | m6n_git pack-objects --stdout > "$1"
}
m6n_capacity() {
    local mem root_free volume_free initial=${1:-0}
    mem=$(m6n_memory); root_free=$(m6n_free /); volume_free=$(m6n_free "$base")
    [[ $mem =~ ^[0-9]+$ && $root_free =~ ^[0-9]+$ && $volume_free =~ ^[0-9]+$ ]] || return 1
    # Count the shared Docker/evidence filesystem once. 1.5GiB DB + 1GiB runtime,
    # 256MiB host-controller allowance, and 1.5GiB staging reserve.
    if (( initial )); then
        printf 'Admission: CPUs=%s MemAvailable=%s root-free=%s volume-free=%s (not additive)\n' "$(nproc)" "$mem" "$root_free" "$volume_free"
    fi
    printf '{"cpus":%s,"memory":%s,"root_free":%s,"volume_free":%s}\n' "$(nproc)" "$mem" "$root_free" "$volume_free" | m6n_guard resources "$initial" >/dev/null
}
m6n_cached_images() {
    db_image=$(m6n_docker image inspect "docker.io/library/mysql@$mysql_digest" | m6n_guard mysql-image "$mysql_digest") || m6n_fail prepare_cached_mysql_image
    m6n_docker image inspect "$runtime_image" | m6n_guard runtime-image "$runtime_image" "$sha" "$php_base" >/dev/null || m6n_fail prepare_cached_runtime_image
    image_bytes=$(m6n_docker image inspect "$db_image" "$runtime_image" --format '{{.Size}}' | awk '{n+=$1}END{printf "%.0f",n}')
    [[ $image_bytes =~ ^[0-9]+$ && $image_bytes -le 5368709120 ]] || m6n_fail combined_image_budget
}
m6n_preflight() {
    local tool key actual common controllers daemon_pid file
    for key in $(compgen -e); do
        case $key in GIT_*|DOCKER_*|APP_ENV|DB_*|DATABASE_URL|MYSQL_*|M6C_*|AWS_*|PHPRC|PHP_INI_SCAN_DIR|BASH_ENV|ENV|LD_PRELOAD|LD_LIBRARY_PATH) m6n_fail inherited_configuration;; esac
    done
    for tool in git docker timeout realpath stat sha256sum df du flock mktemp systemctl findmnt find nproc hostname uname id env sed head tail tr date sleep cat rm rmdir mv mkdir chmod awk cut sort; do command -v "$tool" >/dev/null || m6n_fail missing_tool; done
    [[ $(uname -s) == Linux && $(hostname -s) == ubo-stage-app && $(id -un) == codex-validation && $(id -u) == 1000 ]] || m6n_fail fixed_staging_identity
    [[ $php == /* && -x $php && $(realpath "$php") == "$php" ]] || m6n_fail explicit_host_php
    m6n_private /run/user/1000 || m6n_fail runtime_directory
    [[ $(realpath /run/user/1000/docker.sock) == /run/user/1000/docker.sock && $(stat -c '%F:%u' /run/user/1000/docker.sock) == socket:1000 ]] || m6n_fail local_rootless_socket
    # Pre-existing, credential-free client directory: check-only creates no files/resources.
    m6n_private "$client" && [[ $(stat -c '%u:%a:%s' "$client/config.json") == 1000:600:3 \
        && ! -L $client/config.json && $(cat "$client/config.json") == '{}' \
        && $(find "$client" -mindepth 1 -maxdepth 1 -printf '%f\n') == config.json ]] || m6n_fail prepare_empty_client_config
    m6n_volume || m6n_fail volume_identity
    m6n_capacity 1 || m6n_fail resource_gap
    [[ $repo == "$base/checkouts/"* && $repo != *'/www/'* && $repo != *'/public_html/'* && -d $repo/.git && ! -L $repo/.git \
        && $(m6n_git rev-parse --show-toplevel) == "$repo" && $(m6n_git rev-parse --absolute-git-dir) == "$repo/.git" \
        && ! -e $repo/.git/commondir && ! -e $repo/.git/objects/info/alternates ]] || m6n_fail independent_checkout
    [[ $(m6n_git rev-parse HEAD) == "$sha" && -z $(m6n_git status --porcelain=v1 --untracked-files=all) \
        && -z $(m6n_git ls-files --others) ]] || m6n_fail exact_clean_checkout
    [[ -z $(m6n_git ls-tree -r "$sha" | sed -n '/^120000 /p;/^160000 /p') ]] || m6n_fail unsupported_tracked_link
    "$php" -n -r 'echo json_encode(["os"=>$argv[1],"host"=>$argv[2],"user"=>$argv[3],"uid"=>(int)$argv[4],"socket"=>"/run/user/1000/docker.sock","socket_uid"=>1000,"socket_type"=>"socket","uuid"=>$argv[5],"mount"=>"/mnt/ubo_stage_testdata","volume_device"=>$argv[6],"root_device"=>$argv[7],"repo"=>$argv[8],"clean"=>true,"sha"=>$argv[9],"actual_sha"=>$argv[10]],JSON_THROW_ON_ERROR);' \
        "$(uname -s)" "$(hostname -s)" "$(id -un)" "$(id -u)" "$(findmnt -rn --mountpoint /mnt/ubo_stage_testdata -o UUID)" \
        "$(stat -c %d "$base")" "$(stat -c %d /)" "$repo" "$sha" "$(m6n_git rev-parse HEAD)" | m6n_guard target >/dev/null || m6n_fail target_contract
    "$php" -n -d memory_limit=128M -r 'require $argv[1]."/tests/support/WebsitePlatformM6BScope.php";exit(m6bCanonicalMigrationsValid($argv[1])?0:2);' "$repo" || m6n_fail canonical_migrations
    [[ $(stat -f -c %T /sys/fs/cgroup) == cgroup2fs ]] || m6n_fail cgroup_v2
    controllers=" $(cat /sys/fs/cgroup/user.slice/user-1000.slice/user@1000.service/cgroup.controllers) "
    for key in cpu memory pids; do [[ $controllers == *" $key "* ]] || m6n_fail controller_delegation; done
    [[ $(timeout 10s systemctl --user show docker.service -p ActiveState --value) == active \
        && $(timeout 10s systemctl --user show docker.service -p Delegate --value) == yes ]] || m6n_fail daemon_already_available_required
    daemon_pid=$(timeout 10s systemctl --user show docker.service -p MainPID --value)
    [[ $daemon_pid =~ ^[1-9][0-9]*$ && $(stat -c %u "/proc/$daemon_pid") == 1000 ]] || m6n_fail daemon_owner
    actual=$(m6n_docker info --format '{{json .}}' | m6n_guard engine) || m6n_fail rootless_engine
    [[ $actual == "$base/docker" ]] || m6n_fail daemon_volume_root
    m6n_cached_images
    m6n_checkpoint
}
m6n_limits() {
    local id=$1 memory=$2 pids=$3 group quota period
    group=$(timeout 10s systemctl --user show "docker-$id.scope" -p ControlGroup --value)
    [[ $group == /user.slice/user-1000.slice/* && $group != *..* && $(realpath "/sys/fs/cgroup$group") == "/sys/fs/cgroup$group" ]] || return 1
    read -r quota period < "/sys/fs/cgroup$group/cpu.max"
    [[ $quota =~ ^[0-9]+$ && $period =~ ^[1-9][0-9]*$ && $quota -eq $period \
        && $(cat "/sys/fs/cgroup$group/memory.max") == "$memory" && $(cat "/sys/fs/cgroup$group/memory.swap.max") == 0 \
        && $(cat "/sys/fs/cgroup$group/pids.max") == "$pids" ]] || return 1
    printf '%s cpu=%s/%s memory=%s swap=0 pids=%s\n' "$id" "$quota" "$period" "$memory" "$pids" >> "$evidence/effective-limits.txt"
    local key value
    while read -r key value; do
        printf '%s memory.%s=%s\n' "$id" "$key" "$value" >> "$evidence/cgroup-events.txt"
        [[ $key != oom && $key != oom_kill ]] || [[ $value == 0 ]] || return 1
    done < "/sys/fs/cgroup$group/memory.events"
    while read -r key value; do
        printf '%s pids.%s=%s\n' "$id" "$key" "$value" >> "$evidence/cgroup-events.txt"
        [[ $value == 0 ]] || return 1
    done < "/sys/fs/cgroup$group/pids.events"
}
m6n_inspect() {
    m6n_docker inspect "$db_id" "$db_image" | m6n_guard container db "$token" "$db_id" "$db_image" "$network_id" "$input" > "$evidence/db-identity.json" || return 1
    m6n_docker inspect "$runtime_id" "$runtime_image" | m6n_guard container runtime "$token" "$runtime_id" "$runtime_image" "$network_id" "$input" > "$evidence/runtime-identity.json" || return 1
    m6n_docker network inspect "$network_id" | m6n_guard network "$token" "$network_id" "$db_id,$runtime_id" >/dev/null || return 1
    [[ $(m6n_docker exec "$db_id" awk '/^Uid:/ {print $2 ":" $3 ":" $4 ":" $5}' /proc/1/status) == 999:999:999:999 \
        && $(m6n_docker exec "$runtime_id" awk '/^Uid:/ {print $2 ":" $3 ":" $4 ":" $5}' /proc/1/status) == 0:0:0:0 ]] || return 1
    m6n_limits "$db_id" 1610612736 128 && m6n_limits "$runtime_id" 1073741824 48
}
m6n_owned_remove() {
    local role=$1 id=$2 image=$3 name="ubo-m6c-$1-$token" data found
    # Names were checked absent before creation. Recovery from a lost create reply
    # still requires exact name, random run identity, image, role AND network binding.
    if [[ -z $id ]]; then
        id=$(m6n_docker inspect "$name" --format '{{.Id}}' 2>/dev/null) || {
            found=$(m6n_docker container ls -aq --no-trunc --filter "name=^/$name$") || return 1
            [[ -z $found ]] || return 1
            return 0
        }
    fi
    [[ $id =~ ^[a-f0-9]{64}$ ]] || return 1
    m6n_docker inspect "$id" | m6n_guard owner "$role" "$token" "$id" "$image" >/dev/null || return 1
    [[ $(m6n_docker inspect "$id" --format '{{.HostConfig.NetworkMode}}') == "$network_id" ]] || return 1
    m6n_docker stop --time 5 "$id" >/dev/null || return 1
    m6n_docker inspect "$id" --format '{{json .State}}' > "$evidence/$role-exit-state.json" || return 1
    [[ $(m6n_docker inspect "$id" --format '{{.State.Running}}') == false ]] || return 1
    m6n_docker rm "$id" >/dev/null || return 1
    found=$(m6n_docker container ls -aq --no-trunc --filter "id=$id") || return 1
    [[ -z $found ]]
}
m6n_signal() { interrupted=${interrupted:-$1}; }
m6n_close_signals() { trap '' INT TERM; }
m6n_outcome() {
    final=$primary
    if [[ $final == 0 ]]; then final=${interrupted:-0}; fi
    [[ $final != 0 || ( $cleanup == PASS && $publication == PASS ) ]] || final=2
}
m6n_status() {
    printf 'source=%s\nprimary_exit=%s\ninterruption_exit=%s\ncleanup=%s\npublication=%s\nfinal_exit=%s\n' "$@"
}
m6n_evidence_job() {
    # Executed in a bounded child, with values passed as arguments, never shell code.
    local mode=$1 directory=$2 digest file; shift 2
    cd "$directory" || return 1
    if [[ $mode == failure ]]; then
        rm -f -- PUBLICATION || return 1
        m6n_status "$@" > status.failed && mv -- status.failed status.txt
        return $?
    fi
    if [[ $mode == prepare ]]; then
        m6n_status "$@" > status.provisional || return 1
        # Hash substantive evidence while INT/TERM are still accepted by the parent.
        # Status is small and regenerated once after the terminal boundary.
        local -a files=()
        for file in ./*.txt ./*.json; do
            [[ $file == ./status.txt || ! -f $file ]] || files+=("$file")
        done
        sha256sum -- "${files[@]}" > payload.pending || return 1
        sha256sum -- status.provisional > provisional.pending || return 1
        return 0
    fi
    [[ $mode == commit ]] || return 1
    m6n_status "$@" > status.pending && mv -- status.pending status.txt || return 1
    cat payload.pending > SHA256SUMS.pending || return 1
    sha256sum -- ./status.txt >> SHA256SUMS.pending || return 1
    digest=$(sha256sum -- SHA256SUMS.pending) || return 1
    digest=${digest%% *}; [[ $digest =~ ^[a-f0-9]{64}$ ]] || return 1
    { printf 'manifest_sha256=%s\n' "$digest" && m6n_status "$@"; } > PUBLICATION.pending || return 1
    mv -- SHA256SUMS.pending SHA256SUMS || return 1
    # Receipt is last. Nothing fallible follows its atomic rename in this child.
    mv -- PUBLICATION.pending PUBLICATION
}
m6n_evidence_run() {
    local mode=$1 cap reserve seconds
    case $mode in prepare) cap=15;reserve=8;;commit) cap=4;reserve=3;;failure) cap=2;reserve=0;;*)return 1;;esac
    seconds=$((cleanup_deadline-SECONDS-reserve-1))
    (( seconds>0 )) || return 124
    (( seconds<=cap )) || seconds=$cap
    export -f m6n_evidence_job m6n_status
    timeout --kill-after=1s "${seconds}s" bash -c 'm6n_evidence_job "$@"' _ "$@"
}
m6n_finish() {
    local primary=$? cleanup=PASS publication=PASS final=0 found
    trap - EXIT
    set +e
    cleanup_deadline=$((SECONDS+90))
    # Capture the sanitized native report BEFORE removing the exact runtime container.
    if [[ -n ${runtime_id:-} && -n ${evidence:-} && ! -f $evidence/native.json ]]; then
        m6n_capture fallback || printf 'native-report-capture fallback=FAIL\n' >&2
    fi
    if [[ ${runtime_attempted:-0} == 1 ]]; then m6n_owned_remove runtime "${runtime_id:-}" "$runtime_image" || cleanup=FAIL; fi
    if [[ ${db_attempted:-0} == 1 ]]; then m6n_owned_remove db "${db_id:-}" "$db_image" || cleanup=FAIL; fi
    if [[ -n ${supervisor:-} ]]; then
        kill -TERM "$supervisor" 2>/dev/null
        local until=$((SECONDS+3))
        while kill -0 "$supervisor" 2>/dev/null && (( SECONDS<until )); do sleep 0.05; done
        if kill -0 "$supervisor" 2>/dev/null; then kill -KILL "$supervisor" 2>/dev/null; cleanup=FAIL; fi
        wait "$supervisor" 2>/dev/null
    fi
    if [[ ${network_attempted:-0} == 1 ]]; then
        [[ -n ${network_id:-} ]] || network_id=$(m6n_docker network inspect "ubo-m6c-$token" --format '{{.Id}}' 2>/dev/null)
        if m6n_docker network inspect "$network_id" | m6n_guard network "$token" "$network_id" '' >/dev/null; then
            m6n_docker network rm "$network_id" >/dev/null || cleanup=FAIL
            found=$(m6n_docker network ls -q --no-trunc --filter "id=$network_id")
            [[ $? == 0 && -z $found ]] || cleanup=FAIL
        else cleanup=FAIL; fi
    fi
    # Only this exact newly-created directory on the pinned filesystem is eligible.
    if [[ -n ${disk:-} ]]; then
        if [[ $disk == "$storage/tmp/m6c."* && ! -L $disk && $(m6n_cleanup_command stat -c '%d:%i:%u:%a' "$disk") == "$disk_identity" ]]; then m6n_cleanup_command rm -rf -- "$disk" || cleanup=FAIL
        else cleanup=FAIL; fi
    fi
    [[ -z ${lock_identity:-} ]] || { [[ $(m6n_cleanup_command stat -c '%d:%i:%u:%a' "$storage/tmp/m6c-native.lock") == "$lock_identity" ]] && m6n_cleanup_command rmdir -- "$storage/tmp/m6c-native.lock"; } || cleanup=FAIL
    m6n_outcome
    if [[ -n ${evidence:-} ]]; then
        m6n_evidence_run prepare "$evidence" "$sha" "$primary" "${interrupted:-0}" "$cleanup" "$publication" "$final" || publication=FAIL
    fi
    # Terminal boundary: all substantive cleanup and payload hashing have finished.
    # Freeze only for one <=5s commit and, on failure, <=3s diagnostic write, both
    # inside the original 90s deadline. No retry loop; repeated signals never reset it.
    m6n_close_signals
    m6n_outcome
    if [[ -n ${evidence:-} ]]; then
        if [[ $publication == PASS ]]; then
            m6n_evidence_run commit "$evidence" "$sha" "$primary" "${interrupted:-0}" "$cleanup" "$publication" "$final" || publication=FAIL
        fi
        if [[ $publication != PASS ]]; then
            m6n_outcome
            m6n_evidence_run failure "$evidence" "$sha" "$primary" "${interrupted:-0}" "$cleanup" "$publication" "$final" || :
            printf 'M6C evidence publication FAILED; primary exit=%s interruption=%s final=%s\n' "$primary" "${interrupted:-0}" "$final" >&2
        fi
        printf 'M6C native primary=%s interruption=%s cleanup=%s evidence-publication=%s final=%s; evidence=%s\n' "$primary" "${interrupted:-0}" "$cleanup" "$publication" "$final" "$evidence_path"
    fi
    unset MYSQL_ROOT_PASSWORD worker_password
    exit "$final"
}
m6n_start_containers() {
    [[ -z $(m6n_docker network ls -q --filter "name=^ubo-m6c-$token$") \
        && -z $(m6n_docker container ls -aq --filter "name=ubo-m6c-.*-$token") ]] || m6n_fail preexisting_run_name
    m6n_volume && m6n_checkpoint || m6n_fail volume_changed
    network_attempted=1
    network_id=$(m6n_docker network create --internal --driver bridge --label "ubo.m6c.owner=$token" --label ubo.m6c.contract=m6c-native-v1 "ubo-m6c-$token") || m6n_fail network_create
    [[ $network_id =~ ^[a-f0-9]{64}$ ]] || m6n_fail network_id
    m6n_docker network inspect "$network_id" | m6n_guard network "$token" "$network_id" '' >/dev/null || m6n_fail network_contract
    db_attempted=1
    db_id=$(m6n_docker create --pull=never --platform linux/amd64 --name "ubo-m6c-db-$token" --hostname "m6c-db-$token" \
        --label "ubo.m6c.owner=$token" --label ubo.m6c.role=db --label ubo.m6c.contract=m6c-native-v1 \
        --user 999:999 --network "$network_id" --ipc private --cgroupns private --read-only --no-healthcheck --cap-drop ALL --security-opt no-new-privileges=true \
        --cpus 1 --memory 1536m --memory-swap 1536m --pids-limit 128 \
        --tmpfs /var/lib/mysql:rw,nosuid,nodev,size=1073741824,uid=999,gid=999,mode=0700 \
        --tmpfs /var/run/mysqld:rw,nosuid,nodev,size=16777216,uid=999,gid=999,mode=0755 --tmpfs /tmp:rw,nosuid,nodev,noexec,size=16777216,mode=1777 \
        --log-driver local --log-opt max-size=1m --log-opt max-file=2 --log-opt compress=false \
        --env-file "$disk/input/mysql.env" "$db_image" --skip-log-bin) || m6n_fail database_create
    [[ $db_id =~ ^[a-f0-9]{64}$ ]] || m6n_fail database_id
    m6n_docker start "$db_id" >/dev/null || m6n_fail database_start
    m6n_volume && m6n_checkpoint || m6n_fail volume_changed
    runtime_attempted=1
    runtime_id=$(m6n_docker create --pull=never --platform linux/amd64 --name "ubo-m6c-runtime-$token" --hostname "m6c-runtime-$token" \
        --label "ubo.m6c.owner=$token" --label ubo.m6c.role=runtime --label ubo.m6c.contract=m6c-native-v1 \
        --user 0:0 --network "$network_id" --ipc private --cgroupns private --no-healthcheck --cap-drop ALL \
        --cap-add CHOWN --cap-add DAC_OVERRIDE --cap-add KILL --cap-add SETUID --cap-add SETGID --cap-add SETPCAP --security-opt no-new-privileges=true \
        --cpus 1 --memory 1g --memory-swap 1g --pids-limit 48 \
        --tmpfs /opt/ubo:rw,nosuid,nodev,size=268435456,mode=0755 \
        --tmpfs /var/lib/ubo-m6c:rw,nosuid,nodev,noexec,size=268435456,mode=0755 \
        --tmpfs /run/m6c:rw,nosuid,nodev,noexec,size=16777216,mode=0755 --tmpfs /tmp:rw,nosuid,nodev,noexec,size=16777216,mode=1777 \
        --mount "type=bind,src=$input,dst=/input,readonly,bind-propagation=rprivate" \
        --log-driver local --log-opt max-size=1m --log-opt max-file=2 --log-opt compress=false \
        --entrypoint /bin/sleep "$runtime_image" 1200) || m6n_fail runtime_create
    [[ $runtime_id =~ ^[a-f0-9]{64}$ ]] || m6n_fail runtime_id
    m6n_docker start "$runtime_id" >/dev/null || m6n_fail runtime_start
    m6n_inspect || m6n_fail inspection_mismatch
}
m6n_transport() {
    local seconds=$((capture_deadline-SECONDS-1))
    (( seconds>0 )) || return 124
    timeout --kill-after=1s "${seconds}s" "$php" -n -d memory_limit=128M "$repo/tests/support/WebsitePlatformM6CNativeTransport.php" "$@"
}
m6n_capture() {
    local attempt=$1 capture_deadline=$((SECONDS+15)) ceiling identity=FAIL reader=UNREACHED consumer=UNREACHED verification=UNREACHED result=FAIL
    local -a codes transport_profile=()
    [[ ${profile_version:-m6c-native-v1} != m6c-native-supplement-v1 ]] || transport_profile=("$profile_version")
    if [[ -n ${cleanup_deadline:-} ]];then ceiling=$((cleanup_deadline-8));else ceiling=$((started+1200));fi
    (( capture_deadline<=ceiling )) || capture_deadline=$ceiling
    if [[ ${runtime_id:-} =~ ^[a-f0-9]{64}$ ]] &&
        m6n_docker inspect "$runtime_id" | m6n_guard capture-owner "$token" "$runtime_id" "$runtime_image" "$network_id" >/dev/null; then
        identity=PASS
        # No TTY, archive, shell or application bootstrap. The consumer bounds the pipe
        # before any host write; PIPESTATUS retains both producer and consumer results.
        (ulimit -f 4; m6n_docker exec "$runtime_id" /usr/local/bin/php -n -d memory_limit=128M \
            /opt/ubo/tests/support/WebsitePlatformM6CNativeTransport.php read "$sha" "$token" "${transport_profile[@]}") \
            2> "$evidence/capture-$attempt-reader.txt" |
            m6n_transport receive "$evidence" "$attempt" 2> "$evidence/capture-$attempt-consumer.txt"
        codes=("${PIPESTATUS[@]}");reader=${codes[0]};consumer=${codes[1]}
        if [[ $reader == 0 && $consumer == 0 ]]; then
            verification=0
            m6n_transport verify "$evidence" "$attempt" "$sha" "$token" "${transport_profile[@]}" > "$evidence/capture-$attempt.json" \
                2> "$evidence/capture-$attempt-verification.txt" || verification=$?
            [[ $verification != 0 ]] || result=PASS
        fi
    fi
    printf 'attempt=%s\nidentity=%s\nreader_docker_exit=%s\nconsumer_exit=%s\nverification_exit=%s\ncapture=%s\n' \
        "$attempt" "$identity" "$reader" "$consumer" "$verification" "$result" > "$evidence/capture-$attempt.txt" || return 1
    printf 'native-report-capture %s=%s\n' "$attempt" "$result" >> "$evidence/report.txt" || return 1
    [[ $result == PASS ]]
}
m6n_collect_result() {
    local code=$1 capture=PASS
    printf 'docker-exec exit=%s\n' "$code" >> "$evidence/report.txt"
    m6n_capture normal || capture=FAIL
    printf 'native-report-capture=%s\n' "$capture" >> "$evidence/report.txt"
    [[ $code == 0 ]] || exit "$code"
    [[ $capture == PASS ]] || m6n_fail missing_native_evidence
    if [[ ${profile_version:-m6c-native-v1} == m6c-native-supplement-v1 ]]; then
        local seconds=$((started+1200-SECONDS))
        ((seconds>0)) || m6n_fail overall_deadline 124
        timeout --kill-after=1s "${seconds}s" "$php" -n -d memory_limit=128M "$repo/tests/support/WebsitePlatformM6CSupplementContract.php" evidence "$sha" "$token" < "$evidence/native.json" >/dev/null || m6n_fail invalid_supplemental_evidence
    else
        m6n_guard evidence "$sha" "$token" < "$evidence/native.json" >/dev/null || m6n_fail invalid_native_evidence
    fi
}
m6n_run() {
    # Hold the verified filesystem open. All host output/cleanup uses this FD path,
    # so mount loss cannot redirect writes into an underlying boot-disk directory.
    exec {base_fd}<"$base"; storage="/proc/$$/fd/$base_fd/."
    [[ $(stat -c '%d:%i' "$storage") == "$(stat -c '%d:%i' "$base")" ]] && m6n_volume || m6n_fail volume_changed
    mkdir "$storage/tmp/m6c-native.lock" || m6n_fail another_native_run_or_retained_lock
    lock_identity=$(stat -c '%d:%i:%u:%a' "$storage/tmp/m6c-native.lock")
    token=$("$php" -n -r 'echo bin2hex(random_bytes(16));')
    disk=$(mktemp -d "$storage/tmp/m6c.XXXXXXXX");disk_identity=$(stat -c '%d:%i:%u:%a' "$disk")
    input="$base/tmp/${disk##*/}/input";mkdir "$disk/input"
    evidence="$storage/evidence/m6c-native-$(date -u +%Y%m%dT%H%M%SZ)-$token";mkdir "$evidence"
    evidence_path="$base/evidence/${evidence##*/}"
    printf 'M6C native v1; source=%s; deadline=1200s plus bounded teardown\nMySQL=1CPU/1536MiB/128PIDs runtime=1CPU/1024MiB/48PIDs additional-swap=0\nRuntime controller+at most 2 PHP children, each128MiB; checkout/artifact/run/tmp tmpfs all charged to runtime memory.\nHost controller allowance=256MiB; staging reserve=1536MiB; root floor=1GiB volume floor=4GiB; 6GiB monitored aggregate disk budget, not a quota.\n' "$sha" > "$evidence/report.txt"
    printf 'Selected acceptance profile=%s\n' "$profile_version" >> "$evidence/report.txt"
    (ulimit -v 262144; m6n_export_source "$disk/input/source.pack") || m6n_fail source_pack
    [[ $(stat -c %s "$disk/input/source.pack") -le 67108864 ]] || m6n_fail source_pack_budget
    MYSQL_ROOT_PASSWORD=$("$php" -n -r 'echo bin2hex(random_bytes(32));');export MYSQL_ROOT_PASSWORD
    worker_password=$("$php" -n -r 'echo bin2hex(random_bytes(32));')
    printf 'MYSQL_ROOT_PASSWORD=%s\nMYSQL_ROOT_HOST=%%\n' "$MYSQL_ROOT_PASSWORD" > "$disk/input/mysql.env"
    printf '{"root":"%s","worker":"%s"}\n' "$MYSQL_ROOT_PASSWORD" "$worker_password" > "$disk/input/credentials.json"
    chmod 0400 "$disk/input/credentials.json" "$disk/input/mysql.env"
    m6n_start_containers
    local uuid='' end=$((SECONDS+60)) db_ip bundle_hash remaining code
    while (( SECONDS<end )); do
        m6n_checkpoint
        uuid=$(m6n_docker exec --user 999:999 "$db_id" cat /var/lib/mysql/auto.cnf 2>/dev/null | sed -n 's/^server-uuid=//p') || uuid=''
        [[ $uuid =~ ^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$ ]] && break
        sleep 0.2
    done
    [[ $uuid =~ ^[a-f0-9-]{36}$ ]] || m6n_fail database_server_identity
    db_ip=$("$php" -n -r '$x=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);echo $x["ip"];' "$evidence/db-identity.json")
    bundle_hash=$(sha256sum "$disk/input/source.pack");bundle_hash=${bundle_hash%% *}
    "$php" -n -r 'echo json_encode(["version"=>"m6c-native-v1","sha"=>$argv[1],"token"=>$argv[2],"db_id"=>$argv[3],"runtime_id"=>$argv[4],"network_id"=>$argv[5],"db_ip"=>$argv[6],"server_uuid"=>$argv[7],"bundle_hash"=>$argv[8],"worker_uid"=>2000,"profile"=>$argv[9]],JSON_THROW_ON_ERROR);' \
        "$sha" "$token" "$db_id" "$runtime_id" "$network_id" "$db_ip" "$uuid" "$bundle_hash" "$profile_version" > "$disk/input/identity.json"
    chmod 0444 "$disk/input/identity.json" "$disk/input/source.pack"
    m6n_volume && m6n_inspect && m6n_checkpoint || m6n_fail before_sql_isolation
    # Namespace-local shallow checkout of real commit identity; no history/config copy.
    m6n_docker exec --user 0:0 "$runtime_id" /bin/sh -eu -c 'umask 022; test "$(sha256sum /input/source.pack | cut -d" " -f1)" = "$2"; git init /opt/ubo; git -C /opt/ubo index-pack --stdin </input/source.pack; printf "%s\n" "$1" >/opt/ubo/.git/shallow; git -C /opt/ubo update-ref --no-deref HEAD "$1"; git -C /opt/ubo -c core.hooksPath=/dev/null checkout --detach --force "$1"; git -C /opt/ubo fsck --full; test "$(git -C /opt/ubo rev-parse HEAD)" = "$1"' _ "$sha" "$bundle_hash" > "$evidence/setup.txt" 2>&1 || m6n_fail source_setup
    remaining=$((1200-SECONDS+started)); ((remaining>0)) || m6n_fail overall_deadline 124
    # One runtime cgroup contains controller and every child, including faults and kills.
    (ulimit -f 1024; timeout --kill-after=3s "${remaining}s" env -i PATH=/usr/bin:/bin HOME=/nonexistent LC_ALL=C \
        docker --config "$client" --host unix:///run/user/1000/docker.sock exec --user 0:0 "$runtime_id" \
        /usr/local/bin/php -d memory_limit=128M -d display_errors=stderr -d zend.exception_ignore_args=1 \
        /opt/ubo/tests/WebsitePlatformM6CNative.php --container-run "$sha" "$token" "$profile_version") > "$evidence/child.stdout.txt" 2> "$evidence/child.stderr.txt" & supervisor=$!
    while kill -0 "$supervisor" 2>/dev/null; do
        m6n_checkpoint
        m6n_volume && m6n_capacity || m6n_fail runtime_resource_or_mount_loss
        [[ $(du -sb "$disk" "$evidence" | awk '{n+=$1}END{print n}') -le 671088640 ]] || m6n_fail host_output_budget
        local writable_bytes
        writable_bytes=$(m6n_docker inspect --size "$runtime_id" --format '{{.SizeRw}}')
        [[ $writable_bytes =~ ^[0-9]+$ && $writable_bytes -le 268435456 ]] || m6n_fail writable_layer_budget
        sleep 1
    done
    code=0;wait "$supervisor" || code=$?;supervisor=''
    m6n_collect_result "$code"
    m6n_volume && m6n_inspect && m6n_checkpoint || m6n_fail final_isolation
    exit 0
}
m6n_main() {
    if [[ $# == 1 && $1 == --help ]]; then
        printf '%s\n' 'Usage: bash tests/RunM6CNative.sh --check-only|--run [--profile core|supplement-v1] --expected-sha SHA --mysql-digest sha256:... --runtime-image sha256:... --php-base-digest sha256:... --php /absolute/php' \
            'Fixed target: ubo-stage-app / codex-validation UID1000, rootless local socket, verified test volume.' \
            'Check-only is read-only. Images and empty /run/user/1000/ubo-m6c-client/config.json must already exist.' \
            'Run creates disposable native resources only after explicit authorization. No image pulls/builds or daemon lifecycle commands.';return
    fi
    mode='';sha='';mysql_digest='';runtime_image='';php_base='';php='';profile=core;profile_set=0;profile_version=m6c-native-v1
    while (($#)); do
        case $1 in
            --check-only|--run) [[ -z $mode ]] || m6n_fail one_mode;mode=$1;shift;;
            --profile)
                [[ $# -ge 2 && $profile_set == 0 ]] || m6n_fail one_profile
                case $2 in core)profile_version=m6c-native-v1;;supplement-v1)profile_version=m6c-native-supplement-v1;;*)m6n_fail unknown_profile;;esac
                profile=$2;profile_set=1;shift 2;;
            --expected-sha|--mysql-digest|--runtime-image|--php-base-digest|--php)
                [[ $# -ge 2 ]] || m6n_fail argument_value
                case $1 in --expected-sha)sha=$2;;--mysql-digest)mysql_digest=$2;;--runtime-image)runtime_image=$2;;--php-base-digest)php_base=$2;;--php)php=$2;;esac;shift 2;;
            *)m6n_fail unknown_argument;;
        esac
    done
    [[ -n $mode && $sha =~ ^[a-f0-9]{40}$ && $mysql_digest =~ ^sha256:[a-f0-9]{64}$ \
        && $runtime_image =~ ^sha256:[a-f0-9]{64}$ && $php_base =~ ^sha256:[a-f0-9]{64}$ && -n $php ]] || m6n_fail explicit_identities
    repo=$(realpath "${BASH_SOURCE[0]%/*}/..");base=/mnt/ubo_stage_testdata/codex-validation;client=/run/user/1000/ubo-m6c-client
    started=$SECONDS;interrupted='';volume_id='';network_id='';db_id='';runtime_id='';evidence='';disk='';supervisor='';lock_identity=''
    network_attempted=0;db_attempted=0;runtime_attempted=0
    trap 'm6n_signal 130' INT
    trap 'm6n_signal 143' TERM
    trap m6n_finish EXIT
    m6n_preflight
    if [[ $mode == --check-only ]]; then printf 'Prerequisites accepted; no files, credentials, containers, networks, SQL or worker resources created. NATIVE EXECUTION NOT PERFORMED.\n';return;fi
    m6n_run
}
if [[ ${BASH_SOURCE[0]} == "$0" ]]; then m6n_main "$@"; fi
