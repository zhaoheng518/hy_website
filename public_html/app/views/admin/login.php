<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo htmlspecialchars($siteName ?? 'Stitch Tech', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo \App\Core\View::cacheBust('css/admin.css'); ?>">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        .login-split { display: flex; min-height: 100vh; }
        .login-brand {
            display: none; flex-direction: column; justify-content: flex-end; align-items: center;
            width: 50%; padding: 80px 48px; position: relative; overflow: hidden;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1a56db 100%);
            color: #fff;
        }
        .login-brand::before {
            content: ''; position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .login-brand-content { position: relative; z-index: 1; text-align: center; }
        .login-brand-logo { font-size: 36px; font-weight: 800; margin-bottom: 16px; letter-spacing: -0.5px; }
        .login-brand-slogan { font-size: 18px; opacity: 0.8; line-height: 1.6; max-width: 360px; margin: 0 auto; }
        .login-brand-divider { width: 48px; height: 3px; background: rgba(255,255,255,0.3); border-radius: 2px; margin: 24px auto; }
        .login-brand-sub { font-size: 14px; opacity: 0.5; margin-top: 8px; }

        .login-form-side {
            display: flex; align-items: center; justify-content: center;
            width: 50%; padding: 48px 32px; background: #ffffff;
        }
        .login-form-wrap { width: 100%; max-width: 400px; }
        .login-form-header { margin-bottom: 36px; }
        .login-form-header h1 { font-size: 28px; font-weight: 700; color: var(--c-text); margin-bottom: 8px; }
        .login-form-header p { font-size: 15px; color: var(--c-text-light); line-height: 1.5; }

        .login-field { margin-bottom: 20px; }
        .login-field label { display: block; font-size: 13px; font-weight: 600; color: var(--c-text); margin-bottom: 6px; }
        .login-field input {
            width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px;
            font-size: 15px; font-family: inherit; background: #fff; color: var(--c-text);
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .login-field input:focus {
            outline: none; border-color: var(--c-primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .login-field input:disabled { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }

        .login-alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .login-submit {
            display: block; width: 100%; padding: 12px 24px; border: none; border-radius: 8px;
            font-size: 15px; font-weight: 600; cursor: pointer; color: #fff;
            background: var(--c-primary); transition: background 0.15s, box-shadow 0.15s;
            box-shadow: 0 1px 3px rgba(37,99,235,0.25);
        }
        .login-submit:hover { background: var(--c-primary-dark); box-shadow: 0 4px 12px rgba(37,99,235,0.35); }
        .login-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .login-footer { margin-top: 32px; text-align: center; font-size: 13px; color: var(--c-text-light); }
        .login-footer a { color: var(--c-primary); text-decoration: none; }
        .login-footer a:hover { text-decoration: underline; }

        /* Module 13: CAPTCHA field */
        .login-captcha-row {
            display: flex; gap: 12px; align-items: flex-end; margin-bottom: 20px;
        }
        .login-captcha-question {
            flex: 0 0 auto; padding: 10px 14px; border: 1px solid #e5e7eb;
            border-radius: 8px; background: #f9fafb; font-size: 17px; font-weight: 700;
            color: var(--c-text); letter-spacing: 2px; white-space: nowrap;
            user-select: none; min-width: 90px; text-align: center;
        }
        .login-captcha-row .login-field { flex: 1; margin-bottom: 0; }
        .login-captcha-row .login-field label { display: block; }

        @media (min-width: 769px) {
            .login-brand { display: flex; }
        }
        @media (max-width: 768px) {
            .login-split { flex-direction: column; }
            .login-form-side { width: 100%; min-height: 100vh; }
        }
    </style>
</head>
<body>
<div class="login-split">
    <div class="login-brand">
        <div class="login-brand-content">
            <div class="login-brand-logo"><?php echo htmlspecialchars($siteName ?? 'Stitch Tech', ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="login-brand-divider"></div>
            <div class="login-brand-slogan">Professional B2B Solutions for Global Businesses</div>
            <div class="login-brand-sub">Enterprise-grade products &amp; services since 2010</div>
        </div>
    </div>
    <div class="login-form-side">
        <div class="login-form-wrap">
            <div class="login-form-header">
                <h1>Welcome Back</h1>
                <p>Sign in to access the admin dashboard</p>
            </div>

            <?php if (!empty($error)): ?>
            <div class="login-alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($lockedOut)): ?>
            <div class="login-alert">
                Account temporarily locked. Please try again in <?php echo ceil($lockoutRemaining / 60); ?> minutes.
            </div>
            <?php endif; ?>

            <form method="POST" action="/admin/login">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="login-field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required
                           autocomplete="username" autofocus placeholder="Enter your username"
                           <?php echo !empty($lockedOut) ? 'disabled' : ''; ?>>
                </div>
                <div class="login-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           autocomplete="current-password" placeholder="Enter your password"
                           <?php echo !empty($lockedOut) ? 'disabled' : ''; ?>>
                </div>
                <?php if (!empty($captchaEnabled)): ?>
                <!-- Module 13: Math CAPTCHA — generated server-side, answer stored in session -->
                <div class="login-captcha-row">
                    <div class="login-captcha-question" aria-label="CAPTCHA equation" title="Solve this equation">
                        <?php echo htmlspecialchars($captchaQuestion ?? '', ENT_QUOTES, 'UTF-8'); ?> = ?
                    </div>
                    <div class="login-field">
                        <label for="captcha">Answer</label>
                        <input type="number" id="captcha" name="captcha" required
                               autocomplete="off" placeholder="Enter result"
                               <?php echo !empty($lockedOut) ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="login-submit"
                        <?php echo !empty($lockedOut) ? 'disabled' : ''; ?>>
                    Sign In
                </button>
            </form>

            <div class="login-footer">
                <a href="/">&#8592; Back to website</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
