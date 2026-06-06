param(
  [string]$ApiBase = "http://127.0.0.1:8000",
  [Parameter(Mandatory = $true)][string]$FarmerPhone,
  [Parameter(Mandatory = $true)][string]$FarmerPassword,
  [Parameter(Mandatory = $true)][string]$SupporterPhone,
  [Parameter(Mandatory = $true)][string]$SupporterPassword,
  [int]$ReportId = 0,
  [ValidateSet("low","medium","high","critical")][string]$ConfirmSeverity = "high",
  [switch]$DryRun
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Login {
  param(
    [string]$Base,
    [string]$Phone,
    [string]$Password
  )

  $payload = @{
    phone = $Phone
    password = $Password
  } | ConvertTo-Json

  $res = Invoke-RestMethod -Method Post -Uri "$Base/api/v1/auth/login" -ContentType "application/json" -Body $payload
  if (-not $res.token) {
    throw "Login succeeded but token missing for phone $Phone"
  }

  return @{
    token = [string]$res.token
    user = $res.user
  }
}

function Get-AuthHeaders {
  param([string]$Token)
  return @{
    "Accept" = "application/json"
    "Authorization" = "Bearer $Token"
    "Content-Type" = "application/json"
  }
}

Write-Host "1) Logging in farmer..."
$farmer = Login -Base $ApiBase -Phone $FarmerPhone -Password $FarmerPassword
$farmerHeaders = Get-AuthHeaders -Token $farmer.token
Write-Host "   Farmer login OK"

Write-Host "2) Logging in supporter..."
$supporter = Login -Base $ApiBase -Phone $SupporterPhone -Password $SupporterPassword
$supporterHeaders = Get-AuthHeaders -Token $supporter.token
Write-Host "   Supporter login OK"

if ($ReportId -le 0) {
  Write-Host "3) Selecting a reviewing report for farmer..."
  $reportsRes = Invoke-RestMethod -Method Get -Uri "$ApiBase/api/v1/disease-reports?per_page=100" -Headers $farmerHeaders
  $reports = @($reportsRes.data)
  $candidate = $reports | Where-Object { $_.status -eq "reviewing" } | Select-Object -First 1
  if (-not $candidate) {
    throw "No reviewing disease report found for this farmer."
  }
  $ReportId = [int]$candidate.id
} else {
  Write-Host "3) Using provided report id: $ReportId"
  $candidateRes = Invoke-RestMethod -Method Get -Uri "$ApiBase/api/v1/disease-reports/$ReportId" -Headers $farmerHeaders
  $candidate = $candidateRes.data
}

if (-not $candidate) {
  throw "Could not load disease report candidate."
}

$diseaseName = [string]$candidate.disease_name
if ([string]::IsNullOrWhiteSpace($diseaseName) -or $diseaseName -eq "pending_analysis") {
  $diseaseName = "manual_review_confirmed"
}

Write-Host "   Target report id=$ReportId disease_name=$diseaseName status=$($candidate.status) severity=$($candidate.severity)"

if ($DryRun) {
  Write-Host "4) Dry run enabled. Skipping supporter verify and alert check."
  exit 0
}

Write-Host "4) Supporter confirming report..."
$verifyPayload = @{
  disease_name = $diseaseName
  severity = $ConfirmSeverity
  status = "confirmed"
  description = "Confirmed during role-flow check."
} | ConvertTo-Json

$verifyRes = Invoke-RestMethod -Method Put -Uri "$ApiBase/api/v1/disease-reports/$ReportId/verify" -Headers $supporterHeaders -Body $verifyPayload
$verified = $verifyRes.data
Write-Host "   Verify response status=$($verified.status) severity=$($verified.severity)"

Start-Sleep -Seconds 1

Write-Host "5) Checking farmer alerts for this report..."
$alertsRes = Invoke-RestMethod -Method Get -Uri "$ApiBase/api/v1/alerts?per_page=100" -Headers $farmerHeaders
$alerts = @($alertsRes.data)
$matching = @($alerts | Where-Object { [int]$_.disease_report_id -eq $ReportId })

if ($matching.Count -eq 0) {
  throw "No alert found for disease_report_id=$ReportId"
}

$latest = $matching | Select-Object -First 1
Write-Host "   Alert found id=$($latest.id) status=$($latest.status) severity=$($latest.severity)"

Write-Host ""
Write-Host "Role flow check PASSED"
