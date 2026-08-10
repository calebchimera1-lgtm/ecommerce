<?php /** @var string $name @var array $items @var string $cartUrl */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>You left something behind</title>
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
                                You left <?= count($items) === 1 ? 'an item' : count($items) . ' items' ?> in your
                                cart. It's still saved and ready whenever you are.
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;text-align:left;">
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td style="padding:8px 0;border-bottom:1px solid #2a2a2a;color:#f8f7f4;font-size:13px;">
                                            <?= htmlspecialchars((string) $item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($item['attribute_value'])): ?>
                                                <span style="color:#8a8a8a;"> &mdash; <?= htmlspecialchars((string) $item['attribute_value'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <span style="color:#8a8a8a;"> &times; <?= (int) $item['quantity'] ?></span>
                                        </td>
                                        <td style="padding:8px 0;border-bottom:1px solid #2a2a2a;color:#f8f7f4;font-size:13px;text-align:right;">
                                            <?= number_format((float) $item['price'] * (int) $item['quantity'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                            <p style="margin:32px 0;">
                                <a href="<?= htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8') ?>"
                                   style="background:#c9a24b;color:#0b0b0c;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:bold;letter-spacing:1px;">
                                    Return to Your Cart
                                </a>
                            </p>
                            <p style="color:#8a8a8a;font-size:12px;">
                                If the button above does not work, copy and paste this link into your browser:<br>
                                <?= htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
