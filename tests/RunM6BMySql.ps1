param([string]$Php = 'php', [switch]$CheckOnly)
$ErrorActionPreference = 'Stop'
# This launcher is a LOCAL test harness, never an application migration wrapper.
# It creates a new labelled disposable MySQL 8.4 container, no host volumes/services.
$docker = Get-Command docker -ErrorAction SilentlyContinue
if (-not $docker) { Write-Output 'NOT EXECUTED: local Docker CLI/engine and an existing mysql:8.4 image are required.'; exit 2 }
$phpCommand = Get-Command $Php -ErrorAction Stop
$phpArgs = @()
$drivers = & $phpCommand.Source -r 'echo json_encode(PDO::getAvailableDrivers());'
if ($drivers -notmatch 'mysql') {
    $extensions = Join-Path (Split-Path $phpCommand.Source) 'ext'
    if (Test-Path -LiteralPath (Join-Path $extensions 'php_pdo_mysql.dll')) {
        $phpArgs = @('-d', "extension_dir=$extensions", '-d', 'extension=pdo_mysql')
    }
    $drivers = & $phpCommand.Source @phpArgs -r 'echo json_encode(PDO::getAvailableDrivers());'
    if ($drivers -notmatch 'mysql') { Write-Output 'NOT EXECUTED: PHP PDO MySQL is required (no php.ini changes are made).'; exit 2 }
}
if ($env:DOCKER_HOST -and $env:DOCKER_HOST -notmatch '^(npipe://|unix://)') {
    throw 'Refusing a remote or unverified DOCKER_HOST override.'
}
$context = (& docker context inspect | ConvertFrom-Json)[0]
if ($LASTEXITCODE -ne 0 -or $context.Endpoints.docker.Host -notmatch '^(npipe://|unix://)') {
    throw 'Refusing a remote or unverified Docker endpoint.'
}
$image = & docker image inspect mysql:8.4 2>$null | ConvertFrom-Json
if ($LASTEXITCODE -ne 0 -or -not $image) { Write-Output 'NOT EXECUTED: an operator-provided local mysql:8.4 image is required; the harness does not install or pull software.'; exit 2 }
if ($CheckOnly) { Write-Output 'Local Docker endpoint, existing mysql:8.4 image and PDO MySQL prerequisites available.'; exit 0 }
$runToken = [guid]::NewGuid().ToString('N')
$containerName = "ubo-m6b-$runToken"
$ownedContainerId = $null
$environmentNames = @('MYSQL_ROOT_PASSWORD','M6B_MYSQL_PASSWORD','M6B_MYSQL_PORT','M6B_MYSQL_CONTAINER_ID','M6B_MYSQL_RUN_TOKEN','M6B_MYSQL_IMAGE_ID')
$priorEnvironment = @{}
foreach ($name in $environmentNames) { $priorEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
try {
    $env:MYSQL_ROOT_PASSWORD = [guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N')
    $env:M6B_MYSQL_PASSWORD = $env:MYSQL_ROOT_PASSWORD
    $env:M6B_MYSQL_RUN_TOKEN = $runToken
    $env:M6B_MYSQL_IMAGE_ID = $image[0].Id
    $ownedContainerId = (& docker run --detach --rm --pull=never --name $containerName --label "ubo.m6b.owner=$runToken" --publish '127.0.0.1::3306' --tmpfs '/var/lib/mysql:rw,nosuid,size=1g' --env MYSQL_ROOT_PASSWORD --env 'MYSQL_ROOT_HOST=%' mysql:8.4 --skip-log-bin).Trim()
    if ($LASTEXITCODE -ne 0 -or $ownedContainerId -notmatch '^[a-f0-9]{64}$') { throw 'Disposable container creation failed.' }
    $container = (& docker inspect $ownedContainerId | ConvertFrom-Json)[0]
    if ($container.Config.Labels.'ubo.m6b.owner' -ne $runToken -or $container.Image -ne $env:M6B_MYSQL_IMAGE_ID) { throw 'Created container identity mismatch.' }
    $binding = $container.NetworkSettings.Ports.'3306/tcp'[0]
    if ($binding.HostIp -ne '127.0.0.1') { throw 'Test endpoint is not bound to loopback.' }
    $env:M6B_MYSQL_CONTAINER_ID = $ownedContainerId
    $env:M6B_MYSQL_PORT = $binding.HostPort
    & $phpCommand.Source @phpArgs (Join-Path $PSScriptRoot 'WebsitePlatformM6BMySql.php')
    $testExit = $LASTEXITCODE
    if ($testExit -ne 0) { throw "Local MySQL harness failed with exit $testExit. No remote database was used." }
} finally {
    if ($ownedContainerId) {
        $owned = (& docker inspect $ownedContainerId 2>$null | ConvertFrom-Json)[0]
        if ($owned -and $owned.Id -eq $ownedContainerId -and $owned.Name -eq "/$containerName" -and $owned.Config.Labels.'ubo.m6b.owner' -eq $runToken) {
            & docker rm --force $ownedContainerId | Out-Null
            if ($LASTEXITCODE -ne 0) { Write-Warning "Cleanup required for task-owned container $containerName" }
            else { Write-Output "Removed task-owned disposable container $containerName." }
        }
    }
    foreach ($name in $environmentNames) { [Environment]::SetEnvironmentVariable($name, $priorEnvironment[$name], 'Process') }
}
