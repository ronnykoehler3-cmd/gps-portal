[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$')]
    [string]$Version,

    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string]$Build,

    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string]$Description,

    [string]$Channel = 'stable',
    [string]$WinScpSession = 'ssl.tk-kundendienst',
    [string]$RemoteDirectory = '/home/users/admin/www/update.tk-kundendienst.de/GPS-Portal',
    [string]$ManifestUrl = 'http://update.tk-kundendienst.de/GPS-Portal/latest.json',
    [string]$PhpExecutable = 'E:\Programme\PHP\php.exe',
    [string]$PrivateKey = 'C:\Users\ronny.koehler\Desktop\ssh-key\ronny.ppk'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Write-Utf8WithoutBom {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $temporaryFile = "$Path.utf8-temporary"

    try {
        Set-Content `
            -LiteralPath $temporaryFile `
            -Value $Content `
            -Encoding UTF8 `
            -NoNewline

        $phpCode = '$source=$argv[1];$target=$argv[2];$content=file_get_contents($source);if($content===false){exit(10);}if(substr($content,0,3)===chr(239).chr(187).chr(191)){$content=substr($content,3);}if(file_put_contents($target,$content)===false){exit(11);}'

        & $script:PhpExecutable `
            -r $phpCode `
            $temporaryFile `
            $Path

        if ($LASTEXITCODE -ne 0) {
            throw "PHP konnte die UTF-8-Datei nicht erzeugen: $Path"
        }
    }
    finally {
        if (Test-Path -LiteralPath $temporaryFile) {
            Remove-Item -LiteralPath $temporaryFile -Force
        }
    }
}

function Find-WinScpConsole {
    $candidates = @(
        'C:\Program Files (x86)\WinSCP\WinSCP.com',
        'C:\Program Files\WinSCP\WinSCP.com'
    )

    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate -PathType Leaf) {
            return $candidate
        }
    }

    throw 'WinSCP.com wurde nicht gefunden. Bitte WinSCP vollständig installieren.'
}

function Find-Pageant {
    $candidates = @(
        'C:\Program Files (x86)\WinSCP\PuTTY\pageant.exe',
        'C:\Program Files\WinSCP\PuTTY\pageant.exe',
        'C:\Program Files\PuTTY\pageant.exe',
        'C:\Program Files (x86)\PuTTY\pageant.exe'
    )

    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate -PathType Leaf) {
            return $candidate
        }
    }

    throw 'Pageant wurde nicht gefunden. Bitte WinSCP einschließlich PuTTY-Werkzeugen installieren.'
}

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$script:PhpExecutable = $PhpExecutable

if (-not (Test-Path -LiteralPath $script:PhpExecutable -PathType Leaf)) {
    throw "PHP wurde nicht gefunden: $script:PhpExecutable"
}

$componentSource = Join-Path $repoRoot 'public\components\com_gpsportal'
$releaseRoot = Join-Path $repoRoot 'build\releases'
$releaseDirectory = Join-Path $releaseRoot "gps-portal-$Version"
$stagingDirectory = Join-Path $releaseDirectory 'staging'
$filesDirectory = Join-Path $stagingDirectory 'files'
$packageName = "gps-portal-$Version.zip"
$packageFile = Join-Path $releaseDirectory $packageName
$checksumName = "gps-portal-$Version.sha256"
$checksumFile = Join-Path $releaseDirectory $checksumName
$latestFile = Join-Path $releaseDirectory 'latest.json'

if (-not (Test-Path -LiteralPath $componentSource -PathType Container)) {
    throw "Der Komponentenordner wurde nicht gefunden: $componentSource"
}

New-Item -ItemType Directory -Path $releaseDirectory -Force | Out-Null

if (Test-Path -LiteralPath $stagingDirectory) {
    Remove-Item -LiteralPath $stagingDirectory -Recurse -Force
}

New-Item -ItemType Directory -Path $filesDirectory -Force | Out-Null
Copy-Item -Path "$componentSource\*" -Destination $filesDirectory -Recurse -Force

$releaseDate = Get-Date -Format 'yyyy-MM-dd'
$versionPhp = @"
<?php

defined('_JEXEC') or die;

return [
    'project' => 'GPS-Portal',
    'version' => '$Version',
    'channel' => '$Channel',
    'build' => '$Build',
    'released_at' => '$releaseDate',
    'update_server' => '$ManifestUrl',
];
"@

Write-Utf8WithoutBom -Path (Join-Path $filesDirectory 'version.php') -Content ($versionPhp.TrimStart() + "`n")

$packageInformation = [ordered]@{
    schemaVersion = 1
    project = 'GPS-Portal'
    version = $Version
    build = $Build
    channel = $Channel
    releaseDate = $releaseDate
    description = $Description
}

$packageJson = $packageInformation | ConvertTo-Json -Depth 10
Write-Utf8WithoutBom -Path (Join-Path $stagingDirectory 'package.json') -Content ($packageJson + "`n")

if (Test-Path -LiteralPath $packageFile) {
    Remove-Item -LiteralPath $packageFile -Force
}

$tar = Get-Command 'tar.exe' -ErrorAction SilentlyContinue
if ($null -eq $tar) {
    throw 'Das in Windows enthaltene Programm tar.exe wurde nicht gefunden.'
}

Push-Location $stagingDirectory
try {
    & $tar.Source -a -c -f $packageFile 'package.json' 'files'
    if ($LASTEXITCODE -ne 0) {
        throw 'Das Update-ZIP konnte nicht erstellt werden.'
    }
}
finally {
    Pop-Location
}

$entryNames = @(& $tar.Source -tf $packageFile)
if ($LASTEXITCODE -ne 0) {
    throw 'Das erstellte Update-ZIP konnte nicht gelesen werden.'
}

foreach ($requiredEntry in @('package.json', 'files/version.php')) {
    if ($entryNames -notcontains $requiredEntry) {
        throw "Im Updatepaket fehlt: $requiredEntry"
    }
}

$invalidEntry = $entryNames | Where-Object { $_ -match '\\' } | Select-Object -First 1
if ($null -ne $invalidEntry) {
    throw "Das ZIP enthält einen ungültigen Windows-Pfad: $invalidEntry"
}

$sha256 = (Get-FileHash -LiteralPath $packageFile -Algorithm SHA256).Hash.ToLowerInvariant()
Write-Utf8WithoutBom -Path $checksumFile -Content ($sha256.ToUpperInvariant() + "`n")

$latestManifest = [ordered]@{
    schemaVersion = 1
    project = 'GPS-Portal'
    name = 'GPS-Portal'
    latest = $Version
    minimum = '1.0.0'
    mandatory = $false
    channel = $Channel
    releaseDate = $releaseDate
    build = $Build
    description = $Description
    download = $packageName
    checksum = $checksumName
}

$latestJson = $latestManifest | ConvertTo-Json -Depth 10
Write-Utf8WithoutBom -Path $latestFile -Content ($latestJson + "`n")

$winScp = Find-WinScpConsole
$pageant = Find-Pageant

if (-not (Test-Path -LiteralPath $PrivateKey -PathType Leaf)) {
    throw "Der private SSH-Schlüssel wurde nicht gefunden: $PrivateKey"
}

# Der Schlüssel wird zunächst verschlüsselt in Pageant registriert.
# Falls er noch nicht entsperrt ist, erscheint bei der ersten Verwendung
# durch WinSCP automatisch der Passphrase-Dialog.
Start-Process `
    -FilePath $pageant `
    -ArgumentList @('--encrypted', $PrivateKey) `
    -WindowStyle Hidden

Start-Sleep -Seconds 1

$winScpLog = Join-Path $releaseDirectory 'winscp-upload.log'
$commands = @(
    "open `"$WinScpSession`"",
    "cd `"$RemoteDirectory`"",
    "put `"$packageFile`" `"$packageName`"",
    "put `"$checksumFile`" `"$checksumName`"",
    "put `"$latestFile`" `"latest.json`"",
    'exit'
)

& $winScp "/log=$winScpLog" '/command' $commands
if ($LASTEXITCODE -ne 0) {
    throw "Der WinSCP-Upload ist fehlgeschlagen. Protokoll: $winScpLog"
}

$publicBaseUrl = $ManifestUrl.Substring(0, $ManifestUrl.LastIndexOf('/') + 1)
$remoteManifest = Invoke-RestMethod -Uri $ManifestUrl -Method Get -TimeoutSec 30
if ([string]$remoteManifest.latest -ne $Version) {
    throw "Der Updateserver meldet nicht die erwartete Version $Version."
}

$temporaryVerificationFile = Join-Path $env:TEMP "gps-portal-verification-$Version.zip"
try {
    Invoke-WebRequest -Uri ($publicBaseUrl + $packageName) -OutFile $temporaryVerificationFile -TimeoutSec 120
    $remoteSha256 = (Get-FileHash -LiteralPath $temporaryVerificationFile -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($remoteSha256 -ne $sha256) {
        throw 'Die Prüfsumme des öffentlich heruntergeladenen Pakets stimmt nicht.'
    }
}
finally {
    if (Test-Path -LiteralPath $temporaryVerificationFile) {
        Remove-Item -LiteralPath $temporaryVerificationFile -Force
    }
}

Write-Host ''
Write-Host 'GPS-Portal-Update erfolgreich veröffentlicht.' -ForegroundColor Green
Write-Host "Version:   $Version"
Write-Host "Build:     $Build"
Write-Host "Paket:     $packageFile"
Write-Host "SHA256:    $sha256"
Write-Host "Manifest:  $ManifestUrl"
