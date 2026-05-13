<?php
/**
 * Shared inquiry form (contact page + optional product embed).
 * Expects: $lang, $formData, $formMessage, $formMessageType, $captcha, $useTurnstile, $turnstileSiteKey, $inquiryFormAction
 * Optional: $productSlugPrefill (hidden product_slug)
 */
$useTurnstile = !empty($useTurnstile ?? false);
$turnstileSiteKey = isset($turnstileSiteKey) ? trim((string) $turnstileSiteKey) : '';
$inquiryFormAction = isset($inquiryFormAction) ? (string) $inquiryFormAction : ('/' . rawurlencode((string) ($lang ?? 'en')) . '/contact/submit');
$captcha = isset($captcha) && is_array($captcha) ? $captcha : ['question' => '', 'hash' => ''];
$formData = isset($formData) && is_array($formData) ? $formData : [];
$formMessage = $formMessage ?? null;
$formMessageType = $formMessageType ?? '';
$slugPrefill = isset($productSlugPrefill) ? (string) $productSlugPrefill : '';
$langCode = (string) ($lang ?? 'en');
?>
                <form id="inquiry-form" method="POST" action="<?php echo htmlspecialchars($inquiryFormAction, ENT_QUOTES, 'UTF-8'); ?>" class="inquiry-form">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\App\Core\Auth::generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!$useTurnstile && !empty($captcha['hash'])): ?>
                    <input type="hidden" name="captcha_hash" value="<?php echo htmlspecialchars((string) $captcha['hash'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="source_url" id="source_url" value="">
                    <input type="hidden" name="utm_source"       id="utm_source"       value="">
                    <input type="hidden" name="utm_medium"       id="utm_medium"       value="">
                    <input type="hidden" name="utm_campaign"     id="utm_campaign"     value="">
                    <input type="hidden" name="utm_term"         id="utm_term"         value="">
                    <input type="hidden" name="utm_content"      id="utm_content"      value="">
                    <input type="hidden" name="attr_landing_page" id="attr_landing_page" value="">
                    <input type="hidden" name="attr_referrer"    id="attr_referrer"    value="">

                    <div style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;" aria-hidden="true">
                        <label for="website_url">Website</label>
                        <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="name"><?php echo $langCode === 'cn' ? '姓名 *' : ($langCode === 'es' ? 'Nombre *' : 'Name *'); ?></label>
                        <input type="text" id="name" name="name" required maxlength="100"
                               value="<?php echo htmlspecialchars((string) ($formData['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email"><?php echo $langCode === 'cn' ? '邮箱 *' : ($langCode === 'es' ? 'Correo Electrónico *' : 'Email *'); ?></label>
                        <input type="email" id="email" name="email" required maxlength="200"
                               value="<?php echo htmlspecialchars((string) ($formData['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="company"><?php echo $langCode === 'cn' ? '公司名称' : ($langCode === 'es' ? 'Nombre de la Empresa' : 'Company Name'); ?></label>
                        <input type="text" id="company" name="company" maxlength="200"
                               value="<?php echo htmlspecialchars((string) ($formData['company'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone"><?php echo $langCode === 'cn' ? 'WhatsApp/电话' : ($langCode === 'es' ? 'WhatsApp/Teléfono' : 'WhatsApp/Phone'); ?></label>
                        <input type="tel" id="phone" name="phone" maxlength="50"
                               value="<?php echo htmlspecialchars((string) ($formData['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="country"><?php echo $langCode === 'cn' ? '国家/地区' : ($langCode === 'es' ? 'País' : 'Country'); ?></label>
                        <input type="text" id="country" name="country" maxlength="100"
                               value="<?php echo htmlspecialchars((string) ($formData['country'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <?php
                    $hiddenSlug = (string) ($formData['product_slug'] ?? '');
                    if ($hiddenSlug === '' && $slugPrefill !== '') {
                        $hiddenSlug = $slugPrefill;
                    }
                    ?>
                    <input type="hidden" name="product_slug" id="product_slug" value="<?php echo htmlspecialchars($hiddenSlug, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-group">
                        <label for="product_source"><?php echo $langCode === 'cn' ? '感兴趣的产品' : ($langCode === 'es' ? 'Producto de Interés' : 'Product of Interest'); ?></label>
                        <input type="text" id="product_source" name="product_source" maxlength="200"
                               value="<?php echo htmlspecialchars((string) ($formData['product_source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="message"><?php echo $langCode === 'cn' ? '留言 *' : ($langCode === 'es' ? 'Mensaje *' : 'Message *'); ?></label>
                        <textarea id="message" name="message" rows="5" required maxlength="2000"><?php echo htmlspecialchars((string) ($formData['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="form-group form-checkbox" style="margin-top:8px;">
                        <label style="font-weight:normal;display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="newsletter_opt_out" value="1" style="margin-top:4px;" <?php echo !empty($formData['newsletter_opt_out']) ? 'checked' : ''; ?>>
                            <span><?php echo $langCode === 'cn' ? '不订阅产品与博客更新邮件（询盘仍会正常提交）' : ($langCode === 'es' ? 'No deseo recibir el boletín de novedades (la consulta se enviará igualmente)' : 'Do not subscribe me to product/blog update emails (inquiry will still be sent)'); ?></span>
                        </label>
                    </div>

                    <?php if ($useTurnstile && $turnstileSiteKey !== ''): ?>
                    <div class="form-group turnstile-wrap">
                        <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light"></div>
                    </div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    <?php else: ?>
                    <div class="form-group">
                        <label for="captcha"><?php echo htmlspecialchars((string) ($captcha['question'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> *</label>
                        <input type="text" id="captcha" name="captcha" required maxlength="10" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo $langCode === 'cn' ? '提交询盘' : ($langCode === 'es' ? 'Enviar Consulta' : 'Send Inquiry'); ?>
                    </button>
                </form>
                <script>
                (function() {
                    var u = window.location && window.location.href ? window.location.href : '';
                    var el = document.getElementById('source_url');
                    if (el) el.value = u;
                })();
                </script>
