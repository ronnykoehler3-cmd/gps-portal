[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$publishScript = 'D:\GitHub\gps-portal\scripts\Publish-GpsPortalUpdate.ps1'
$manifestUrl = 'http://update.tk-kundendienst.de/GPS-Portal/latest.json'

Clear-Host
Write-Host '============================================================' -ForegroundColor DarkCyan
Write-Host ' GPS-Portal – Update erstellen und hochladen' -ForegroundColor Cyan
Write-Host '============================================================' -ForegroundColor DarkCyan
Write-Host ''

if (-not (Test-Path -LiteralPath $publishScript -PathType Leaf)) {
    Write-Host 'Das Veröffentlichungsskript wurde nicht gefunden:' -ForegroundColor Red
    Write-Host $publishScript -ForegroundColor Yellow
    Write-Host ''
    Read-Host 'Zum Schließen Enter drücken'
    return
}

Write-Host 'Der aktuelle Stand wird vom Updateserver gelesen ...' -ForegroundColor Cyan

try {
    $currentManifest = Invoke-RestMethod `
        -Uri $manifestUrl `
        -Method Get `
        -TimeoutSec 30
}
catch {
    Write-Host ''
    Write-Host 'Der Updateserver konnte nicht gelesen werden:' -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Yellow
    Write-Host ''
    Read-Host 'Zum Schließen Enter drücken'
    return
}

$currentVersion = ([string]$currentManifest.latest).Trim()
$currentBuild = ([string]$currentManifest.build).Trim()

if ($currentVersion -notmatch '^(\d+)\.(\d+)\.(\d+)$') {
    Write-Host ''
    Write-Host "Die aktuelle Serverversion ist ungültig: $currentVersion" -ForegroundColor Red
    Read-Host 'Zum Schließen Enter drücken'
    return
}

$major = [int]$Matches[1]
$minor = [int]$Matches[2]
$patch = [int]$Matches[3] + 1
$version = "$major.$minor.$patch"

$today = Get-Date -Format 'yyyyMMdd'
$buildNumber = 1

if ($currentBuild -match "^$today-(\d{3})$") {
    $buildNumber = [int]$Matches[1] + 1
}

$build = $today + '-' + ('{0:D3}' -f $buildNumber)

Write-Host ''
Write-Host "Aktuelle Serverversion: $currentVersion" -ForegroundColor Gray
Write-Host "Aktueller Serverbuild:   $currentBuild" -ForegroundColor Gray
Write-Host "Neue Version:            $version" -ForegroundColor Green
Write-Host "Neue Buildnummer:        $build" -ForegroundColor Green
Write-Host ''

do {
    $description = (Read-Host 'Kurze Beschreibung des Updates').Trim()
    if ($description -eq '') {
        Write-Host 'Bitte eine Beschreibung eingeben.' -ForegroundColor Yellow
    }
} while ($description -eq '')

Write-Host ''
Write-Host 'Zusammenfassung' -ForegroundColor Cyan
Write-Host "Version:      $version"
Write-Host "Build:        $build"
Write-Host "Beschreibung: $description"
Write-Host ''

$confirmation = (Read-Host 'Update jetzt erstellen und hochladen? (J/N)').Trim()
if ($confirmation -notmatch '^(j|ja)$') {
    Write-Host ''
    Write-Host 'Der Vorgang wurde abgebrochen.' -ForegroundColor Yellow
    Read-Host 'Zum Schließen Enter drücken'
    return
}

try {
    & $publishScript `
        -Version $version `
        -Build $build `
        -Description $description

    Write-Host ''
    Write-Host 'Das GPS-Portal-Update wurde erfolgreich veröffentlicht.' -ForegroundColor Green
}
catch {
    Write-Host ''
    Write-Host 'Die Veröffentlichung ist fehlgeschlagen:' -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Yellow
}

Write-Host ''
Read-Host 'Zum Schließen Enter drücken'
