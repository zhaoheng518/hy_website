/**
 * analytics.js — GA4 / GTM DataLayer Event System + UTM Attribution
 *
 * Responsibilities:
 *   1. UTM param capture from URL → localStorage (30-day TTL)
 *   2. Landing page capture → sessionStorage (session duration)
 *   3. Auto-populate hidden attribution fields in .inquiry-form
 *   4. GA4 / GTM events: form_start, inquiry_submit, whatsapp_click,
 *      datasheet_download, email_click, phone_click
 *
 * Compatible: PHP 7+ environments, native JS, no Node, no Composer.
 * No inline onclick anywhere. All listeners via addEventListener.
 */
(function () {
    'use strict';

    // =========================================================================
    // UTM / Attribution constants
    // =========================================================================

    var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    var UTM_STORAGE_KEY  = 'stitch_utm_attr';
    var LANDING_SESS_KEY = 'stitch_landing_page';
    var UTM_TTL_MS       = 30 * 24 * 60 * 60 * 1000; // 30 days

    // =========================================================================
    // UTM helpers
    // =========================================================================

    /**
     * Parse UTM params from current page URL query string.
     * Returns only the keys that are present and non-empty.
     * @returns {Object}
     */
    function readUtmFromUrl() {
        var result = {};
        var search = window.location.search;
        if (!search || search.length < 2) { return result; }
        var pairs = search.slice(1).split('&');
        for (var i = 0; i < pairs.length; i++) {
            var idx = pairs[i].indexOf('=');
            if (idx === -1) { continue; }
            var rawKey = decodeURIComponent(pairs[i].slice(0, idx));
            var rawVal = decodeURIComponent(pairs[i].slice(idx + 1).replace(/\+/g, ' '));
            if (UTM_KEYS.indexOf(rawKey) !== -1 && rawVal !== '') {
                result[rawKey] = rawVal.slice(0, 256);
            }
        }
        return result;
    }

    /**
     * Persist UTM params to localStorage with a 30-day expiry.
     * Overwrites previous entry so the most-recent click always wins.
     * @param {Object} utmData
     */
    function saveUtmToStorage(utmData) {
        if (!utmData || Object.keys(utmData).length === 0) { return; }
        try {
            localStorage.setItem(UTM_STORAGE_KEY, JSON.stringify({
                data: utmData,
                expires: Date.now() + UTM_TTL_MS
            }));
        } catch (e) { /* localStorage unavailable (private mode, storage full, etc.) */ }
    }

    /**
     * Read stored UTM params from localStorage (respects TTL).
     * @returns {Object}
     */
    function loadUtmFromStorage() {
        try {
            var raw = localStorage.getItem(UTM_STORAGE_KEY);
            if (!raw) { return {}; }
            var obj = JSON.parse(raw);
            if (!obj || typeof obj !== 'object') { return {}; }
            if (obj.expires && Date.now() > obj.expires) {
                localStorage.removeItem(UTM_STORAGE_KEY);
                return {};
            }
            return (obj.data && typeof obj.data === 'object') ? obj.data : {};
        } catch (e) { return {}; }
    }

    /**
     * Store the current URL as the session's landing page (first visit only).
     */
    function captureLandingPage() {
        try {
            if (!sessionStorage.getItem(LANDING_SESS_KEY)) {
                sessionStorage.setItem(LANDING_SESS_KEY, window.location.href);
            }
        } catch (e) { /* sessionStorage unavailable */ }
    }

    /**
     * Retrieve the stored landing page URL.
     * @returns {string}
     */
    function getLandingPage() {
        try {
            return sessionStorage.getItem(LANDING_SESS_KEY) || '';
        } catch (e) { return ''; }
    }

    // Run UTM capture immediately on script execution (before DOM-ready).
    // Deferred scripts still run before the user can navigate away.
    var _currentPageUtm = readUtmFromUrl();
    if (Object.keys(_currentPageUtm).length > 0) {
        saveUtmToStorage(_currentPageUtm);
    }
    captureLandingPage();

    // =========================================================================
    // DataLayer helpers
    // =========================================================================

    /**
     * Push an event object into window.dataLayer.
     * @param {Object} obj
     */
    function dlPush(obj) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(obj);
    }

    /**
     * Merge all plain-object entries in dataLayer into a single context map.
     * Used to read product/page data pushed by PHP partials.
     * @returns {Object}
     */
    function getPageContext() {
        var dl = window.dataLayer || [];
        var ctx = {};
        for (var i = 0; i < dl.length; i++) {
            var item = dl[i];
            if (item && typeof item === 'object' && typeof item.length === 'undefined') {
                for (var k in item) {
                    if (Object.prototype.hasOwnProperty.call(item, k)) {
                        ctx[k] = item[k];
                    }
                }
            }
        }
        return ctx;
    }

    // =========================================================================
    // DOM traversal helpers
    // =========================================================================

    /**
     * Walk up the DOM from e.target until matching tag or document root.
     * @param {Event} e
     * @param {string} tag  Uppercase tag name, e.g. 'A'
     * @returns {Element|null}
     */
    function closestTag(e, tag) {
        var el = e.target;
        while (el && el !== document) {
            if (el.tagName === tag) { return el; }
            el = el.parentNode;
        }
        return null;
    }

    /**
     * Walk up the DOM from e.target checking for a CSS class.
     * @param {Event} e
     * @param {string} cls
     * @returns {Element|null}
     */
    function closestClass(e, cls) {
        var el = e.target;
        while (el && el !== document) {
            if (el.classList && el.classList.contains(cls)) { return el; }
            el = el.parentNode;
        }
        return null;
    }

    // =========================================================================
    // Attribution: populate hidden form fields
    // =========================================================================

    /**
     * Fill hidden attribution fields in every .inquiry-form.
     * Called once on DOM-ready. Reads from localStorage (UTM) and
     * sessionStorage (landing page), plus document.referrer.
     *
     * Hidden field name mapping:
     *   utm_source      ← localStorage UTM
     *   utm_medium      ← localStorage UTM
     *   utm_campaign    ← localStorage UTM
     *   utm_term        ← localStorage UTM
     *   utm_content     ← localStorage UTM
     *   attr_landing_page ← sessionStorage landing page
     *   attr_referrer     ← document.referrer (JS only; CDN-safe)
     */
    function populateUtmFields() {
        var utmData = loadUtmFromStorage();
        var landingPage = getLandingPage();
        var referrer = (typeof document.referrer === 'string') ? document.referrer : '';

        var fieldMap = {
            'utm_source':       utmData.utm_source   || '',
            'utm_medium':       utmData.utm_medium   || '',
            'utm_campaign':     utmData.utm_campaign || '',
            'utm_term':         utmData.utm_term     || '',
            'utm_content':      utmData.utm_content  || '',
            'attr_landing_page': landingPage,
            'attr_referrer':     referrer
        };

        var forms = document.querySelectorAll('.inquiry-form');
        for (var i = 0; i < forms.length; i++) {
            for (var fieldName in fieldMap) {
                if (!Object.prototype.hasOwnProperty.call(fieldMap, fieldName)) { continue; }
                var el = forms[i].querySelector('[name="' + fieldName + '"]');
                if (el && el.value === '') {
                    el.value = fieldMap[fieldName];
                }
            }
        }
    }

    // =========================================================================
    // GA4 event: form_start
    // First interaction with any visible field in .inquiry-form
    // =========================================================================
    function initFormStart() {
        var forms = document.querySelectorAll('.inquiry-form');
        for (var fi = 0; fi < forms.length; fi++) {
            (function (form) {
                var fired = false;
                var fields = form.querySelectorAll('input:not([type="hidden"]), textarea, select');
                for (var i = 0; i < fields.length; i++) {
                    fields[i].addEventListener('focus', function () {
                        if (fired) { return; }
                        fired = true;
                        var ctx = getPageContext();
                        dlPush({
                            event: 'form_start',
                            form_id: form.getAttribute('id') || 'inquiry-form',
                            page_type: ctx.page_type || '',
                            product_slug: ctx.product_slug || '',
                            product_name: ctx.product_name || ''
                        });
                    });
                }
            })(forms[fi]);
        }
    }

    // =========================================================================
    // GA4 event: inquiry_submit
    // Fires on form submit after honeypot check passes; includes UTM context
    // =========================================================================
    function initInquirySubmit() {
        var forms = document.querySelectorAll('.inquiry-form');
        for (var i = 0; i < forms.length; i++) {
            forms[i].addEventListener('submit', function (e) {
                var form = e.currentTarget;
                var hpUrl  = form.querySelector('#website_url');
                var hpSite = form.querySelector('#website');
                if ((hpUrl && hpUrl.value !== '') || (hpSite && hpSite.value !== '')) {
                    return;
                }
                var ctx = getPageContext();
                var utmData = loadUtmFromStorage();
                dlPush({
                    event: 'inquiry_submit',
                    form_id: form.getAttribute('id') || 'inquiry-form',
                    page_type: ctx.page_type || '',
                    product_slug: ctx.product_slug || '',
                    product_name: ctx.product_name || '',
                    product_category: ctx.product_category || '',
                    utm_source:   utmData.utm_source   || '',
                    utm_medium:   utmData.utm_medium   || '',
                    utm_campaign: utmData.utm_campaign || ''
                });
            });
        }
    }

    // =========================================================================
    // GA4 event: whatsapp_click
    // =========================================================================
    function initWhatsappClick() {
        document.addEventListener('click', function (e) {
            var el = closestTag(e, 'A');
            if (!el) { return; }
            var href = el.getAttribute('href') || '';
            if (href.indexOf('wa.me') === -1 && href.indexOf('whatsapp.com') === -1) { return; }
            var ctx = getPageContext();
            dlPush({
                event: 'whatsapp_click',
                click_url: href,
                page_type: ctx.page_type || '',
                product_slug: ctx.product_slug || ''
            });
        });
    }

    // =========================================================================
    // GA4 event: datasheet_download
    // Module 10: reads data-file-name and data-product-slug from tracked links.
    // Falls back to page context (ctx) for product info when attributes absent.
    // =========================================================================
    function initDatasheetDownload() {
        document.addEventListener('click', function (e) {
            var el = closestClass(e, 'datasheet-download-link');
            if (!el) { return; }
            var ctx = getPageContext();
            dlPush({
                event: 'datasheet_download',
                file_url:     el.getAttribute('href') || '',
                file_name:    el.getAttribute('data-file-name') || '',
                link_text:    (el.textContent || el.innerText || '').replace(/\s+/g, ' ').trim(),
                product_slug: ctx.product_slug || el.getAttribute('data-product-slug') || '',
                product_name: ctx.product_name || ''
            });
        });
    }

    // =========================================================================
    // GA4 event: email_click
    // =========================================================================
    function initEmailClick() {
        document.addEventListener('click', function (e) {
            var el = closestTag(e, 'A');
            if (!el) { return; }
            var href = el.getAttribute('href') || '';
            if (href.indexOf('mailto:') !== 0) { return; }
            var ctx = getPageContext();
            dlPush({
                event: 'email_click',
                click_url: href,
                page_type: ctx.page_type || ''
            });
        });
    }

    // =========================================================================
    // GA4 event: phone_click
    // =========================================================================
    function initPhoneClick() {
        document.addEventListener('click', function (e) {
            var el = closestTag(e, 'A');
            if (!el) { return; }
            var href = el.getAttribute('href') || '';
            if (href.indexOf('tel:') !== 0) { return; }
            var ctx = getPageContext();
            dlPush({
                event: 'phone_click',
                click_url: href,
                page_type: ctx.page_type || ''
            });
        });
    }

    // =========================================================================
    // Bootstrap
    // =========================================================================

    function init() {
        populateUtmFields();
        initFormStart();
        initInquirySubmit();
        initWhatsappClick();
        initDatasheetDownload();
        initEmailClick();
        initPhoneClick();
    }

    // Handle both deferred load (after DOMContentLoaded) and
    // synchronous load (readyState already interactive/complete).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
