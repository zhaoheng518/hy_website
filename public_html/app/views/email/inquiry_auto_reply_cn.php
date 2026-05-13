<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,'Microsoft YaHei',sans-serif;color:#333;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:32px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">

        <!-- Header -->
        <tr>
          <td style="background:#1a56db;padding:28px 32px;text-align:center;">
            <h1 style="margin:0;font-size:22px;color:#fff;font-weight:600;">
              <?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?>
            </h1>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px 32px 24px;">
            <p style="margin:0 0 16px;">尊敬的 <strong><?php echo $customer_name; ?></strong>，</p>
            <p style="margin:0 0 16px;">
              感谢您向我们发送询盘！我们已成功收到您的消息，团队将尽快进行处理。
            </p>
            <?php if ($product_name !== ''): ?>
            <p style="margin:0 0 16px;">
              <strong>您询盘的产品：</strong><?php echo $product_name; ?>
            </p>
            <?php endif; ?>
            <p style="margin:0 0 16px;">
              我们通常在 <strong>1–2 个工作日内</strong>回复。如需紧急处理，欢迎直接与我们联系。
            </p>

            <!-- Reference box -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;border-radius:4px;margin:24px 0 0;">
              <tr>
                <td style="padding:16px 20px;">
                  <p style="margin:0;font-size:13px;color:#555;">询盘编号</p>
                  <p style="margin:4px 0 0;font-size:15px;font-weight:600;color:#1a56db;font-family:monospace;">
                    <?php echo htmlspecialchars($inquiry_id, ENT_QUOTES, 'UTF-8'); ?>
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:16px 32px 28px;border-top:1px solid #eee;">
            <p style="margin:0;font-size:13px;color:#888;">
              此致，<br>
              <strong><?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?></strong>
              <?php if ($site_url !== ''): ?>
              &nbsp;·&nbsp;
              <a href="<?php echo htmlspecialchars($site_url, ENT_QUOTES, 'UTF-8'); ?>" style="color:#1a56db;text-decoration:none;">
                <?php echo htmlspecialchars($site_url, ENT_QUOTES, 'UTF-8'); ?>
              </a>
              <?php endif; ?>
            </p>
            <p style="margin:8px 0 0;font-size:11px;color:#bbb;">
              本邮件为系统自动发送，请勿直接回复。
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
