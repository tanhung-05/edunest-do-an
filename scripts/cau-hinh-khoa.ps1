# Điền khóa và mật khẩu vào .env (người dùng tự nhập, script không in ra màn hình).
# Chạy: powershell -ExecutionPolicy Bypass -File E:\Nam4\MNM\edunest\scripts\cau-hinh-khoa.ps1
# Để trống một câu hỏi (bấm Enter) thì giữ nguyên giá trị cũ.

$envPath = Join-Path $PSScriptRoot '..\.env'
$lines = [System.Collections.Generic.List[string]](Get-Content $envPath -Encoding UTF8)

function Set-EnvValue([string]$key, [string]$value) {
    if ([string]::IsNullOrWhiteSpace($value)) { return }
    $value = $value.Trim()
    if ($value -match '\s') { $value = '"' + $value + '"' }
    $idx = -1
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match "^#?\s*$key=") { $idx = $i; break }
    }
    if ($idx -ge 0) { $lines[$idx] = "$key=$value" } else { $lines.Add("$key=$value") }
}

function Read-Secret([string]$prompt) {
    $s = Read-Host $prompt -AsSecureString
    return [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))
}

Write-Host '--- Gmail (mật khẩu ứng dụng 16 ký tự) ---'
$mail = Read-Secret 'Mật khẩu ứng dụng Gmail'
Set-EnvValue 'MAIL_PASSWORD' ($mail -replace '\s', '')

Write-Host '--- MoMo môi trường thử (developers.momo.vn) ---'
Set-EnvValue 'MOMO_PARTNER_CODE' (Read-Host 'Partner Code')
Set-EnvValue 'MOMO_ACCESS_KEY' (Read-Host 'Access Key')
Set-EnvValue 'MOMO_SECRET_KEY' (Read-Secret 'Secret Key')

Write-Host '--- VietQR (tài khoản nhận tiền) ---'
Write-Host 'Mã BIN: 970436 Vietcombank, 970422 MB, 970418 BIDV, 970415 VietinBank, 970407 Techcombank, 970405 Agribank, 970416 ACB, 970432 VPBank, 970423 TPBank'
Set-EnvValue 'BANK_BIN' (Read-Host 'Mã BIN ngân hàng (6 số)')
Set-EnvValue 'BANK_SIM_BANK' (Read-Host 'Tên ngân hàng hiển thị')
Set-EnvValue 'BANK_SIM_ACCOUNT' (Read-Host 'Số tài khoản')
Set-EnvValue 'BANK_SIM_ACCOUNT_NAME' (Read-Host 'Tên chủ tài khoản (không dấu, viết hoa)')

$utf8 = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllLines((Resolve-Path $envPath), $lines, $utf8)
Write-Host 'Đã lưu vào .env. Nhắn Claude để gửi email thử và thử giao dịch MoMo.'
