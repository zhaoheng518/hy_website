<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333;">
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
            <p style="margin:0 0 16px;">Dear <strong><?php echo $customer_name; ?></strong>,</p>
            <p style="margin:0 0 16px;">
              Thank you for reaching out to us! We have successfully received your inquiry and our team will review it shortly.
            </p>
            <?php if ($product_name !== ''): ?>
            <p style="margin:0 0 16px;">
              <strong>Product of interest:</strong> <?php echo $product_name; ?>
            </p>
            <?php endif; ?>
            <p style="margin:0 0 16px;">
              We typically respond within <strong>1–2 business days</strong>. If your request is urgent, please feel free to contact us directly.
            </p>

            <!-- Reference box -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;border-radius:4px;margin:24px 0 0;">
              <tr>
                <td style="padding:16px 20px;">
                  <p style="margin:0;font-size:13px;color:#555;">Inquiry Reference</p>
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
              Best regards,<br>
              <strong><?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?></strong>
              <?php if ($site_url !== ''): ?>
              &nbsp;·&nbsp;
              <a href="<?php echo htmlspecialchars($site_url, ENT_QUOTES, 'UTF-8'); ?>" style="color:#1a56db;text-decoration:none;">
                <?php echo htmlspecialchars($site_url, ENT_QUOTES, 'UTF-8'); ?>
              </a>
              <?php endif; ?>
            </p>
            <p style="margin:8px 0 0;font-size:11px;color:#bbb;">
              This is an automated confirmation email. Please do not reply directly to this message.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
