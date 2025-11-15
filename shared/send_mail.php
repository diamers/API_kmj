<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($to_email, $nama_user, $otp)
{
    $mail = new PHPMailer(true);

    $logo_path = __DIR__ . '/images/logo_kmj_maverick.png';
    $cid = 'logo_maverick'; 

    $body = <<<HTML
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Verifikasi Email - Tim Maverick</title>
        <style>
            body { background-color: #f3f4f6; font-family: Arial, sans-serif; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); overflow: hidden; }
            .header { background-color: #001333; text-align: center; padding: 25px; }
            .header img { width: 200px; margin-bottom: 10px; }
            .header h1 { color: #fff; font-size: 22px; margin: 0; }
            .content { padding: 30px; color: #333; line-height: 1.6; }
            .otp-box { text-align: center; margin: 25px 0; }
            .otp { background: #001333; color: #fff; font-size: 28px; font-weight: bold; letter-spacing: 4px; padding: 12px 24px; border-radius: 8px; display: inline-block; }
            .footer { background: #f1f5f9; text-align: center; padding: 15px; font-size: 13px; color: #666; }
            .social-icons img { width: 22px; margin: 0 6px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="cid:$cid" alt="Logo Tim Maverick">
                <h1>Verifikasi Email Anda</h1>
            </div>
            <div class="content">
                <p>Halo <strong>$nama_user</strong>,</p>
                <p>Terima kasih telah bergabung dengan <strong>Tim Maverick</strong>! Untuk menyelesaikan proses pendaftaran, gunakan kode verifikasi di bawah ini:</p>
                <div class="otp-box"><div class="otp">$otp</div></div>
                <p>Kode ini berlaku selama <strong>10 menit</strong>.</p>
                <p>Salam hangat,<br><strong>Tim Maverick</strong></p>
            </div>
            <div class="footer">
                <div class="social-icons">
                    <a href="https://instagram.com/maverickteam"><img src="https://cdn-icons-png.flaticon.com/512/2111/2111463.png"></a>
                    <a href="https://facebook.com/maverickteam"><img src="https://cdn-icons-png.flaticon.com/512/733/733547.png"></a>
                    <a href="https://x.com/maverickteam"><img src="https://cdn-icons-png.flaticon.com/512/733/733579.png"></a>
                </div>
                <p>&copy; 2025 Tim Maverick.<br>Email ini dikirim otomatis, mohon tidak membalas.</p>
            </div>
        </div>
    </body>
    </html>
    HTML;

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.zoho.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@maverickteam.my.id';
        $mail->Password = 's7UKTxzg6GQn'; // gunakan app password Zoho
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('noreply@maverickteam.my.id', 'Maverick Team');
        $mail->addAddress($to_email);
        $mail->addReplyTo('support@maverickteam.my.id', 'Support Maverick');

        // Tambahkan logo inline
        $mail->AddEmbeddedImage($logo_path, $cid, 'logo.png');

        $mail->isHTML(true);
        $mail->Subject = 'Kode Verifikasi Akun Anda - Tim Maverick';
        $mail->Body = $body;
        $mail->AltBody = "Halo $nama_user, kode verifikasi Anda adalah: $otp";

        $mail->send();
        return ['code' => 200, 'info' => 'Email verifikasi berhasil dikirim'];
    } catch (Exception $e) {
        return ['code' => 501, 'info' => 'Gagal mengirim email: ' . $mail->ErrorInfo];
    }
}
?>