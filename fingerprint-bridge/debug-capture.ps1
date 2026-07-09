try { 
} catch { 
Write-Host 'ERROR:' .Exception.Message 
if (.Exception.Response) { 
 = New-Object System.IO.StreamReader(.Exception.Response.GetResponseStream()) 
Write-Host .ReadToEnd() 
} 
