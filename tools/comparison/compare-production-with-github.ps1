$ErrorActionPreference = "Stop"

$GitHubRoot = "D:\GitHub\gps-portal\public"
$ProductionManifest = "D:\Projekte\GPS-Portal\Vergleich\gps-production-comparison\production-manifest.tsv"
$OutputRoot = "D:\Projekte\GPS-Portal\Vergleich\Ergebnis"

if (-not (Test-Path $GitHubRoot)) {
    throw "GitHub-Ordner fehlt: $GitHubRoot"
}

if (-not (Test-Path $ProductionManifest)) {
    throw "Produktionsmanifest fehlt: $ProductionManifest"
}

Remove-Item $OutputRoot -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path $OutputRoot -Force | Out-Null

$ExcludedPaths = @(
    "configuration.php"
)

$ExcludedPrefixes = @(
    "cache/",
    "administrator/cache/",
    "tmp/",
    "logs/",
    "log/",
    "storage/",
    "images/",
    "media/cache/",
    "administrator/logs/"
)

$ExcludedExtensions = @(
    ".log",
    ".tmp",
    ".bak",
    ".old",
    ".zip",
    ".tar",
    ".gz"
)

function Get-RelativePath {
    param(
        [string]$FullPath,
        [string]$RootPath
    )

    return $FullPath.Substring($RootPath.Length).TrimStart("\", "/").Replace("\", "/")
}

function Test-Excluded {
    param(
        [string]$RelativePath
    )

    if ($ExcludedPaths -contains $RelativePath) {
        return $true
    }

    foreach ($Prefix in $ExcludedPrefixes) {
        if ($RelativePath.StartsWith($Prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $true
        }
    }

    $Extension = [System.IO.Path]::GetExtension($RelativePath).ToLowerInvariant()

    if ($ExcludedExtensions -contains $Extension) {
        return $true
    }

    return $false
}

Write-Host ""
Write-Host "GitHub-Dateien werden geprüft ..."

$GitHubFiles = Get-ChildItem `
    -Path $GitHubRoot `
    -Recurse `
    -File |
ForEach-Object {
    $RelativePath = Get-RelativePath `
        -FullPath $_.FullName `
        -RootPath $GitHubRoot

    if (-not (Test-Excluded -RelativePath $RelativePath)) {
        $Hash = Get-FileHash `
            -Path $_.FullName `
            -Algorithm SHA256

        [PSCustomObject]@{
            Path   = $RelativePath
            Size   = [int64]$_.Length
            SHA256 = $Hash.Hash.ToLowerInvariant()
        }
    }
}

Write-Host "Produktionsmanifest wird geladen ..."

$ProductionFiles = Get-Content `
    -Path $ProductionManifest `
    -Encoding UTF8 |
ForEach-Object {
    $Parts = $_ -split "`t", 3

    if ($Parts.Count -eq 3) {
        [PSCustomObject]@{
            Path   = $Parts[0].Replace("\", "/")
            Size   = [int64]$Parts[1]
            SHA256 = $Parts[2].ToLowerInvariant()
        }
    }
}

$GitHubIndex = @{}
foreach ($File in $GitHubFiles) {
    $GitHubIndex[$File.Path] = $File
}

$ProductionIndex = @{}
foreach ($File in $ProductionFiles) {
    $ProductionIndex[$File.Path] = $File
}

$AllPaths = @(
    $GitHubIndex.Keys
    $ProductionIndex.Keys
) | Sort-Object -Unique

$Comparison = foreach ($Path in $AllPaths) {
    $GitHubFile = $GitHubIndex[$Path]
    $ProductionFile = $ProductionIndex[$Path]

    if ($null -eq $ProductionFile) {
        $Status = "Nur GitHub"
    }
    elseif ($null -eq $GitHubFile) {
        $Status = "Nur Produktiv"
    }
    elseif ($GitHubFile.SHA256 -eq $ProductionFile.SHA256) {
        $Status = "Identisch"
    }
    else {
        $Status = "Unterschiedlich"
    }

    [PSCustomObject]@{
        Status            = $Status
        Pfad              = $Path
        ProduktivGroesse  = if ($ProductionFile) { $ProductionFile.Size } else { $null }
        GitHubGroesse     = if ($GitHubFile) { $GitHubFile.Size } else { $null }
        ProduktivSHA256   = if ($ProductionFile) { $ProductionFile.SHA256 } else { "" }
        GitHubSHA256      = if ($GitHubFile) { $GitHubFile.SHA256 } else { "" }
    }
}

$Comparison |
Export-Csv `
    -Path "$OutputRoot\production-vs-github.csv" `
    -NoTypeInformation `
    -Encoding UTF8 `
    -Delimiter ";"

$Comparison |
Where-Object Status -eq "Identisch" |
Export-Csv `
    -Path "$OutputRoot\identisch.csv" `
    -NoTypeInformation `
    -Encoding UTF8 `
    -Delimiter ";"

$Comparison |
Where-Object Status -eq "Unterschiedlich" |
Export-Csv `
    -Path "$OutputRoot\unterschiedlich.csv" `
    -NoTypeInformation `
    -Encoding UTF8 `
    -Delimiter ";"

$Comparison |
Where-Object Status -eq "Nur Produktiv" |
Export-Csv `
    -Path "$OutputRoot\nur-produktiv.csv" `
    -NoTypeInformation `
    -Encoding UTF8 `
    -Delimiter ";"

$Comparison |
Where-Object Status -eq "Nur GitHub" |
Export-Csv `
    -Path "$OutputRoot\nur-github.csv" `
    -NoTypeInformation `
    -Encoding UTF8 `
    -Delimiter ";"

$Identisch = @($Comparison | Where-Object Status -eq "Identisch").Count
$Unterschiedlich = @($Comparison | Where-Object Status -eq "Unterschiedlich").Count
$NurProduktiv = @($Comparison | Where-Object Status -eq "Nur Produktiv").Count
$NurGitHub = @($Comparison | Where-Object Status -eq "Nur GitHub").Count

@"
GPS-Portal Vergleich Produktiv gegen GitHub
===========================================

Produktivdateien:    $($ProductionFiles.Count)
GitHub-Dateien:      $($GitHubFiles.Count)

Identisch:           $Identisch
Unterschiedlich:     $Unterschiedlich
Nur Produktiv:       $NurProduktiv
Nur GitHub:          $NurGitHub

Ergebnisordner:
$OutputRoot
"@ | Set-Content `
    -Path "$OutputRoot\zusammenfassung.txt" `
    -Encoding UTF8

Write-Host ""
Write-Host "============================================="
Write-Host "Vergleich abgeschlossen"
Write-Host "============================================="
Write-Host "Identisch:       $Identisch"
Write-Host "Unterschiedlich: $Unterschiedlich"
Write-Host "Nur Produktiv:   $NurProduktiv"
Write-Host "Nur GitHub:      $NurGitHub"
Write-Host ""
Write-Host "Ergebnis:"
Write-Host $OutputRoot
