document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('mobile-toggle');
    var nav = document.getElementById('main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            nav.classList.toggle('open');
            var isOpen = nav.classList.contains('open');
            toggle.innerHTML = isOpen ? '&times;' : '&#9776;';
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    var megaTrigger = document.getElementById('nav-products-trigger');
    var megaMenu = document.getElementById('mega-menu');
    if (megaTrigger && megaMenu) {
        var megaLink = megaTrigger.querySelector('.nav-mega-trigger');
        var openClass = 'is-open';

        function isMobileView() {
            return window.innerWidth <= 768;
        }

        function openMega() {
            megaTrigger.classList.add('active');
            megaTrigger.classList.add(openClass);
        }

        function closeMega() {
            megaTrigger.classList.remove('active');
            megaTrigger.classList.remove(openClass);
        }

        function toggleMega() {
            if (megaTrigger.classList.contains(openClass)) {
                closeMega();
            } else {
                openMega();
            }
        }

        if (megaLink) {
            megaLink.addEventListener('click', function(e) {
                if (isMobileView()) {
                    e.preventDefault();
                    toggleMega();
                }
            });
        }

        megaTrigger.addEventListener('mouseenter', function() {
            if (!isMobileView()) {
                openMega();
            }
        });

        megaTrigger.addEventListener('mouseleave', function() {
            if (!isMobileView()) {
                closeMega();
            }
        });

        if (megaLink) {
            megaLink.addEventListener('focus', function() {
                if (!isMobileView()) {
                    openMega();
                }
            });
        }

        document.addEventListener('click', function(e) {
            if (isMobileView() && !megaTrigger.contains(e.target)) {
                closeMega();
            }
        });

        window.addEventListener('resize', function() {
            closeMega();
        });
    }

    var header = document.getElementById('site-header');
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 10) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }, { passive: true });
    }

    var forms = document.querySelectorAll('.inquiry-form');
    forms.forEach(function(form) {
        var sourceUrlField = form.querySelector('#source_url');
        if (sourceUrlField) {
            sourceUrlField.value = window.location.href;
        }

        form.addEventListener('submit', function(e) {
            var hpUrl = form.querySelector('#website_url');
            var hpSite = form.querySelector('#website');
            if ((hpUrl && hpUrl.value !== '') || (hpSite && hpSite.value !== '')) {
                e.preventDefault();
                return false;
            }

            var captchaInput = form.querySelector('#captcha');
            if (captchaInput && isNaN(parseInt(captchaInput.value, 10))) {
                e.preventDefault();
                alert('Please enter a valid number for the security question.');
                captchaInput.focus();
                return false;
            }
        });
    });
});
