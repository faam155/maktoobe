param([string]$MySqlBase = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64')
$ErrorActionPreference = 'Stop'
$project = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$environment = Join-Path $project '.env.browser'
$client = Join-Path $project '.runtime/mysql/root-client.ini'
if (Test-Path -LiteralPath $environment) { throw 'Browser environment already exists; no credentials or data were changed.' }
if (-not (Test-Path -LiteralPath $client)) { throw 'Initialize/start the project MySQL server first.' }
$secret = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
$sqlPath = Join-Path $project '.runtime/browser-database.sql'
$sql = @"
CREATE DATABASE maktoobe_browser CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'maktoobe_browser'@'127.0.0.1' IDENTIFIED BY '$secret';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON maktoobe_browser.* TO 'maktoobe_browser'@'127.0.0.1';
"@
try {
    [IO.File]::WriteAllText($sqlPath, $sql, [Text.UTF8Encoding]::new($false))
    & (Join-Path $MySqlBase 'bin/mysql.exe') "--defaults-file=$client" --no-login-paths --execute="source $($sqlPath.Replace('\','/'))"
    if ($LASTEXITCODE -ne 0) { throw 'Browser database setup failed; inspect the existing database, do not reset it blindly.' }
    $content = [IO.File]::ReadAllText((Join-Path $project '.env.browser.example')).Replace('DB_PASSWORD=', "DB_PASSWORD=$secret")
    $key = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    $content = $content.Replace('APP_KEY=', "APP_KEY=base64:$key")
    [IO.File]::WriteAllText($environment, $content, [Text.UTF8Encoding]::new($false))
} finally {
    if (Test-Path -LiteralPath $sqlPath) { Remove-Item -LiteralPath $sqlPath }
}
Write-Output 'Isolated browser database/user and private .env.browser created.'
