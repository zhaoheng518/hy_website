<?php
$mockBanner = [
    'title' => 'Welcome to Stitch Tech',
    'subtitle' => 'Professional B2B Solutions for Global Businesses',
    'cta_text' => 'Contact Us',
    'cta_link' => View::langUrl($lang ?? 'en') . '/contact',
    'background_image' => '',
    'background_video' => '',
];
$banner = array_merge($mockBanner, $data['banner'] ?? []);

$mockProducts = [
    'title' => $navLabels['products'] ?? 'Products',
    'subtitle' => '',
    'items' => [],
];
$products = array_merge($mockProducts, $data['products'] ?? []);

$mockFactory = [
    'title' => $navLabels['factory'] ?? 'Factory',
    'subtitle' => '',
    'content' => '',
    'images' => [],
];
$factory = array_merge($mockFactory, $data['factory'] ?? []);

$mockCases = [
    'title' => $navLabels['cases'] ?? 'Cases',
    'subtitle' => '',
    'items' => [],
];
$cases = array_merge($mockCases, $data['cases'] ?? []);

$mockAbout = [
    'title' => $navLabels['about'] ?? 'About Us',
    'content' => '',
    'certifications' => [],
];
$about = array_merge($mockAbout, $data['about'] ?? []);

$mockBlog = [
    'title' => $navLabels['blog'] ?? 'Blog',
    'subtitle' => '',
];
$blog = array_merge($mockBlog, $data['blog'] ?? []);

$mockContact = [
    'title' => $navLabels['contact'] ?? 'Contact Us',
    'subtitle' => '',
    'email' => '',
    'phone' => '',
    'whatsapp' => '',
    'address' => '',
];
$contact = array_merge($mockContact, $data['contact'] ?? []);
?>

