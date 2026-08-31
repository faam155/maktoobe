param(
    [ValidateSet('Initialize', 'Start', 'Stop', 'Status')]
    [string]$Action = 'Status',
    [string]$MySqlBase = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64',
    [int]$Port = 3307
)

$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$runtimeRoot = Join-Path $projectRoot '.runtime\mysql'
$dataPath = Join-Path $runtimeRoot 'data'
$configPath = Join-Path $runtimeRoot 'my.ini'
$clientPath = Join-Path $runtimeRoot 'root-client.ini'
$initPath = Join-Path $runtimeRoot 'initialize.sql'
$mysqlServer = Join-Path $MySqlBase 'bin\mysqld.exe'
$mysqlClient = Join-Path $MySqlBase 'bin\mysql.exe'
$mysqlAdmin = Join-Path $MySqlBase 'bin\mysqladmin.exe'
$utf8 = [Text.UTF8Encoding]::new($false)

function Write-LocalFile([string]$Path, [string]$Content) {
    [IO.File]::WriteAllText($Path, $Content, $utf8)
}

function New-LocalSecret {
    $bytes = [byte[]]::new(32)
    [Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    return [Convert]::ToHexString($bytes).ToLowerInvariant()
}

function Test-LocalServer {
    if (-not (Test-Path -LiteralPath $clientPath)) { return $false }
    & $mysqlClient "--defaults-file=$clientPath" --no-login-paths --connect-timeout=2 --batch --skip-column-names -e 'SELECT 1' 2>$null | Out-Null
    return $LASTEXITCODE -eq 0
}

if (-not (Test-Path -LiteralPath $mysqlServer)) { throw "MySQL binary not found at $mysqlServer" }

if ($Action -eq 'Initialize') {
    if ((Test-Path -LiteralPath $runtimeRoot) -or (Test-Path -LiteralPath (Join-Path $projectRoot '.env')) -or (Test-Path -LiteralPath (Join-Path $projectRoot '.env.testing'))) {
        throw 'Initialization refused: runtime or environment files already exist. No data or credentials were overwritten.'
    }
    if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
        throw "Port $Port is already in use. Choose an unused local port."
    }
    New-Item -ItemType Directory -Path $dataPath -Force | Out-Null
    # This is a new, empty, project-owned data directory; no Laragon data is changed.
    if (@(Get-ChildItem -LiteralPath $dataPath -Force).Count -ne 0) { throw 'Data directory must be empty.' }
    $rootSecret = New-LocalSecret
    $appSecret = New-LocalSecret
    $testSecret = New-LocalSecret
    $mysqlBaseNormalized = $MySqlBase.Replace('\', '/')
    $dataNormalized = $dataPath.Replace('\', '/')
    $runtimeNormalized = $runtimeRoot.Replace('\', '/')
    Write-LocalFile $configPath @"
[mysqld]
basedir="$mysqlBaseNormalized"
datadir="$dataNormalized"
port=$Port
bind-address=127.0.0.1
mysqlx=0
skip-name-resolve
local-infile=0
secure-file-priv=NULL
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
default-time-zone=+00:00
log-error="$runtimeNormalized/server.log"
pid-file="$runtimeNormalized/server.pid"
"@
    Write-LocalFile $clientPath @"
[client]
host=127.0.0.1
port=$Port
protocol=tcp
user=root
password=$rootSecret
"@
    Write-LocalFile $initPath @"
ALTER USER 'root'@'localhost' IDENTIFIED BY '$rootSecret';
CREATE USER 'root'@'127.0.0.1' IDENTIFIED BY '$rootSecret';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE DATABASE maktoobe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE maktoobe_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'maktoobe_app'@'127.0.0.1' IDENTIFIED BY '$appSecret';
CREATE USER 'maktoobe_test'@'127.0.0.1' IDENTIFIED BY '$testSecret';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON maktoobe.* TO 'maktoobe_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON maktoobe_test.* TO 'maktoobe_test'@'127.0.0.1';
"@
    & $mysqlServer "--defaults-file=$configPath" --initialize-insecure
    if ($LASTEXITCODE -ne 0) { throw 'MySQL initialization failed. Inspect the ignored local server log; do not reset the directory.' }
    # The init file sets a random root password before the server accepts connections.
    $process = Start-Process -FilePath $mysqlServer -ArgumentList @("--defaults-file=`"$configPath`"", "--init-file=`"$initPath`"") -WindowStyle Hidden -PassThru
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        if (Test-LocalServer) { $ready = $true; break }
        if ($process.HasExited) { break }
        Start-Sleep -Milliseconds 500
    }
    if (-not $ready) { throw 'MySQL did not become ready. Existing runtime is preserved for inspection.' }
    Remove-Item -LiteralPath $initPath
    $appEnvironment = [IO.File]::ReadAllText((Join-Path $projectRoot '.env.example')).Replace('DB_PASSWORD=', "DB_PASSWORD=$appSecret").Replace('DB_PORT=3307', "DB_PORT=$Port")
    $testEnvironment = [IO.File]::ReadAllText((Join-Path $projectRoot '.env.testing.example')).Replace('DB_PASSWORD=', "DB_PASSWORD=$testSecret").Replace('DB_PORT=3307', "DB_PORT=$Port")
    Write-LocalFile (Join-Path $projectRoot '.env') $appEnvironment
    Write-LocalFile (Join-Path $projectRoot '.env.testing') $testEnvironment
    Write-Output "MySQL ready on 127.0.0.1:$Port. Isolated development/test databases and private environment files created."
    exit 0
}

if (-not (Test-Path -LiteralPath $configPath)) { throw 'Project MySQL is not initialized. Run -Action Initialize once.' }

if ($Action -eq 'Start') {
    if (Test-LocalServer) { Write-Output 'Project MySQL is already running.'; exit 0 }
    if (Test-Path -LiteralPath $initPath) { throw 'Incomplete initialization found; inspect it before restarting.' }
    Start-Process -FilePath $mysqlServer -ArgumentList "--defaults-file=`"$configPath`"" -WindowStyle Hidden | Out-Null
    Write-Output 'Project MySQL startup requested. Use -Action Status to verify readiness.'
} elseif ($Action -eq 'Stop') {
    & $mysqlAdmin "--defaults-file=$clientPath" --no-login-paths shutdown
    if ($LASTEXITCODE -ne 0) { throw 'MySQL did not confirm shutdown.' }
    Write-Output 'Project MySQL stopped.'
} else {
    if (-not (Test-LocalServer)) { throw 'Project MySQL is not responding with the saved credentials.' }
    & $mysqlClient "--defaults-file=$clientPath" --no-login-paths --batch -e 'SELECT VERSION() AS version, @@port AS port, @@bind_address AS bind_address;'
}
