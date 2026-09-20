#!/usr/bin/env bash
# Explicit, disposable Linux test launcher. Never source application configuration.
set +x
set -Eeuo pipefail
export LC_ALL=C
umask 077

m6_fail() { printf 'M6B prerequisite/run failure: %s\n' "$1" >&2; exit "${2:-2}"; }
m6_git() { git -C "$repo" "$@"; }
m6_docker() {
    timeout --kill-after=2s 15s env -u DOCKER_CONTEXT -u DOCKER_HOST -u DOCKER_CONFIG -u DOCKER_AUTH_CONFIG \
        -u HTTP_PROXY -u HTTPS_PROXY -u ALL_PROXY -u NO_PROXY -u http_proxy -u https_proxy -u all_proxy -u no_proxy \
        docker --config "$cli_config" --host "$endpoint" "$@"
}
m6_system() { timeout --kill-after=2s 20s systemctl --user "$@"; }
m6_guard() { "$php" -n -d memory_limit=256M "$repo/tests/support/WebsitePlatformM6BLinuxGuard.php" "$@"; }
m6_private() { [[ $(stat -c '%u:%a' "$1") == "$uid:700" && ! -L $1 ]] || m6_fail private_directory_required; }
m6_init() {
    container_attempted=0; unit_attempted=0; collector=''; supervisor=''; container_id=''; evidence=''; scratch=''; disk_scratch=''; php_cg=''
    cli_config=''; cli_identity=''; failure=prerequisite; test_exit=NOT_EXECUTED; volume_uuid=''; volume_mount_id=''; volume_ready=0; evidence_safe=1; base_fd=''; evidence_path=''
    trap m6_finish EXIT
    trap 'failure=interrupted; exit 130' INT
    trap 'failure=interrupted; exit 143' TERM
}
m6_cli_config() {
    # Registered trap already owns the lifecycle, including partial creation and check-only.
    cli_config=$(mktemp -d "$runtime_dir/ubo-m6b-cli.XXXXXXXX")
    cli_identity=$(stat -c '%d:%i' "$cli_config")
    printf '{}\n' > "$cli_config/config.json"
}
m6_remove_cli_config() {
    [[ -n ${cli_config:-} ]] || return 0
    [[ $cli_config == "$runtime_dir/ubo-m6b-cli."* && ! -L $cli_config && $(realpath "$cli_config") == "$cli_config" \
        && $(stat -c '%u:%a' "$cli_config") == "$uid:700" && $(stat -c '%d:%i' "$cli_config") == "$cli_identity" ]] || return 1
    # Fixed files only; unexpected client-created files cause a visible cleanup failure.
    [[ ! -L $cli_config/config.json ]] || return 1
    rm -f -- "$cli_config/config.json" && rmdir -- "$cli_config"
}
m6_identity() {
    [[ $(uname -s) == Linux && $uid != 0 && $(id -un) == "$expected_user" && $expected_user != ubo-deploy ]] || m6_fail wrong_user_or_root;
    [[ $(hostname -s) == "$expected_host" ]] || m6_fail wrong_host;
    # Closed host-role allowlist: unknown/production hosts cannot be authorized by --expected-host.
    case $host_mode in
        dedicated) [[ $expected_host == ubo-m6b-validate ]] || m6_fail dedicated_host_required;;
        shared-staging) [[ $expected_host == ubo-stage-app && $expected_user == codex-validation ]] || m6_fail shared_staging_identity;;
        *) m6_fail invalid_host_mode;;
    esac
}
m6_checkout() {
    [[ $repo == "$base/checkouts/"* && $repo != *'/public_html/'* && $repo != *'/www/'* && $repo != /var/www/* ]] || m6_fail deployed_or_webroot_checkout;
    [[ -d $repo/.git && ! -L $repo/.git && $(realpath "$(m6_git rev-parse --absolute-git-dir)") == "$repo/.git" ]] || m6_fail standalone_git_required;
    [[ $(realpath "$(m6_git rev-parse --show-toplevel)") == "$repo" && ! -e $repo/.git/commondir && ! -e $repo/.git/objects/info/alternates ]] || m6_fail shared_or_redirected_checkout;
    [[ $(realpath "$repo/$(m6_git rev-parse --git-common-dir)") == "$repo/.git" ]] || m6_fail shared_git_metadata;
    [[ $(m6_git rev-parse HEAD) == "$expected_sha" ]] || m6_fail wrong_head;
    [[ -z $(m6_git status --porcelain=v1 --untracked-files=all) && -z $(m6_git ls-files --others) ]] || m6_fail dirty_or_untracked_checkout;
    [[ $(sha256sum "$repo/database/migrations/025_site_build_deployment.sql") == "$migration_hash "* ]] || m6_fail migration_025_hash;
    m6_git cat-file -e "$baseline^{commit}" || m6_fail missing_scope_history;
    local number files blob
    for number in {1..24}; do
        printf -v number '%03d' "$number"
        files=("$repo/database/migrations/${number}_"*.sql)
        [[ ${#files[@]} == 1 && -f ${files[0]} ]] || m6_fail historical_migration_inventory;
        blob=$(m6_git rev-parse "$baseline:database/migrations/${files[0]##*/}") || m6_fail historical_migration_missing;
        [[ $(m6_git hash-object --no-filters "${files[0]}") == "$blob" ]] || m6_fail historical_migration_changed;
    done
}
m6_endpoint() {
    [[ -z ${DOCKER_TLS_VERIFY:-}${DOCKER_CERT_PATH:-}${DOCKER_API_VERSION:-} ]] || m6_fail docker_override;
    [[ -z ${DOCKER_HOST:-} || -z ${DOCKER_CONTEXT:-} ]] || m6_fail ambiguous_docker_endpoint;
    [[ $socket == "/run/user/$uid/docker.sock" && $(realpath "$socket") == "$socket" && $(stat -c '%F:%u' "$socket") == "socket:$uid" ]] || m6_fail rootless_socket_owner;
    m6_private "/run/user/$uid"
    endpoint="unix://$socket"
    # The explicit endpoint is authoritative. Never load the user's client config or its currentContext.
    [[ -z ${DOCKER_CONTEXT:-} && ${DOCKER_HOST:-$endpoint} == "$endpoint" ]] || m6_fail remote_or_conflicting_endpoint;
}
m6_root_anchor() {
    local mode parent=${1%/*}
    [[ -f $1 && ! -L $1 && $(realpath "$1") == "$1" && $(stat -c %u "$1") == 0 && ! -w $1 ]] || return 1
    mode=$(stat -c %a "$1")
    [[ $mode =~ ^[0-7]{3,4}$ ]] && (( (8#$mode & 06022) == 0 )) || return 1
    while [[ -n $parent ]]; do
        [[ -d $parent && -x $parent && ! -w $parent && $(stat -c %u "$parent") == 0 ]] || return 1
        mode=$(stat -c %a "$parent"); [[ $mode =~ ^[0-7]{3,4}$ ]] && (( (8#$mode & 0022) == 0 )) || return 1
        [[ $parent != / ]] || break
        parent=${parent%/*}; [[ -n $parent ]] || parent=/
    done
}
m6_volume_ok() {
    [[ ${layout:-home} == volume ]] || return 0
    local marker=/etc/ubo-validation/volume.uuid helper=/usr/local/bin/ubo-test-volume-check actual_uuid mount_id path
    [[ $host_mode == shared-staging && $expected_host == ubo-stage-app && $expected_user == codex-validation ]] || return 1
    m6_root_anchor "$marker" && m6_root_anchor "$helper" || return 1
    [[ $(stat -c %s "$marker") -le 65 ]] || return 1
    actual_uuid=$(cat "$marker")
    [[ $actual_uuid =~ ^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$ ]] || return 1
    m6_volume_helper "$helper" || return 1
    [[ $(realpath "$volume_mount") == "$volume_mount" && $(findmnt -rn --mountpoint "$volume_mount" -o TARGET) == "$volume_mount" \
        && $(findmnt -rn --mountpoint "$volume_mount" -o UUID) == "$actual_uuid" && $(stat -c %d "$volume_mount") != "$(stat -c %d /)" ]] || return 1
    mount_id=$(findmnt -rn --mountpoint "$volume_mount" -o ID)
    [[ $mount_id =~ ^[0-9]+$ && ( -z ${volume_uuid:-} || $volume_uuid == "$actual_uuid" ) && ( -z ${volume_mount_id:-} || $volume_mount_id == "$mount_id" ) ]] || return 1
    for path in "$base" "$base/docker" "$base/checkouts" "$base/evidence" "$base/tmp"; do
        m6_volume_directory "$path" || return 1
    done
    volume_uuid=$actual_uuid; volume_mount_id=$mount_id
}
m6_volume_helper() { [[ -x $1 ]] && timeout --kill-after=2s 15s "$1" >/dev/null 2>&1; }
m6_volume_directory() {
    [[ -d $1 && ! -L $1 && $(realpath "$1") == "$1" && $(stat -c '%u:%a' "$1") == "$uid:700" \
        && $(findmnt -rn --target "$1" -o TARGET) == "$volume_mount" && $(stat -c %d "$1") == "$(stat -c %d "$volume_mount")" ]]
}
m6_layout() {
    case $layout in
        home) base="$account_home/m6b-validation";;
        volume) volume_mount=/mnt/ubo_stage_testdata; base="$volume_mount/codex-validation"; m6_volume_ok || m6_fail volume_identity; volume_ready=1;;
        *) m6_fail invalid_layout;;
    esac
    m6_private "$base"; m6_private "$base/checkouts"; m6_private "$base/evidence"; m6_private "$base/tmp"
}
m6_docker_root() {
    [[ $(realpath "$docker_root") == "$docker_root" && $(stat -c %u "$docker_root") == "$uid" ]] || m6_fail rootless_data_owner
    if [[ $layout == volume ]]; then [[ $docker_root == "$base/docker" ]] || m6_fail wrong_volume_docker_root
    else [[ $docker_root == "$account_home/"* ]] || m6_fail rootless_data_owner; fi
}
m6_pin_storage() {
    # Pin the verified filesystem before any disk write: unmount/overmount cannot redirect writes to boot.
    exec {base_fd}<"$base"
    storage_base="/proc/$$/fd/$base_fd/."
    [[ $(stat -c '%d:%i' "$storage_base") == "$(stat -c '%d:%i' "$base")" ]] && m6_volume_ok || m6_fail storage_identity_changed
}
m6_delegation() {
    [[ $(stat -f -c %T /sys/fs/cgroup) == cgroup2fs ]] || m6_fail cgroup_v2_required;
    local controllers=" $(cat "/sys/fs/cgroup/user.slice/user-$uid.slice/user@$uid.service/cgroup.controllers") " controller
    for controller in cpu memory pids; do [[ $controllers == *" $controller "* ]] || m6_fail controller_not_delegated; done
    [[ $(m6_system show docker.service -p ActiveState --value) == active ]] || m6_fail rootless_service_inactive;
    [[ $(m6_system show docker.service -p Delegate --value) == yes ]] || m6_fail rootless_service_delegation;
    local daemon_pid
    daemon_pid=$(m6_system show docker.service -p MainPID --value)
    [[ $daemon_pid =~ ^[1-9][0-9]*$ && $(stat -c %u "/proc/$daemon_pid") == "$uid" ]] || m6_fail daemon_process_owner;
}
m6_free() { df -B1 --output=avail "$1" | tail -n 1 | tr -d ' '; }
m6_available_memory() { local key value unit; while read -r key value unit; do [[ $key != MemAvailable: ]] || { printf '%s\n' "$((value * 1024))"; return; }; done < /proc/meminfo; return 1; }
m6_headroom() {
    disk_floor=1073741824; memory_floor=268435456; boot_floor=1073741824
    local initial_disk=6442450944 initial_memory=3221225472 required_cpu=2 actual_cpu actual_memory boot_bytes data_bytes evidence_bytes
    if [[ $host_mode == shared-staging ]]; then
        # 1.5 GiB MySQL + 1 GiB PHP unit + .25 GiB external overhead + 1.5 GiB operating reserve.
        initial_disk=10737418240; disk_floor=4294967296; initial_memory=4563402752; memory_floor=1610612736; required_cpu=4
    fi
    actual_cpu=$(nproc); actual_memory=$(m6_available_memory); boot_bytes=$(m6_free /); data_bytes=$(m6_free "$docker_root")
    # Capacity shared by Docker and evidence is counted once, never added together.
    evidence_bytes=$data_bytes
    if [[ $(stat -c %d "$docker_root") != "$(stat -c %d "$base/evidence")" ]]; then evidence_bytes=$(m6_free "$base/evidence"); fi
    admission=$(printf 'Profile: %s-conservative; layout=%s\nCPU available=%s required=%s\nMemAvailable bytes=%s required=%s\nBoot free bytes=%s required=%s\nData filesystem free bytes=%s required=%s\nEvidence filesystem free bytes=%s required=%s (not additive)\n' "$host_mode" "$layout" "$actual_cpu" "$required_cpu" "$actual_memory" "$initial_memory" "$boot_bytes" "$boot_floor" "$data_bytes" "$initial_disk" "$evidence_bytes" "$initial_disk")
    printf '%s\n' "$admission"
    [[ $actual_cpu =~ ^[0-9]+$ && $actual_cpu -ge $required_cpu ]] || m6_fail cpu_headroom
    [[ $actual_memory =~ ^[0-9]+$ && $actual_memory -ge $initial_memory ]] || m6_fail memory_headroom
    [[ $boot_bytes =~ ^[0-9]+$ && $boot_bytes -ge $boot_floor ]] || m6_fail boot_disk_headroom
    [[ $data_bytes =~ ^[0-9]+$ && $evidence_bytes =~ ^[0-9]+$ && $data_bytes -ge $initial_disk && $evidence_bytes -ge $initial_disk ]] || m6_fail data_disk_headroom
}
m6_preflight() {
    local key tool
    for key in $(compgen -e); do
        case $key in GIT_*|APP_ENV|DB_*|DATABASE_URL|MYSQL_*|M6B_*|AWS_*|STRIPE_*|OPENAI_API_KEY|PHPRC|PHP_INI_SCAN_DIR|BASH_ENV|ENV|LD_PRELOAD|LD_LIBRARY_PATH)
            m6_fail inherited_configuration_refused;; esac
    done
    for tool in git docker timeout realpath stat sha256sum df du flock mktemp mkfifo systemctl systemd-run getent nproc findmnt sed head tail tr date sleep env hostname uname id cat rm rmdir; do command -v "$tool" >/dev/null || m6_fail missing_tool; done
    [[ $php == /* && -f $php && -x $php && $(realpath "$php") == "$php" ]] || m6_fail explicit_php_executable;
    uid=$(id -u); runtime_dir="/run/user/$uid"; m6_identity; m6_private "$runtime_dir"
    account_home=$(getent passwd "$uid"); account_home=${account_home#*:*:*:*:*:}; account_home=${account_home%%:*}
    [[ $account_home == /* && $(realpath "$account_home") == "$account_home" ]] || m6_fail resolved_home_required
    m6_layout
    # Report capacity even when the operator intentionally left Docker stopped.
    docker_root=$account_home; [[ $layout != volume ]] || docker_root="$base/docker"
    local capacity_probe=$docker_root
    m6_headroom
    repo=$(realpath "${BASH_SOURCE[0]%/*}/.."); m6_checkout
    # Linux PHP must have PDO MySQL in its reviewed CLI configuration. No automatic extension install.
    "$php" -d memory_limit=256M -r 'exit(PHP_OS_FAMILY === "Linux" && function_exists("posix_geteuid") && in_array("mysql", PDO::getAvailableDrivers(), true) ? 0 : 2);' || m6_fail linux_pdo_mysql_required
    m6_endpoint; m6_delegation; m6_cli_config
    docker_root=$(m6_docker info --format '{{json .}}' 2>/dev/null | m6_guard engine "$platform") || m6_fail engine_not_approved
    m6_docker_root
    image_id=$(m6_docker image inspect "docker.io/library/mysql@$digest" 2>/dev/null | m6_guard image "$digest" "$platform") || m6_fail cached_official_image_required
    image_bytes=$(m6_docker image inspect "$image_id" --format '{{.Size}}' 2>/dev/null)
    [[ $image_bytes =~ ^[0-9]+$ && $image_bytes -le 4294967296 ]] || m6_fail image_storage_budget
    [[ $docker_root == "$capacity_probe" ]] || m6_headroom
}
m6_cgroup() {
    local group cpu quota period
    group=$(m6_system show "docker-$container_id.scope" -p ControlGroup --value)
    [[ $group == "/user.slice/user-$uid.slice/"* && $group != *..* && $(realpath "/sys/fs/cgroup$group") == "/sys/fs/cgroup$group" ]] || m6_fail container_cgroup_identity
    cg="/sys/fs/cgroup$group"
    cpu=$(cat "$cg/cpu.max"); read -r quota period <<< "$cpu"
    [[ $quota =~ ^[0-9]+$ && $period =~ ^[1-9][0-9]*$ && $quota -eq $period && $(cat "$cg/memory.max") == 1610612736 && $(cat "$cg/memory.swap.max") == 0 && $(cat "$cg/pids.max") == 128 ]] || m6_fail container_limits_not_effective
    printf 'Observed container cpu.max=%s memory.max=1610612736 memory.swap.max=0 pids.max=128\n' "$cpu" >> "$evidence/report.txt"
}
m6_php_cgroup() {
    local group quota period
    group=$(m6_system show "$unit" -p ControlGroup --value)
    [[ $group == "/user.slice/user-$uid.slice/"* && $group != *..* && $(realpath "/sys/fs/cgroup$group") == "/sys/fs/cgroup$group" ]] || m6_fail php_cgroup_identity
    read -r quota period < "/sys/fs/cgroup$group/cpu.max"
    php_cg="/sys/fs/cgroup$group"
    [[ $quota =~ ^[0-9]+$ && $period =~ ^[1-9][0-9]*$ && $quota -eq $period && $(cat "/sys/fs/cgroup$group/memory.max") == 1073741824 && $(cat "/sys/fs/cgroup$group/memory.swap.max") == 0 && $(cat "/sys/fs/cgroup$group/pids.max") == 32 ]] || m6_fail php_limits_not_effective
    printf 'Observed PHP cgroup: CPU=1 memory.max=1073741824 memory.swap.max=0 pids.max=32\n' >> "$evidence/report.txt"
}
m6_redact() {
    # Keep more than the longest secret across read boundaries, including database-name prefixes.
    local chunk='' carry='' joined emit bytes=0 limit=2097152
    while IFS= read -r -N 4096 chunk || [[ -n $chunk ]]; do
        joined="$carry$chunk"; joined=${joined//"$password"/[REDACTED]}; joined=${joined//"$token"/[REDACTED]}; joined=${joined//"${token:0:16}"/[REDACTED]}
        if [[ ${#joined} -gt 128 ]]; then emit=${joined:0:${#joined}-128}; carry=${joined: -128}; else carry=$joined; emit=''; fi
        if (( bytes + ${#emit} <= limit )); then printf '%s' "$emit"; else : > "$scratch/output-limit"; fi
        bytes=$((bytes + ${#emit})); chunk=''
    done
    if (( bytes + ${#carry} <= limit )); then printf '%s' "$carry"; else : > "$scratch/output-limit"; fi
}
m6_owned_unit() { [[ $(m6_system show "$unit" -p Description --value 2>/dev/null) == "M6B validation $token" ]]; }
m6_unit_snapshot() {
    local data rc=0 key value count=0 seen='|'
    data=$(m6_system show "$unit" -p LoadState -p ActiveState -p Description -p ControlGroup 2>/dev/null) || rc=$?
    unit_load=''; unit_active=''; unit_description=''; unit_group=''
    while IFS='=' read -r key value; do
        [[ $seen != *"|$key|"* ]] || return 1
        seen+="$key|"; count=$((count + 1))
        case $key in LoadState) unit_load=$value;; ActiveState) unit_active=$value;; Description) unit_description=$value;; ControlGroup) unit_group=$value;; *) return 1;; esac
    done <<< "$data"
    [[ $count == 4 && -n $unit_load && -n $unit_active ]] || return 1
    # systemctl may exit nonzero for an unknown unit. Only explicit metadata proves absence.
    [[ $rc == 0 || ( $unit_load == not-found && $unit_active == inactive && -z $unit_group ) ]]
}
m6_group_path() {
    [[ $1 == "/user.slice/user-$uid.slice/"*"/$unit" && $1 != *..* ]] || return 1
    printf '/sys/fs/cgroup%s\n' "$1"
}
m6_tree_empty() {
    local path=$1 current='' component events
    # Walk from an accessible ancestor: an unreadable directory is not proof of removal.
    [[ $path == /* ]] || return 1
    local -a components; IFS=/ read -r -a components <<< "$path"
    for component in "${components[@]}"; do
        [[ -n $component ]] || continue
        current+="/$component"
        [[ ! -L $current ]] || return 1
        if [[ ! -e $current ]]; then return 0; fi
        [[ -d $current && -r $current && -x $current ]] || return 1
    done
    [[ -f $path/cgroup.events && -r $path/cgroup.events ]] || return 1
    events=$(cat "$path/cgroup.events") || return 1
    # cgroup.events populated includes descendants, unlike the parent's cgroup.procs alone.
    [[ $(printf '%s\n' "$events" | sed -n 's/^populated //p') == 0 ]]
}
m6_stop_unit() {
    [[ ${unit_attempted:-0} == 0 ]] && return 0
    local recorded=${php_cg:-} captured
    m6_unit_snapshot || return 1
    if [[ $unit_load == not-found ]]; then
        [[ $unit_active == inactive && -z $unit_group ]] || return 1
        [[ -z $recorded ]] || m6_tree_empty "$recorded"
        return $?
    fi
    [[ $unit_load == loaded && $unit_description == "M6B validation $token" ]] || return 1
    if [[ -n $unit_group ]]; then
        captured=$(m6_group_path "$unit_group") || return 1
        [[ -z $recorded || $captured == "$recorded" ]] || return 1
        recorded=$captured
    else [[ $unit_active == inactive || $unit_active == failed ]] || return 1; fi
    # Capture ownership and cgroup before stop; never infer success from a failed lookup.
    m6_system stop "$unit" >/dev/null 2>&1 || return 1
    m6_unit_snapshot || return 1
    if [[ $unit_load == not-found ]]; then
        [[ $unit_active == inactive && -z $unit_group ]] || return 1
    else
        [[ $unit_load == loaded && $unit_description == "M6B validation $token" && ( $unit_active == inactive || $unit_active == failed ) ]] || return 1
        [[ -z $unit_group || $(m6_group_path "$unit_group") == "$recorded" ]] || return 1
    fi
    [[ -z $recorded ]] || m6_tree_empty "$recorded"
}
m6_cleanup_container() {
    [[ ${container_attempted:-0} == 0 ]] && return 0
    local verified
    # Include partial create/start: resolve only our exact name, then verify ID + all ownership fields.
    verified=$(m6_docker inspect "${container_id:-ubo-m6b-$token}" 2>/dev/null | m6_guard owner 2>/dev/null) || return 1
    [[ -z ${container_id:-} || $verified == "$container_id" ]] || return 1
    if [[ ${evidence_safe:-1} == 1 ]]; then m6_docker logs --tail 200 "$verified" 2>&1 | m6_redact > "$evidence/mysql-tail.txt" || true; fi
    m6_docker rm --force "$verified" >/dev/null 2>&1 || return 1
}
m6_finish() {
    local status=$? cleanup=PASS
    trap - EXIT INT TERM
    set +e
    if [[ ${volume_ready:-0} == 1 ]] && ! m6_volume_ok; then evidence_safe=0; failure=infrastructure_volume_identity; status=2; fi
    m6_stop_unit || cleanup=FAILED_unit_ownership_or_stop
    # Wait only for our local collector. Stopping the cgroup closes its inherited output pipes.
    # GNU timeout owns a separate process group. Never signal the launcher's group or search by name.
    if [[ -n ${supervisor:-} ]] && kill -0 "$supervisor" 2>/dev/null; then
        kill -TERM -- "-$supervisor" 2>/dev/null
        sleep 0.2
        kill -KILL -- "-$supervisor" 2>/dev/null
        wait "$supervisor" 2>/dev/null
    fi
    if [[ -n ${collector:-} ]]; then kill -TERM "$collector" 2>/dev/null; wait "$collector" 2>/dev/null; fi
    # Never run another PHP guard while an un-stopped test tree may still contain three PHP processes.
    if [[ $cleanup == PASS ]]; then m6_cleanup_container || cleanup=FAILED_orphan_requires_operator_review; fi
    m6_remove_cli_config || cleanup=FAILED_client_config_removal
    [[ $cleanup == PASS ]] || status=2
    [[ $cleanup == PASS ]] || printf 'M6B cleanup: %s\n' "$cleanup" >&2
    # Only fixed files in the private directory created by this run. No recursive removal.
    if [[ -n ${scratch:-} && $scratch == "$runtime_dir/ubo-m6b."* && ! -L $scratch ]]; then
        rm -f -- "$scratch/credentials" "$scratch/go" "$scratch/output-limit" "$scratch/stream" || cleanup=FAILED_scratch_removal
        rmdir -- "$scratch" || cleanup=FAILED_scratch_removal
    fi
    if [[ ${volume_ready:-0} == 1 ]] && ! m6_volume_ok; then evidence_safe=0; failure=infrastructure_volume_identity; status=2; fi
    if [[ -n ${disk_scratch:-} && $evidence_safe == 1 ]]; then
        [[ $disk_scratch == "$storage_base/tmp/run."* && ! -L $disk_scratch && $(stat -c '%u:%a' "$disk_scratch") == "$uid:700" ]] && rmdir -- "$disk_scratch" || cleanup=FAILED_disk_scratch_removal
    fi
    [[ $cleanup == PASS ]] || status=2
    if [[ -n ${evidence:-} && $evidence_safe == 1 ]]; then
        printf 'Test exit: %s\nFailure class: %s\nCleanup: %s\nOverall: %s\n' "${test_exit:-NOT_EXECUTED}" "${failure:-prerequisite_or_startup}" "$cleanup" "$([[ $status == 0 && $cleanup == PASS ]] && echo PASS || echo NON_SUCCESS)" >> "$evidence/report.txt"
    fi
    unset MYSQL_ROOT_PASSWORD M6B_MYSQL_PASSWORD M6B_MYSQL_RUN_TOKEN token password
    if [[ -n ${evidence:-} && $evidence_safe == 1 ]]; then
        (cd "$evidence" && sha256sum report.txt test-output.txt mysql-tail.txt 2>/dev/null > SHA256SUMS) || status=2
        printf 'Private evidence: %s\n' "${evidence_path:-$evidence}"
    fi
    [[ -z ${base_fd:-} ]] || exec {base_fd}<&-
    if [[ $evidence_safe == 0 ]]; then
        # Do not open/create replacement evidence or temporary files beneath a lost mount.
        local emergency
        emergency=$(mktemp "$runtime_dir/ubo-m6b-failure.XXXXXXXX")
        printf 'Overall: NON_SUCCESS\nFailure: infrastructure_volume_identity\nTest exit: %s\nCleanup: %s\nCode SHA: %s\nContainer ID: %s\nVolume evidence unavailable; operator review required.\n' "${test_exit:-NOT_EXECUTED}" "$cleanup" "$expected_sha" "${container_id:-unknown}" > "$emergency"
        sha256sum "$emergency" > "$emergency.sha256"
        printf 'Volume identity lost; runtime diagnostic: %s\n' "$emergency" >&2
    fi
    exit "$status"
}
m6_budget() {
    local writable used temporary=0
    m6_volume_ok || { failure=infrastructure_volume_identity; return 1; }
    writable=$(m6_docker inspect --size "$container_id" --format '{{.SizeRw}}' 2>/dev/null) || return 1
    used=$(du -sb "$evidence"); used=${used%%$'\t'*}
    if [[ -n ${disk_scratch:-} ]]; then temporary=$(du -sb "$disk_scratch"); temporary=${temporary%%$'\t'*}; fi
    [[ $temporary =~ ^[0-9]+$ && $temporary -le 4194304 ]] || return 1
    [[ $writable =~ ^[0-9]+$ && $used =~ ^[0-9]+$ ]] || return 1
    # Conservative accounting: full image, DB tmpfs reserve, bounded Docker logs, evidence and scratch.
    (( image_bytes + writable + used + 1073741824 + 2097152 + 4194304 <= 6442450944 )) || return 1
    [[ $(m6_free /) -ge $boot_floor && $(m6_free "$docker_root") -ge $disk_floor && $(m6_available_memory) -ge $memory_floor ]] || return 1
    [[ $(stat -c %d "$docker_root") == "$(stat -c %d "$evidence")" || $(m6_free "$evidence") -ge $disk_floor ]]
}
m6_run() {
    failure=startup
    local started=$SECONDS remaining gate_end result code
    m6_volume_ok || m6_fail volume_identity_before_creation
    m6_headroom
    m6_pin_storage
    exec 9> "$storage_base/run.lock"; flock -n 9 || m6_fail another_launcher_active
    evidence=$(mktemp -d "$storage_base/evidence/run-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
    evidence_path="$base/evidence/${evidence##*/}"
    scratch=$(mktemp -d "$runtime_dir/ubo-m6b.XXXXXXXX")
    disk_scratch=$(mktemp -d "$storage_base/tmp/run.XXXXXXXX")
    : > "$evidence/test-output.txt"; : > "$evidence/mysql-tail.txt"
    printf 'Code SHA: %s\nMigration 025 SHA256: %s\nHost/user: %s/%s (UID %s)\nImage digest: %s\nImage ID: %s\nPlatform: %s\nRequested MySQL: CPU=1 memory=1536MiB additional-swap=0 PIDs=128 tmpfs=1GiB (within memory) logs=2x1MiB\nRequested PHP: 256MiB each, maximum 3 processes; systemd memory=1GiB swap=0 tasks=32\nDeadline: 1200s plus bounded cleanup; storage: monitored 6GiB budget, NOT a quota\n' "$expected_sha" "$migration_hash" "$expected_host" "$expected_user" "$uid" "$digest" "$image_id" "$platform" > "$evidence/report.txt"
    printf 'Host mode: %s\nPre-run MemAvailable: %s\nPre-run disk available: %s\nStop floors: disk=%s memory=%s bytes\n' "$host_mode" "$(m6_available_memory)" "$(m6_free "$docker_root")" "$disk_floor" "$memory_floor" >> "$evidence/report.txt"
    printf '%s\nLayout: %s\nVolume UUID: %s\nVolume mount ID: %s\n' "${admission:-}" "$layout" "$volume_uuid" "$volume_mount_id" >> "$evidence/report.txt"
    "$php" -r 'echo "PHP ", PHP_VERSION, "\n";' >> "$evidence/report.txt"
    m6_docker version --format 'Docker client={{.Client.Version}} server={{.Server.Version}}' >> "$evidence/report.txt" 2>/dev/null
    systemctl --version | head -n 1 >> "$evidence/report.txt"
    token=$("$php" -n -r 'echo bin2hex(random_bytes(16));'); password=$("$php" -n -r 'echo bin2hex(random_bytes(32));')
    export M6B_MYSQL_RUN_TOKEN=$token M6B_MYSQL_IMAGE_ID=$image_id MYSQL_ROOT_PASSWORD=$password
    unit="ubo-m6b-$token.service"
    m6_volume_ok || { failure=infrastructure_volume_identity; exit 2; }
    container_attempted=1
    container_id=$(m6_docker create --pull=never --platform "$platform" --name "ubo-m6b-$token" --label "ubo.m6b.owner=$token" --label ubo.m6b.launcher=linux-v1 --publish 127.0.0.1::3306 --network bridge --ipc private --cgroupns private --security-opt no-new-privileges=true --cpus 1 --memory 1536m --memory-swap 1536m --pids-limit 128 --tmpfs /var/lib/mysql:rw,nosuid,size=1073741824 --log-driver local --log-opt max-size=1m --log-opt max-file=2 --log-opt compress=false --env MYSQL_ROOT_PASSWORD --env MYSQL_ROOT_HOST=% "$image_id" --skip-log-bin 2>/dev/null) || m6_fail container_create
    [[ $container_id =~ ^[a-f0-9]{64}$ ]] || { container_id=''; m6_fail container_create_identity; }
    export M6B_MYSQL_CONTAINER_ID=$container_id
    printf 'Container ID: %s\n' "$container_id" >> "$evidence/report.txt"
    m6_docker inspect "$container_id" 2>/dev/null | m6_guard owner >/dev/null || m6_fail container_owner
    m6_docker start "$container_id" >/dev/null 2>&1 || m6_fail container_start
    port=$(m6_docker inspect "$container_id" "$image_id" 2>/dev/null | m6_guard container "$digest" "$platform") || m6_fail container_controls
    m6_cgroup
    printf 'Observed: rootless Linux, cgroup v2/systemd, delegated cpu/memory/pids; owned image/container; loopback; tmpfs; bounded logs\n' >> "$evidence/report.txt"
    # Generated test credentials only. No environment dumps and no secrets in systemd command arguments.
    printf 'export M6B_LINUX_RUN=1 M6B_MYSQL_RUN_TOKEN=%q M6B_MYSQL_IMAGE_ID=%q M6B_MYSQL_CONTAINER_ID=%q M6B_MYSQL_PASSWORD=%q M6B_MYSQL_PORT=%q M6B_DOCKER_SOCKET=%q M6B_IMAGE_DIGEST=%q M6B_IMAGE_PLATFORM=%q\n' "$token" "$image_id" "$container_id" "$password" "$port" "$socket" "$digest" "$platform" > "$scratch/credentials"
    printf 'export M6B_DOCKER_CONFIG=%q TMPDIR=%q TMP=%q TEMP=%q\n' "$cli_config" "$disk_scratch" "$disk_scratch" "$disk_scratch" >> "$scratch/credentials"
    remaining=$((1200 - SECONDS + started)); (( remaining > 0 )) || { failure=timeout; exit 124; }
    [[ $(m6_system show "$unit" -p LoadState --value 2>/dev/null) == not-found ]] || m6_fail unit_already_exists
    unit_attempted=1
    mkfifo -m 600 "$scratch/stream"
    m6_redact < "$scratch/stream" > "$evidence/test-output.txt" & collector=$!
    timeout --kill-after=12s "${remaining}s" systemd-run --user --unit "$unit" --description "M6B validation $token" --service-type=exec --wait --pipe --quiet --property="RuntimeMaxSec=${remaining}s" --property=TimeoutStopSec=10s --property=KillMode=control-group --property=SendSIGKILL=yes --property=CPUQuota=100% --property=MemoryMax=1G --property=MemorySwapMax=0 --property=TasksMax=32 --property=LimitFSIZE=4M --property=LimitCORE=0 --working-directory="$repo" \
            /usr/bin/env -i "PATH=$PATH" "HOME=$account_home" "XDG_RUNTIME_DIR=/run/user/$uid" /bin/bash -c 'set +x; set -eu; for i in {1..100}; do [[ -f $1/go ]] && break; sleep 0.1; done; [[ -f $1/go ]]; source "$1/credentials"; exec "$2" -d memory_limit=256M -d max_execution_time=0 "$3/tests/WebsitePlatformM6BMySql.php"' _ "$scratch" "$php" "$repo" > "$scratch/stream" 2>&1 & supervisor=$!
    gate_end=$((SECONDS + 8))
    until m6_owned_unit; do (( SECONDS < gate_end )) || m6_fail supervisor_unavailable; sleep 0.1; done
    [[ $(m6_system show "$unit" -p MemoryMax --value) == 1073741824 && $(m6_system show "$unit" -p MemorySwapMax --value) == 0 && $(m6_system show "$unit" -p TasksMax --value) == 32 && $(m6_system show "$unit" -p KillMode --value) == control-group ]] || m6_fail supervisor_limits
    m6_php_cgroup
    printf 'Observed PHP supervisor: MemoryMax=1073741824 MemorySwapMax=0 TasksMax=32 KillMode=control-group; deadline=%ss\n' "$remaining" >> "$evidence/report.txt"
    m6_volume_ok || { failure=infrastructure_volume_identity; exit 2; }
    test_exit=NO_COMPLETED_RESULT
    : > "$scratch/go"
    failure=test_failure
    while kill -0 "$supervisor" 2>/dev/null; do
        if (( SECONDS - started >= 1200 )); then failure=timeout; exit 124; fi
        if [[ -f $scratch/output-limit ]]; then failure=output_budget; exit 2; fi
        m6_budget || { [[ $failure == infrastructure_volume_identity ]] || failure=headroom_or_storage_budget_or_inspection; exit 2; }
        sleep 1
    done
    test_exit=0; wait "$supervisor" || test_exit=$?; supervisor=''
    wait "$collector" || { failure=output_collector; exit 2; }; collector=''
    [[ $test_exit =~ ^[0-9]+$ && $test_exit -le 255 ]] || m6_fail supervisor_exit
    result=$(m6_system show "$unit" -p Result --value 2>/dev/null) || result=unknown
    # Successful transient units can be garbage-collected after systemd-run releases its reference.
    if [[ $test_exit == 0 && $(m6_system show "$unit" -p LoadState --value 2>/dev/null) == not-found ]]; then result=success; fi
    [[ $test_exit != 124 ]] || { failure=timeout; exit 124; }
    printf 'Supervisor result: %s\nContainer memory events: ' "$result" >> "$evidence/report.txt"
    tr '\n' ' ' < "$cg/memory.events" >> "$evidence/report.txt"; printf '\nContainer PID events: ' >> "$evidence/report.txt"; tr '\n' ' ' < "$cg/pids.events" >> "$evidence/report.txt"; printf '\n' >> "$evidence/report.txt"
    case $result in timeout) failure=timeout; exit 124;; oom-kill|resources) failure=resource_exhaustion; exit 2;; success) :;; *) [[ $test_exit != 0 ]] || { failure=supervisor_unknown; exit 2; };; esac
    [[ $(< "$evidence/test-output.txt") != *'Allowed memory size of'* ]] || { failure=resource_exhaustion; exit 2; }
    if [[ $(m6_docker inspect "$container_id" --format '{{.State.OOMKilled}}' 2>/dev/null) != false ]] || [[ $(cat "$cg/memory.events") =~ (oom_kill|oom)[[:space:]]+[1-9] ]] || [[ $(cat "$cg/pids.events") =~ max[[:space:]]+[1-9] ]]; then failure=resource_exhaustion; exit 2; fi
    [[ ! -f $scratch/output-limit ]] || { failure=output_budget; exit 2; }
    [[ $test_exit != 0 ]] || failure=none
    exit "$test_exit"
}
m6_main() {
    local mode='' expected_sha='' expected_host='' expected_user='' php='' socket='' digest='' platform='' host_mode=dedicated layout=home
    local baseline=5baae28c9af68cca7694a912d7f35c87c50c9dc5 migration_hash=dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0
    while (($#)); do
        case $1 in
            --check-only|--run) [[ -z $mode ]] || m6_fail choose_one_mode; mode=$1; shift;;
            --expected-sha|--expected-host|--expected-user|--php|--docker-socket|--image-digest|--platform|--host-mode|--layout)
                [[ $# -ge 2 ]] || m6_fail missing_argument
                case $1 in --expected-sha) expected_sha=$2;; --expected-host) expected_host=$2;; --expected-user) expected_user=$2;; --php) php=$2;; --docker-socket) socket=$2;; --image-digest) digest=$2;; --platform) platform=$2;; --host-mode) host_mode=$2;; --layout) layout=$2;; esac; shift 2;;
            *) m6_fail unknown_argument;;
        esac
    done
    [[ -n $mode && $expected_sha =~ ^[a-f0-9]{40}$ && $expected_host =~ ^[a-zA-Z0-9-]+$ && $expected_user =~ ^[a-z_][a-z0-9_-]*$ && $digest =~ ^sha256:[a-f0-9]{64}$ && $platform =~ ^linux/(amd64|arm64)$ && -n $php && -n $socket ]] || m6_fail required_explicit_arguments
    m6_init; m6_preflight
    if [[ $mode == --check-only ]]; then printf 'Prerequisites accepted. NOT EXECUTED: container, credentials, SQL, migrations, workers.\n'; exit 0; fi
    m6_run
}
if [[ ${BASH_SOURCE[0]} == "$0" ]]; then m6_main "$@"; fi
