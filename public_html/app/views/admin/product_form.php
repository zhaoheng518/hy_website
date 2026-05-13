<?php $pageTitle = $isEdit ? '编辑产品' : '添加产品'; $activeMenu = 'products'; ?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="panel">
    <div class="panel-header">
        <h2><?php echo $isEdit ? '编辑产品' : '添加新产品'; ?>
            <span class="lang-badge<?php echo $isDefaultLang ? ' lang-badge-default' : ''; ?>">
                <?php echo strtoupper($editLang); ?>
                <?php if ($isDefaultLang): ?>(主语言)<?php endif; ?>
            </span>
        </h2>
        <a href="/admin/products?lang=<?php echo $editLang; ?>" class="btn btn-sm">&larr; 返回产品列表</a>
    </div>

    <div class="lang-switcher-bar">
        <div class="lang-switcher-tabs">
            <?php
            $formSlug = $product['slug'] ?? '';
            $formAction = $isEdit
                ? '/admin/products/edit/' . htmlspecialchars($formSlug, ENT_QUOTES, 'UTF-8')
                : '/admin/products/create';
            ?>
            <?php foreach ($supportedLangs as $l): ?>
            <a href="<?php echo $formAction; ?>?lang=<?php echo $l; ?>"
               class="lang-switcher-tab<?php echo $l === $editLang ? ' active' : ''; ?><?php echo $l === 'en' ? ' lang-default' : ''; ?>">
                <?php echo strtoupper($l); ?>
                <?php if ($l === 'en'): ?><span class="default-tag">Default</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$isDefaultLang): ?>
        <button type="button" class="btn btn-magic" id="btn-translate-all" onclick="translateAllFromEN()">
            ✨ Translate All from English
        </button>
        <?php endif; ?>
    </div>

    <?php if (!$isDefaultLang && !$isEdit): ?>
    <div class="alert" style="background:var(--c-warning-light);color:var(--c-warning);margin-bottom:16px;">
        建议先在主语言 (EN) 创建产品，再切换到其他语言编辑翻译内容。
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo $formAction; ?>?lang=<?php echo $editLang; ?>" enctype="multipart/form-data"
          class="admin-form product-edit-form" onsubmit="return prepareProductSubmit();">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="edit_lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="images_json" id="images-json" value="">
        <input type="hidden" name="specs_json" id="specs-json" value="">
        <input type="hidden" name="faqs_json" id="faqs-json" value="">
        <input type="hidden" id="en-product-data" value="<?php echo htmlspecialchars(json_encode($enProduct), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" id="current-lang" value="<?php echo htmlspecialchars($editLang, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!$isDefaultLang): ?>
        <input type="hidden" name="product_status" value="<?php echo htmlspecialchars(\App\Core\ProductPublishState::normalize($product['status'] ?? \App\Core\ProductPublishState::STATUS_PUBLISHED), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>

        <div class="form-section">
            <h3 class="form-section-title">基本信息</h3>

            <div class="form-row">
                <div class="form-group form-group-2 field-localized">
                    <label for="name">产品名称 <span class="field-tag field-tag-local">本地化</span></label>
                    <div class="field-with-translate">
                        <input type="text" id="name" name="name"
                               value="<?php echo htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               required maxlength="200">
                        <?php if (!$isDefaultLang): ?>
                        <button type="button" class="btn-translate" onclick="translateField('name')" title="从英文自动翻译">✨</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group form-group-1 field-global">
                    <label for="slug">URL别名 <span class="field-tag field-tag-global">全局</span> <small>(留空自动生成)</small></label>
                    <?php if ($isDefaultLang): ?>
                    <input type="text" id="slug" name="slug"
                           value="<?php echo htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           maxlength="200" pattern="[a-z0-9\-]*">
                    <?php else: ?>
                    <input type="text" id="slug-display"
                           value="<?php echo htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           disabled class="field-disabled">
                    <input type="hidden" id="slug" name="slug"
                           value="<?php echo htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="field-sync-notice">🔒 此字段与主语言同步，请在 EN 界面修改</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group field-global">
                <label for="category_id">所属分类 <span class="field-tag field-tag-global">全局</span></label>
                <?php if ($isDefaultLang): ?>
                <select id="category_id" name="category_id">
                    <option value="">-- 无分类 --</option>
                    <?php foreach ($categories ?? [] as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo ($product['category_id'] ?? '') === ($cat['slug'] ?? '') ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" value="<?php echo htmlspecialchars($product['category_id'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>"
                       disabled class="field-disabled">
                <input type="hidden" id="category_id" name="category_id"
                       value="<?php echo htmlspecialchars($product['category_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="field-sync-notice">🔒 此字段与主语言同步，请在 EN 界面修改</div>
                <?php endif; ?>
            </div>

            <?php if ($isDefaultLang): ?>
            <div class="form-group field-global">
                <label for="product_status">发布状态 <span class="field-tag field-tag-global">全局</span></label>
                <select id="product_status" name="product_status">
                    <?php
                    $curStatus = \App\Core\ProductPublishState::normalize($product['status'] ?? \App\Core\ProductPublishState::STATUS_PUBLISHED);
                    ?>
                    <option value="<?php echo htmlspecialchars(\App\Core\ProductPublishState::STATUS_PUBLISHED, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $curStatus === \App\Core\ProductPublishState::STATUS_PUBLISHED ? ' selected' : ''; ?>>已发布（前台可见；首次发布时通知订阅者）</option>
                    <option value="<?php echo htmlspecialchars(\App\Core\ProductPublishState::STATUS_DRAFT, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $curStatus === \App\Core\ProductPublishState::STATUS_DRAFT ? ' selected' : ''; ?>>草稿（前台不可见）</option>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($isDefaultLang): ?>
        <div class="form-section">
            <h3 class="form-section-title">工业 B2B · 型号与系列 <span class="field-tag field-tag-global">全局</span></h3>
            <div class="form-row">
                <div class="form-group form-group-1 field-global">
                    <label for="product_model">产品型号 (product_model)</label>
                    <input type="text" id="product_model" name="product_model"
                           value="<?php echo htmlspecialchars($product['product_model'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           maxlength="160">
                </div>
                <div class="form-group form-group-1 field-global">
                    <label for="product_series">产品系列 (product_series)</label>
                    <input type="text" id="product_series" name="product_series"
                           value="<?php echo htmlspecialchars($product['product_series'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           maxlength="160">
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="form-section">
            <h3 class="form-section-title">工业 B2B · 型号与系列 <span class="field-tag field-tag-global">全局</span></h3>
            <div class="form-row">
                <div class="form-group">
                    <label>产品型号</label>
                    <input type="text" value="<?php echo htmlspecialchars($product['product_model'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>" disabled class="field-disabled">
                </div>
                <div class="form-group">
                    <label>产品系列</label>
                    <input type="text" value="<?php echo htmlspecialchars($product['product_series'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>" disabled class="field-disabled">
                </div>
            </div>
            <div class="field-sync-notice">🔒 与主语言 (EN) 同步，请在 EN 界面修改</div>
            <div class="form-row" style="margin-top:12px;">
                <div class="form-group">
                    <label>MOQ</label>
                    <input type="text" value="<?php echo htmlspecialchars($product['moq'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>" disabled class="field-disabled">
                </div>
                <div class="form-group">
                    <label>交货期</label>
                    <input type="text" value="<?php echo htmlspecialchars($product['lead_time'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>" disabled class="field-disabled">
                </div>
            </div>
            <div class="form-row" style="margin-top:12px;">
                <div class="form-group form-group-2">
                    <label>关联产品 slug（related_products）</label>
                    <?php
                    $relRo = $product['related_products'] ?? $product['related_product_slugs'] ?? [];
                    $relRoStr = is_array($relRo) ? implode(' ', $relRo) : '';
                    ?>
                    <input type="text" value="<?php echo htmlspecialchars($relRoStr !== '' ? $relRoStr : '—', ENT_QUOTES, 'UTF-8'); ?>" disabled class="field-disabled">
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-section">
            <h3 class="form-section-title">描述内容 <span class="field-tag field-tag-local">本地化</span></h3>

            <div class="form-group field-localized">
                <label for="short_description">摘要 (short_description)</label>
                <div class="field-with-translate">
                    <input type="text" id="short_description" name="short_description"
                           value="<?php echo htmlspecialchars($product['short_description'] ?? $product['short_desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           maxlength="500">
                    <?php if (!$isDefaultLang): ?>
                    <button type="button" class="btn-translate" onclick="translateField('short_description')" title="从英文自动翻译">✨</button>
                    <?php endif; ?>
                </div>
                <small>产品详情页摘要；保存时同步兼容旧字段 short_desc</small>
            </div>

            <div class="form-group field-localized">
                <label>产品描述</label>
                <?php if (!$isDefaultLang): ?>
                <button type="button" class="btn-translate-rt" onclick="translateRichText('desc')" title="从英文自动翻译">✨ Auto Translate from EN</button>
                <?php endif; ?>
                <div id="desc-editor"><?php echo $product['desc'] ?? ''; ?></div>
                <input type="hidden" id="desc" name="desc">
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">产品图集 <span class="field-tag field-tag-global">全局</span></h3>
            <?php if (!$isDefaultLang): ?>
            <div class="field-sync-notice" style="margin-bottom:12px;">🔒 图片与主语言同步，仅可编辑 Alt 文本（本地化字段）</div>
            <?php endif; ?>

            <?php if ($isDefaultLang): ?>
            <div class="gallery-dropzone" id="gallery-dropzone">
                <div class="dropzone-content">
                    <span class="dropzone-icon">📁</span>
                    <p>拖拽图片到此处上传，或点击选择文件</p>
                    <small>支持 JPG / PNG / WebP，单文件最大 2MB</small>
                </div>
                <input type="file" id="gallery-file-input" multiple accept="image/jpeg,image/png,image/webp" style="display:none;">
            </div>
            <?php endif; ?>

            <div class="gallery-grid" id="gallery-grid"></div>

            <?php if ($isDefaultLang): ?>
            <button type="button" class="btn btn-sm" onclick="addImageByUrl()" style="margin-top:10px;">+ 手动输入图片URL</button>
            <?php endif; ?>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">技术参数 <span class="field-tag field-tag-global">全局</span></h3>
            <?php if ($isDefaultLang): ?>
            <div class="specs-editor" id="specs-editor">
                <?php foreach ($product['specs'] ?? [] as $i => $spec): ?>
                <div class="spec-row" draggable="true">
                    <span class="spec-drag-handle" title="拖拽排序">&#8942;&#8942;</span>
                    <input type="text" class="spec-label" value="<?php echo htmlspecialchars($spec['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="参数名 (如: Voltage)" maxlength="60">
                    <input type="text" class="spec-value" value="<?php echo htmlspecialchars($spec['value'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="参数值 (如: 1000V)" maxlength="120">
                    <button type="button" class="btn btn-sm btn-danger spec-remove" onclick="this.closest('.spec-row').remove();">删除</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm" onclick="addSpecRow();" style="margin-top:10px;">+ 添加参数</button>
            <?php else: ?>
            <div class="field-sync-notice" style="margin-bottom:12px;">🔒 技术参数与主语言同步，请在 EN 界面修改</div>
            <div class="specs-readonly" id="specs-readonly">
                <?php if (!empty($product['specs'])): ?>
                <table class="specs-readonly-table">
                    <?php foreach ($product['specs'] as $spec): ?>
                    <tr>
                        <td class="spec-label-td"><?php echo htmlspecialchars($spec['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="spec-value-td"><?php echo htmlspecialchars($spec['value'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?>
                <p style="color:var(--c-text-light);">暂无技术参数</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">FAQ（结构化） <span class="field-tag field-tag-local">本地化</span></h3>
            <p style="font-size:13px;color:var(--c-text-light);margin-bottom:10px;">用于 SEO 的 FAQPage 与前台展示；与技术参数表无关。</p>
            <div class="faqs-editor" id="faqs-editor">
                <?php foreach ($product['faqs'] ?? [] as $faq): ?>
                <div class="faq-row">
                    <input type="text" class="faq-q" value="<?php echo htmlspecialchars($faq['question'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="问题" maxlength="200">
                    <textarea class="faq-a" rows="2" placeholder="回答" maxlength="2000"><?php echo htmlspecialchars($faq['answer'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.faq-row').remove();">删除</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm" onclick="addProductFaqRow();" style="margin-top:8px;">+ 添加 FAQ</button>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">工业 B2B · 详情与标准 <span class="field-tag field-tag-local">本地化</span></h3>
            <p style="font-size:13px;color:var(--c-text-light);margin-bottom:10px;">以下为特种电缆等场景常用区块，支持 HTML；留空则前台不显示。</p>
            <?php
            $b2bText = function (string $key, string $label) use ($product, $isDefaultLang) {
                $val = $product[$key] ?? '';
                echo '<div class="form-group field-localized">';
                echo '<label for="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
                if (!$isDefaultLang) {
                    echo '<button type="button" class="btn-translate-rt" onclick="translatePlainTextarea(\'' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '\')" title="从英文自动翻译">✨ Auto Translate from EN</button>';
                }
                echo '<textarea id="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" rows="5" class="code-editor" placeholder="可输入 HTML">' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '</textarea>';
                echo '</div>';
            };
            $b2bText('product_structure', '产品结构 (product_structure)');
            $b2bText('technical_specs', '技术规格全文 (technical_specs)');
            $b2bText('electrical_characteristics', '电气性能 (electrical_characteristics)');
            $b2bText('mechanical_characteristics', '机械性能 (mechanical_characteristics)');
            $b2bText('environmental_characteristics', '环境性能 (environmental_characteristics)');
            $b2bText('applications', '应用领域 (applications)');
            $b2bText('standards', '标准与认证 (standards)');
            $b2bText('compliance_standards', '符合标准 (compliance_standards)');
            ?>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">附件 <span class="field-tag field-tag-global">全局</span></h3>
            <div class="form-group field-global">
                <label for="datasheet_file">Datasheet PDF</label>
                <?php if ($isDefaultLang): ?>
                <?php if (!empty($product['datasheet'])): ?>
                <div class="field-sync-notice" style="margin-bottom:8px;">
                    当前文件：
                    <a href="/<?php echo htmlspecialchars(ltrim($product['datasheet'], '/'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" style="text-decoration:underline;">
                        <?php echo htmlspecialchars($product['datasheet'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <br>上传新文件将覆盖原文件路径。
                </div>
                <?php endif; ?>
                <input type="file" id="datasheet_file" name="datasheet_file" accept=".pdf,application/pdf">
                <small>仅支持 PDF，最大 10MB。</small>
                <div class="form-group" style="margin-top:12px;">
                    <label for="datasheet-files-json">附加下载（JSON 数组，每项 label + url）</label>
                    <textarea id="datasheet-files-json" name="datasheet_files_json" rows="4" class="code-editor" placeholder='[{"label":"PDF EN","url":"/uploads/a.pdf"}]'><?php echo htmlspecialchars(json_encode($product['datasheet_files'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <small>最多 10 条；留空或 [] 表示无附加文件。</small>
                </div>
                <?php else: ?>
                <input type="text" value="<?php echo htmlspecialchars($product['datasheet'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>"
                       disabled class="field-disabled">
                <div class="field-sync-notice">🔒 此字段与主语言同步，请在 EN 界面修改</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isDefaultLang): ?>
        <div class="form-section">
            <h3 class="form-section-title">标签 / 关联 / 推荐 <span class="field-tag field-tag-global">全局</span></h3>
            <div class="form-group field-global">
                <label for="tags_csv">Tags（逗号分隔）</label>
                <input type="text" id="tags_csv" name="tags_csv"
                       value="<?php echo htmlspecialchars(isset($product['tags']) && is_array($product['tags']) ? implode(', ', $product['tags']) : '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group field-global">
                <label for="related_products">相关产品 slug（related_products）</label>
                <input type="text" id="related_products" name="related_products"
                       value="<?php
                       $rel = $product['related_products'] ?? $product['related_product_slugs'] ?? [];
                       echo htmlspecialchars(is_array($rel) ? implode(' ', $rel) : '', ENT_QUOTES, 'UTF-8');
                       ?>">
                <small>空格或逗号分隔多个 slug；同步写入 related_products 与 related_product_slugs</small>
            </div>
            <div class="form-row">
                <div class="form-group form-group-1 field-global">
                    <label for="moq">MOQ 最小起订量</label>
                    <input type="text" id="moq" name="moq" maxlength="128"
                           value="<?php echo htmlspecialchars($product['moq'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group form-group-1 field-global">
                    <label for="lead_time">交货期 (lead_time)</label>
                    <input type="text" id="lead_time" name="lead_time" maxlength="160"
                           value="<?php echo htmlspecialchars($product['lead_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="form-group field-global">
                <label for="customizable-options-json">可定制项 customizable_options（JSON 数组）</label>
                <textarea id="customizable-options-json" name="customizable_options_json" rows="4" class="code-editor"
                    placeholder='[{"name":"护套","value":"PVC / LSZH"}]'><?php echo htmlspecialchars(json_encode($product['customizable_options'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group field-global">
                <label for="custom-options-json">选配项 custom_options（JSON 数组，与上项独立）</label>
                <textarea id="custom-options-json" name="custom_options_json" rows="4" class="code-editor"
                    placeholder='[{"name":"长度","value":"100m / 500m"}]'><?php echo htmlspecialchars(json_encode($product['custom_options'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group field-global">
                <label for="download-center-json">下载中心 download_center（JSON，title/url/label）</label>
                <textarea id="download-center-json" name="download_center_json" rows="4" class="code-editor"
                    placeholder='[{"title":"安装说明","url":"/uploads/guide.pdf","label":"PDF"}]'><?php echo htmlspecialchars(json_encode($product['download_center'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group field-global">
                <label for="compare_slugs">对比产品 slug（最多4个）</label>
                <input type="text" id="compare_slugs" name="compare_slugs"
                       value="<?php echo htmlspecialchars(isset($product['compare_slugs']) && is_array($product['compare_slugs']) ? implode(' ', $product['compare_slugs']) : '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group field-global">
                <label for="application_industry">应用行业</label>
                <input type="text" id="application_industry" name="application_industry"
                       value="<?php echo htmlspecialchars($product['application_industry'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group field-global">
                <input type="hidden" name="is_featured" value="0">
                <label><input type="checkbox" name="is_featured" value="1" <?php echo !empty($product['is_featured']) ? 'checked' : ''; ?>> 推荐产品</label>
                &nbsp;&nbsp;
                <input type="hidden" name="is_hot" value="0">
                <label><input type="checkbox" name="is_hot" value="1" <?php echo !empty($product['is_hot']) ? 'checked' : ''; ?>> 热门产品</label>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-section">
            <h3 class="form-section-title">详细内容 <span class="field-tag field-tag-local">本地化</span></h3>
            <div class="form-group field-localized">
                <label>产品详情</label>
                <?php if (!$isDefaultLang): ?>
                <button type="button" class="btn-translate-rt" onclick="translateRichText('content')" title="从英文自动翻译">✨ Auto Translate from EN</button>
                <?php endif; ?>
                <div id="content-editor"><?php echo $product['content'] ?? ''; ?></div>
                <input type="hidden" id="content" name="content">
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">SEO设置 <span class="field-tag field-tag-local">本地化</span></h3>
            <p style="font-size:13px;color:var(--c-text-light);margin-bottom:10px;">沿用现有 SEO 管线：title/description 仍由 seo_title、seo_desc 驱动；canonical_url 与 tdk_tags 为可选增强。</p>
            <div class="form-group field-localized">
                <label for="seo_title">SEO标题 (seo_title)</label>
                <div class="field-with-translate">
                    <input type="text" id="seo_title" name="seo_title"
                           value="<?php echo htmlspecialchars($product['seo_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           maxlength="70">
                    <?php if (!$isDefaultLang): ?>
                    <button type="button" class="btn-translate" onclick="translateField('seo_title')" title="从英文自动翻译">✨</button>
                    <?php endif; ?>
                </div>
                <small>建议50-60个字符，留空则使用产品名称</small>
            </div>
            <div class="form-group field-localized">
                <label for="seo_desc">SEO描述 (seo_desc / seo_description)</label>
                <div class="field-with-translate field-with-translate-textarea">
                    <textarea id="seo_desc" name="seo_desc" rows="3" maxlength="500"><?php echo htmlspecialchars($product['seo_desc'] ?? $product['seo_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <?php if (!$isDefaultLang): ?>
                    <button type="button" class="btn-translate btn-translate-textarea" onclick="translateField('seo_desc')" title="从英文自动翻译">✨</button>
                    <?php endif; ?>
                </div>
                <small>建议 meta 描述 120–160 字；可略长用于摘要</small>
            </div>
            <div class="form-group field-localized">
                <label for="seo_keywords">SEO 关键词 (seo_keywords)</label>
                <div class="field-with-translate">
                    <input type="text" id="seo_keywords" name="seo_keywords" maxlength="512"
                           value="<?php echo htmlspecialchars($product['seo_keywords'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!$isDefaultLang): ?>
                    <button type="button" class="btn-translate" onclick="translateField('seo_keywords')" title="从英文自动翻译">✨</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group field-localized">
                <label for="canonical_url">规范链接 (canonical_url)</label>
                <input type="text" id="canonical_url" name="canonical_url" maxlength="512"
                       placeholder="/en/product/slug 或完整 https URL"
                       value="<?php echo htmlspecialchars($product['canonical_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <small>留空则使用系统默认多语言规范 URL，不改变现有 URL 结构</small>
            </div>
            <div class="form-group field-localized">
                <label for="tdk_tags">TDK 附加 meta (tdk_tags JSON)</label>
                <textarea id="tdk_tags" name="tdk_tags" rows="4" class="code-editor"
                    placeholder='[{"name":"twitter:label1","content":"Voltage"},{"property":"og:type","content":"product"}]'><?php
                    $tdk = $product['tdk_tags'] ?? '';
                    if (is_array($tdk)) {
                        echo htmlspecialchars(json_encode($tdk, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');
                    } else {
                        echo htmlspecialchars((string) $tdk, ENT_QUOTES, 'UTF-8');
                    }
                ?></textarea>
                <small>JSON 数组，每项为 name+content 或 property+content；非法 JSON 将被忽略</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $isEdit ? '更新产品' : '创建产品'; ?></button>
            <a href="/admin/products?lang=<?php echo $editLang; ?>" class="btn">取消</a>
        </div>
    </form>
</div>

<div class="modal-overlay" id="image-modal" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3>编辑图片</h3>
            <button type="button" class="modal-close" onclick="closeImageModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-image-preview" id="modal-preview-wrap">
                <img id="modal-preview-img" src="" alt="">
                <div class="modal-preview-placeholder" id="modal-preview-placeholder" style="display:none;">📷 无图片</div>
            </div>
            <div class="form-group field-localized">
                <label>Alt 文本 <span class="field-tag field-tag-local">本地化</span></label>
                <div class="field-with-translate">
                    <input type="text" id="modal-alt-text" maxlength="120" placeholder="描述图片内容">
                    <?php if (!$isDefaultLang): ?>
                    <button type="button" class="btn-translate" onclick="translateImageAlt()" title="从英文自动翻译">✨</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isDefaultLang): ?>
            <div class="form-group">
                <label class="toggle-switch">
                    <input type="checkbox" id="modal-is-main">
                    <span class="toggle-slider"></span>
                    <span style="margin-left:8px;font-size:13px;">设为主图</span>
                </label>
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="saveImageModal()">确定</button>
            <button type="button" class="btn" onclick="closeImageModal()">取消</button>
            <?php if ($isDefaultLang): ?>
            <button type="button" class="btn btn-danger" onclick="removeImageFromModal()">删除图片</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function() {
    var isDefaultLang = <?php echo $isDefaultLang ? 'true' : 'false'; ?>;
    var galleryImages = <?php echo json_encode($product['images'] ?? []); ?>;
    var editingImageIndex = -1;

    var quillDesc = new Quill('#desc-editor', {
        theme: 'snow',
        placeholder: '输入产品描述...',
        modules: {
            toolbar: [
                [{ 'header': [2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['link', 'blockquote'],
                ['clean']
            ]
        }
    });

    var quillContent = new Quill('#content-editor', {
        theme: 'snow',
        placeholder: '输入产品详细内容...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link', 'blockquote'],
                [{ 'color': [] }, { 'background': [] }],
                ['clean']
            ]
        }
    });

    function renderGallery() {
        var grid = document.getElementById('gallery-grid');
        grid.innerHTML = '';
        galleryImages.forEach(function(img, idx) {
            var item = document.createElement('div');
            item.className = 'gallery-item' + (img.is_main ? ' gallery-item-main' : '');
            item.draggable = isDefaultLang;
            item.setAttribute('data-index', idx);

            var thumb = document.createElement('div');
            thumb.className = 'gallery-item-thumb';
            if (img.url) {
                thumb.style.backgroundImage = 'url(' + img.url + ')';
                thumb.style.backgroundSize = 'cover';
                thumb.style.backgroundPosition = 'center';
            } else {
                thumb.innerHTML = '<span class="gallery-item-placeholder">📷</span>';
            }

            var badge = document.createElement('div');
            badge.className = 'gallery-item-badge';
            if (img.is_main) {
                badge.innerHTML = '⭐ 主图';
            }

            var altIndicator = document.createElement('div');
            altIndicator.className = 'gallery-item-alt' + (img.alt_text ? '' : ' gallery-item-alt-empty');
            altIndicator.textContent = img.alt_text ? (img.alt_text.length > 20 ? img.alt_text.substring(0, 20) + '...' : img.alt_text) : '无Alt';

            item.appendChild(thumb);
            item.appendChild(badge);
            item.appendChild(altIndicator);
            item.addEventListener('click', function() { openImageModal(idx); });

            if (isDefaultLang) {
                item.addEventListener('dragstart', galleryDragStart);
                item.addEventListener('dragover', galleryDragOver);
                item.addEventListener('drop', galleryDrop);
                item.addEventListener('dragend', galleryDragEnd);
            }

            grid.appendChild(item);
        });
    }

    var galleryDragSrc = null;

    function galleryDragStart(e) {
        galleryDragSrc = this;
        this.classList.add('gallery-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
    }

    function galleryDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var grid = document.getElementById('gallery-grid');
        var items = Array.from(grid.querySelectorAll('.gallery-item'));
        var overItem = this;
        if (galleryDragSrc === overItem) return;
        var srcIdx = items.indexOf(galleryDragSrc);
        var overIdx = items.indexOf(overItem);
        if (srcIdx < overIdx) {
            grid.insertBefore(galleryDragSrc, overItem.nextSibling);
        } else {
            grid.insertBefore(galleryDragSrc, overItem);
        }
        var newItems = Array.from(grid.querySelectorAll('.gallery-item'));
        var newImages = [];
        newItems.forEach(function(el) {
            var i = parseInt(el.getAttribute('data-index'));
            if (!isNaN(i) && galleryImages[i]) {
                newImages.push(galleryImages[i]);
            }
        });
        galleryImages = newImages;
        renderGallery();
    }

    function galleryDrop(e) { e.preventDefault(); }

    function galleryDragEnd() {
        this.classList.remove('gallery-dragging');
        galleryDragSrc = null;
        renderGallery();
    }

    window.openImageModal = function(idx) {
        editingImageIndex = idx;
        var img = galleryImages[idx];
        document.getElementById('modal-alt-text').value = img.alt_text || '';
        document.getElementById('modal-is-main').checked = !!img.is_main;

        var previewImg = document.getElementById('modal-preview-img');
        var placeholder = document.getElementById('modal-preview-placeholder');
        if (img.url) {
            previewImg.src = img.url;
            previewImg.style.display = 'block';
            placeholder.style.display = 'none';
        } else {
            previewImg.style.display = 'none';
            placeholder.style.display = 'flex';
        }

        document.getElementById('image-modal').style.display = 'flex';
    };

    window.closeImageModal = function() {
        document.getElementById('image-modal').style.display = 'none';
        editingImageIndex = -1;
    };

    window.saveImageModal = function() {
        if (editingImageIndex < 0 || editingImageIndex >= galleryImages.length) return;
        galleryImages[editingImageIndex].alt_text = document.getElementById('modal-alt-text').value.trim();
        if (isDefaultLang) {
            var isMain = document.getElementById('modal-is-main').checked;
            if (isMain) {
                galleryImages.forEach(function(img, i) { img.is_main = (i === editingImageIndex); });
            } else {
                galleryImages[editingImageIndex].is_main = false;
                var hasMain = galleryImages.some(function(img) { return img.is_main; });
                if (!hasMain && galleryImages.length > 0) {
                    galleryImages[0].is_main = true;
                }
            }
        }
        renderGallery();
        closeImageModal();
    };

    window.removeImageFromModal = function() {
        if (editingImageIndex < 0 || editingImageIndex >= galleryImages.length) return;
        galleryImages.splice(editingImageIndex, 1);
        var hasMain = galleryImages.some(function(img) { return img.is_main; });
        if (!hasMain && galleryImages.length > 0) {
            galleryImages[0].is_main = true;
        }
        renderGallery();
        closeImageModal();
    };

    window.translateImageAlt = function() {
        if (editingImageIndex < 0) return;
        var enData = getEnProductData();
        if (!enData || !enData.images || !enData.images[editingImageIndex]) return;
        var enAlt = enData.images[editingImageIndex].alt_text || '';
        if (!enAlt) return;
        var targetLang = document.getElementById('current-lang').value;
        callTranslateApi(enAlt, targetLang, function(translated) {
            document.getElementById('modal-alt-text').value = translated;
        });
    };

    window.addImageByUrl = function() {
        var url = prompt('输入图片URL（如 /uploads/img_xxx.jpg）：');
        if (url && url.trim()) {
            var hasMain = galleryImages.some(function(img) { return img.is_main; });
            galleryImages.push({
                url: url.trim(),
                alt_text: '',
                is_main: !hasMain
            });
            renderGallery();
        }
    };

    if (isDefaultLang) {
        var dropzone = document.getElementById('gallery-dropzone');
        var fileInput = document.getElementById('gallery-file-input');

        if (dropzone) {
            dropzone.addEventListener('click', function() { fileInput.click(); });

            dropzone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dropzone-active');
            });

            dropzone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dropzone-active');
            });

            dropzone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dropzone-active');
                var files = e.dataTransfer.files;
                handleFileUpload(files);
            });

            fileInput.addEventListener('change', function() {
                handleFileUpload(this.files);
                this.value = '';
            });
        }
    }

    function handleFileUpload(files) {
        var csrfToken = document.getElementById('csrf-token').value;
        for (var i = 0; i < files.length; i++) {
            (function(file) {
                if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                    alert('仅支持 JPG/PNG/WebP 格式: ' + file.name);
                    return;
                }
                if (file.size > 2097152) {
                    alert('文件超过 2MB 限制: ' + file.name);
                    return;
                }
                var formData = new FormData();
                formData.append('file', file);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/api/upload', true);
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.success && resp.url) {
                                var hasMain = galleryImages.some(function(img) { return img.is_main; });
                                galleryImages.push({
                                    url: resp.url,
                                    alt_text: '',
                                    is_main: !hasMain
                                });
                                renderGallery();
                            } else {
                                alert('上传失败: ' + (resp.error || '未知错误'));
                            }
                        } catch (e) {
                            alert('上传响应解析失败');
                        }
                    } else {
                        alert('上传失败 (HTTP ' + xhr.status + ')');
                    }
                };
                xhr.onerror = function() { alert('上传网络错误'); };
                xhr.send(formData);
            })(files[i]);
        }
    }

    function getEnProductData() {
        var raw = document.getElementById('en-product-data').value;
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (e) { return null; }
    }

    function callTranslateApi(text, targetLang, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/translate', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success && resp.translated) {
                        callback(resp.translated);
                    } else {
                        alert('翻译失败: ' + (resp.error || '未知错误'));
                    }
                } catch (e) { alert('翻译响应解析失败'); }
            } else {
                alert('翻译请求失败 (HTTP ' + xhr.status + ')');
            }
        };
        xhr.onerror = function() { alert('翻译网络错误'); };
        xhr.send(JSON.stringify({ text: text, targetLang: targetLang, sourceLang: 'en' }));
    }

    window.translateField = function(fieldId) {
        var enData = getEnProductData();
        if (!enData) { alert('无法获取英文数据'); return; }

        var fieldMap = {
            'name': 'name',
            'short_description': 'short_description',
            'short_desc': 'short_description',
            'seo_title': 'seo_title',
            'seo_desc': 'seo_desc',
            'seo_keywords': 'seo_keywords'
        };

        var enKey = fieldMap[fieldId];
        if (!enKey) { alert('该字段不支持一键翻译'); return; }
        var enValue = enData[enKey];
        if (fieldId === 'short_description' && !enValue) {
            enValue = enData.short_desc || '';
        }
        if (!enValue) { alert('英文版本该字段为空，无法翻译'); return; }

        var el = document.getElementById(fieldId);
        if (!el) return;

        var targetLang = document.getElementById('current-lang').value;
        var origDisabled = el.disabled;
        el.disabled = true;
        el.style.opacity = '0.6';

        callTranslateApi(enValue, targetLang, function(translated) {
            el.value = translated;
            el.disabled = origDisabled;
            el.style.opacity = '';
        });
    };

    window.translatePlainTextarea = function(fieldId) {
        var enData = getEnProductData();
        if (!enData) { alert('无法获取英文数据'); return; }
        var enValue = enData[fieldId] || '';
        if (!enValue) { alert('英文版本该字段为空，无法翻译'); return; }
        var el = document.getElementById(fieldId);
        if (!el) return;
        var targetLang = document.getElementById('current-lang').value;
        el.disabled = true;
        el.style.opacity = '0.6';
        callTranslateApi(enValue, targetLang, function(translated) {
            el.value = translated;
            el.disabled = false;
            el.style.opacity = '';
        });
    };

    window.translateRichText = function(fieldKey) {
        var enData = getEnProductData();
        if (!enData) { alert('无法获取英文数据'); return; }

        var enHtml = enData[fieldKey] || '';
        if (!enHtml) { alert('英文版本该字段为空，无法翻译'); return; }

        var targetLang = document.getElementById('current-lang').value;
        var quill = fieldKey === 'desc' ? quillDesc : quillContent;

        quill.enable(false);

        callTranslateApi(enHtml, targetLang, function(translated) {
            quill.clipboard.dangerouslyPasteHTML(translated);
            quill.enable(true);
        });
    };

    window.translateAllFromEN = function() {
        var enData = getEnProductData();
        if (!enData) { alert('无法获取英文数据'); return; }

        var btn = document.getElementById('btn-translate-all');
        btn.disabled = true;
        btn.textContent = '⏳ 翻译中...';

        var targetLang = document.getElementById('current-lang').value;
        var fields = [
            { key: 'name', type: 'input', id: 'name' },
            { key: 'short_description', type: 'input', id: 'short_description' },
            { key: 'desc', type: 'quill', quill: quillDesc },
            { key: 'content', type: 'quill', quill: quillContent },
            { key: 'seo_title', type: 'input', id: 'seo_title' },
            { key: 'seo_desc', type: 'textarea', id: 'seo_desc' },
            { key: 'seo_keywords', type: 'input', id: 'seo_keywords' }
        ];

        var idx = 0;
        function translateNext() {
            if (idx >= fields.length) {
                btn.disabled = false;
                btn.textContent = '✨ Translate All from English';
                return;
            }
            var field = fields[idx];
            idx++;
            var enValue = enData[field.key] || '';
            if (field.key === 'short_description' && !enValue) {
                enValue = enData.short_desc || '';
            }
            if (!enValue) { translateNext(); return; }

            callTranslateApi(enValue, targetLang, function(translated) {
                if (field.type === 'input' || field.type === 'textarea') {
                    var el = document.getElementById(field.id);
                    if (el) el.value = translated;
                } else if (field.type === 'quill') {
                    field.quill.clipboard.dangerouslyPasteHTML(translated);
                }
                translateNext();
            });
        }
        translateNext();
    };

    window.addSpecRow = function() {
        var editor = document.getElementById('specs-editor');
        var html = '<div class="spec-row" draggable="true">' +
            '<span class="spec-drag-handle" title="拖拽排序">&#8942;&#8942;</span>' +
            '<input type="text" class="spec-label" value="" placeholder="参数名 (如: Voltage)" maxlength="60">' +
            '<input type="text" class="spec-value" value="" placeholder="参数值 (如: 1000V)" maxlength="120">' +
            '<button type="button" class="btn btn-sm btn-danger spec-remove" onclick="this.closest(\'.spec-row\').remove();">删除</button></div>';
        editor.insertAdjacentHTML('beforeend', html);
        initSpecsDragSort();
    };

    function initSpecsDragSort() {
        var editor = document.getElementById('specs-editor');
        if (!editor) return;
        var rows = editor.querySelectorAll('.spec-row');
        rows.forEach(function(row) {
            row.removeEventListener('dragstart', specDragStart);
            row.removeEventListener('dragover', specDragOver);
            row.removeEventListener('drop', specDrop);
            row.removeEventListener('dragend', specDragEnd);
            row.addEventListener('dragstart', specDragStart);
            row.addEventListener('dragover', specDragOver);
            row.addEventListener('drop', specDrop);
            row.addEventListener('dragend', specDragEnd);
        });
    }

    var specDragSrc = null;

    function specDragStart(e) {
        specDragSrc = this;
        this.classList.add('spec-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
    }

    function specDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var editor = document.getElementById('specs-editor');
        var rows = Array.from(editor.querySelectorAll('.spec-row'));
        var overRow = this;
        if (specDragSrc === overRow) return;
        var srcIdx = rows.indexOf(specDragSrc);
        var overIdx = rows.indexOf(overRow);
        if (srcIdx < overIdx) {
            editor.insertBefore(specDragSrc, overRow.nextSibling);
        } else {
            editor.insertBefore(specDragSrc, overRow);
        }
    }

    function specDrop(e) { e.preventDefault(); }

    function specDragEnd() {
        this.classList.remove('spec-dragging');
        specDragSrc = null;
    }

    initSpecsDragSort();

    window.prepareProductSubmit = function() {
        document.getElementById('desc').value = quillDesc.root.innerHTML;
        document.getElementById('content').value = quillContent.root.innerHTML;

        var images = [];
        var hasMain = false;
        galleryImages.forEach(function(img) {
            var isMain = !!img.is_main;
            if (isMain) hasMain = true;
            images.push({
                url: (img.url || '').trim(),
                alt_text: (img.alt_text || '').trim(),
                is_main: isMain
            });
        });
        if (!hasMain && images.length > 0) images[0].is_main = true;
        document.getElementById('images-json').value = JSON.stringify(images);

        if (isDefaultLang) {
            var specs = [];
            document.querySelectorAll('#specs-editor .spec-row').forEach(function(el) {
                var label = el.querySelector('.spec-label').value.trim();
                var value = el.querySelector('.spec-value').value.trim();
                if (label && value) specs.push({ label: label, value: value });
            });
            document.getElementById('specs-json').value = JSON.stringify(specs);
        } else {
            var enData = getEnProductData();
            document.getElementById('specs-json').value = JSON.stringify(enData ? (enData.specs || []) : []);
        }

        var faqs = [];
        document.querySelectorAll('#faqs-editor .faq-row').forEach(function(el) {
            var q = el.querySelector('.faq-q').value.trim();
            var a = el.querySelector('.faq-a').value.trim();
            if (q && a) faqs.push({ question: q, answer: a });
        });
        document.getElementById('faqs-json').value = JSON.stringify(faqs);

        return true;
    };

    window.addProductFaqRow = function() {
        var editor = document.getElementById('faqs-editor');
        if (!editor) return;
        var wrap = document.createElement('div');
        wrap.className = 'faq-row';
        wrap.innerHTML = '<input type="text" class="faq-q" value="" placeholder="问题" maxlength="200">' +
            '<textarea class="faq-a" rows="2" placeholder="回答" maxlength="2000"></textarea>' +
            '<button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'.faq-row\').remove();">删除</button>';
        editor.appendChild(wrap);
    };

    renderGallery();

    document.getElementById('image-modal').addEventListener('click', function(e) {
        if (e.target === this) closeImageModal();
    });
})();
</script>
