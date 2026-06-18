<!DOCTYPE html>
<html>
<head>
    <title>Reset Password PinjemLur</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 0.85em; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
    </style>
</head>
<body>
    <h2>Halo,</h2>
    <p>Kami menerima permintaan untuk mereset kata sandi (password) akun Anda di PinjemLur.</p>
    <p>Silakan klik tombol di bawah ini untuk mengatur kata sandi baru Anda:</p>
    
    <a href="{{ $resetLink }}" class="btn">Reset Password</a>
    
    <p>Tautan ini hanya berlaku selama 60 menit.</p>
    <p>Jika Anda tidak pernah merasa meminta reset password, Anda dapat mengabaikan email ini dan akun Anda akan tetap aman.</p>
    
    <div class="footer">
        <p>Terima kasih,<br>Tim PinjemLur</p>
    </div>
</body>
</html>