<section class="hero-banner" id="hero"
    <?php if (!empty($banner['background_image'])): ?>
    style="background-image: url('<?php echo htmlspecialchars($banner['background_image'], ENT_QUOTES, 'UTF-8'); ?>')"
    <?php endif; ?>>
    <?php if (!empty($banner['background_video'])): ?>
    <video class="hero-video" autoplay muted loop playsinline>
        <source src="<?php echo htmlspecialchars($banner['background_video'], ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
    </video>
    <?php endif; ?>
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <h1 class="hero-title"><?php echo htmlspecialchars($banner['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="hero-subtitle"><?php echo htmlspecialchars($banner['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!empty($banner['cta_text'])): ?>
        <a href="<?php echo htmlspecialchars($banner['cta_link'], ENT_QUOTES, 'UTF-8'); ?>"
           class="btn btn-hero">
            <?php echo htmlspecialchars($banner['cta_text'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php endif; ?>
    </div>
</section>

<section class="section section-products" id="products">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php echo htmlspecialchars($products['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if (!empty($products['subtitle'])): ?>
            <p class="section-subtitle"><?php echo htmlspecialchars($products['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <?php $productItems = array_filter($products['items'] ?? [], function($item) { return !empty($item['show_in_menu']); }); ?>
        <?php if (!empty($productItems)): ?>
        <div class="products-grid">
            <?php foreach ($productItems as $item):
                $itemLink = View::langUrl($lang ?? 'en') . '/products';
                if (!empty($item['slug'])) {
                    $itemLink = View::langUrl($lang ?? 'en') . '/products/' . $item['slug'];
                } elseif (!empty($item['link'])) {
                    $itemLink = $item['link'];
                }
            ?>
            <a href="<?php echo htmlspecialchars($itemLink, ENT_QUOTES, 'UTF-8'); ?>" class="product-card">
                <?php if (!empty($item['image'])): ?>
                <div class="product-card-img">
                    <img src="<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                         loading="lazy">
                </div>
                <?php else: ?>
                <div class="product-card-img product-card-placeholder">
                    <span>&#9733;</span>
                </div>
                <?php endif; ?>
                <div class="product-card-body">
                    <h3><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                    <?php if (!empty($item['desc'])): ?>
                    <p><?php echo htmlspecialchars($item['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="section-empty">
            <p>Product categories coming soon.</p>
            <a href="<?php echo View::langUrl($lang ?? 'en'); ?>/products" class="btn btn-primary">View All Products</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-factory" id="factory">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php echo htmlspecialchars($factory['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if (!empty($factory['subtitle'])): ?>
            <p class="section-subtitle"><?php echo htmlspecialchars($factory['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <div class="factory-layout">
            <div class="factory-text">
                <?php if (!empty($factory['content'])): ?>
                <p><?php echo nl2br(htmlspecialchars($factory['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                <?php else: ?>
                <p>Our state-of-the-art manufacturing facility delivers precision-engineered solutions for defense and industrial applications worldwide.</p>
                <?php endif; ?>
                <div class="factory-stats">
                    <div class="stat-item">
                        <strong>50,000㎡</strong>
                        <span>Production Facility</span>
                    </div>
                    <div class="stat-item">
                        <strong>200+</strong>
                        <span>Engineers</span>
                    </div>
                    <div class="stat-item">
                        <strong>40+</strong>
                        <span>Countries Served</span>
                    </div>
                </div>
            </div>
            <div class="factory-gallery">
                <?php if (!empty($factory['images'])): ?>
                <div class="gallery-grid">
                    <?php foreach (array_slice($factory['images'], 0, 4) as $img): ?>
                    <div class="gallery-item">
                        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="Factory" loading="lazy">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="gallery-placeholder">
                    <span>&#9881;</span>
                    <p>Factory images coming soon</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section section-cases" id="cases">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php echo htmlspecialchars($cases['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if (!empty($cases['subtitle'])): ?>
            <p class="section-subtitle"><?php echo htmlspecialchars($cases['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($cases['items'])): ?>
        <div class="cases-grid">
            <?php foreach ($cases['items'] as $item): ?>
            <div class="case-card">
                <?php if (!empty($item['image'])): ?>
                <div class="case-card-img">
                    <img src="<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                         loading="lazy">
                </div>
                <?php else: ?>
                <div class="case-card-img case-card-placeholder">
                    <span>&#128737;</span>
                </div>
                <?php endif; ?>
                <div class="case-card-body">
                    <h3><?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars($item['desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (!empty($item['slug'])): ?>
                    <a href="<?php echo View::langUrl($lang, 'cases/' . $item['slug']); ?>" class="case-link">
                        <?php echo $navLabels['learn_more'] ?? 'Learn More'; ?> &rarr;
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="section-empty">Case studies coming soon.</div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-about" id="about">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php echo htmlspecialchars($about['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
        </div>
        <div class="about-layout">
            <div class="about-text">
                <?php if (!empty($about['content'])): ?>
                <p><?php echo nl2br(htmlspecialchars($about['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                <?php else: ?>
                <p>Learn more about our company, our mission, and our commitment to excellence.</p>
                <?php endif; ?>
            </div>
            <?php if (!empty($about['certifications'])): ?>
            <div class="cert-wall">
                <h3 class="cert-title"><?php echo $navLabels['certifications'] ?? 'Certifications & Qualifications'; ?></h3>
                <div class="cert-grid">
                    <?php foreach ($about['certifications'] as $cert): ?>
                    <div class="cert-item">
                        <?php if (!empty($cert['image'])): ?>
                        <img src="<?php echo htmlspecialchars($cert['image'], ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($cert['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                             loading="lazy">
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($cert['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section section-blog" id="blog">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php echo htmlspecialchars($blog['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if (!empty($blog['subtitle'])): ?>
            <p class="section-subtitle"><?php echo htmlspecialchars($blog['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($posts)): ?>
        <div class="blog-grid">
            <?php foreach (array_slice($posts, 0, 3) as $post): ?>
            <a href="<?php echo View::langUrl($lang, 'blog/' . ($post['slug'] ?? '')); ?>" class="blog-card">
                <?php if (!empty($post['image'])): ?>
                <div class="blog-card-img">
                    <img src="<?php echo htmlspecialchars($post['image'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                         loading="lazy">
                </div>
                <?php else: ?>
                <div class="blog-card-img blog-card-placeholder">
                    <span>&#128240;</span>
                </div>
                <?php endif; ?>
                <div class="blog-card-body">
                    <span class="blog-date"><?php echo htmlspecialchars($post['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    <h3><?php echo htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars($post['excerpt'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="section-empty"><?php echo $navLabels['no_posts'] ?? 'No articles yet.'; ?></div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-contact" id="contact">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><?php echo htmlspecialchars($contact['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if (!empty($contact['subtitle'])): ?>
            <p class="section-subtitle"><?php echo htmlspecialchars($contact['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <div class="contact-layout">
            <div class="contact-info">
                <?php if (!empty($contact['email'])): ?>
                <div class="contact-item">
                    <span class="contact-icon">&#9993;</span>
                    <div>
                        <strong>Email</strong>
                        <a href="mailto:<?php echo htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contact['phone'])): ?>
                <div class="contact-item">
                    <span class="contact-icon">&#9742;</span>
                    <div>
                        <strong>Phone</strong>
                        <a href="tel:<?php echo htmlspecialchars($contact['phone'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($contact['phone'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contact['whatsapp'])): ?>
                <div class="contact-item">
                    <span class="contact-icon">&#128172;</span>
                    <div>
                        <strong>WhatsApp</strong>
                        <a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $contact['whatsapp']), ENT_QUOTES, 'UTF-8'); ?>"
                           target="_blank" rel="noopener">
                            <?php echo htmlspecialchars($contact['whatsapp'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contact['address'])): ?>
                <div class="contact-item">
                    <span class="contact-icon">&#128205;</span>
                    <div>
                        <strong>Address</strong>
                        <span><?php echo htmlspecialchars($contact['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <form class="contact-form" method="POST" action="<?php echo View::langUrl($lang, 'contact'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="source_url" id="source_url" value="">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="name" placeholder="<?php echo $navLabels['your_name'] ?? 'Your Name'; ?>" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="<?php echo $navLabels['your_email'] ?? 'Your Email'; ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="company" placeholder="<?php echo $navLabels['company'] ?? 'Company'; ?>">
                    </div>
                    <div class="form-group">
                        <input type="tel" name="phone" placeholder="<?php echo $navLabels['phone_whatsapp'] ?? 'Phone / WhatsApp'; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <textarea name="message" rows="4" placeholder="<?php echo $navLabels['your_message'] ?? 'Your Message'; ?>" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <?php echo $navLabels['send_inquiry'] ?? 'Send Inquiry'; ?>
                </button>
            </form>
        </div>
    </div>
</section>
