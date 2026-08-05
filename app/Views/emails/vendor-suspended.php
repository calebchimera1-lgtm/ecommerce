<?php /** @var string $name @var string $storeName */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your vendor account has been suspended</title>
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
                                Your vendor account, <strong><?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') ?></strong>,
                                has been suspended and you will not be able to sign in to your vendor portal
                                until it is reinstated.
                            </p>
                            <p style="color:#8a8a8a;font-size:12px;">
                                Please contact our support team if you have questions about this decision.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
