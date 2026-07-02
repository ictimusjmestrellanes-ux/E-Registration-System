try {
    $json = '{"Source":"browser"}'
    $json | Set-Content -Path 'fingerprint-bridge\capture.json' -Encoding utf8
    $response = Invoke-RestMethod -Method Post -Uri 'http://127.0.0.1:38655/api/capture' -Body $json -ContentType 'application/json' -TimeoutSec 20
    $response | ConvertTo-Json -Compress
} catch {
    Write-Host "ERROR: $($_.Exception.Message)"
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        Write-Host $reader.ReadToEnd()
    }
}
