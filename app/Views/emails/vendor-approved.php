<?php /** @var string $name @var string $storeName @var string $loginUrl */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your vendor application was approved</title>
</head>
<body style="margin:0;padding:0;background:#0b0b0c;font-family:Georgia,serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0c;padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#141414;border:1px solid #c9a24b;border-radius:12px;">
                    <tr>
                        <td style="padding:32px;text-align:center;">
                            <div style="color:#c9a24b;letter-spacing:4px;text-transform:uppercase;font-size:18px;margin-bottom:24px;">Kymera Collection</div>
                            <p style="color:#f8f7f4;font-size:16px;">Hello <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>,</p>
                            <p style="color:#cfcfcf;font-size:14px;line-height:1.6;">
                                Congratulations - <strong><?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') ?></strong>
                                has been approved to sell on Kymera Collection. You can now sign in to your
                                vendor portal.
                            </p>
                            <p style="margin:32px 0;">
                                <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>"
                                   style="background:#c9a24b;color:#0b0b0c;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:bold;letter-spacing:1px;">
                                    Sign In to Vendor Portal
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
