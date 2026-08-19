(function () {
  'use strict';

  var navbar = document.querySelector('.l-navbar');
  var themeToggle = document.querySelector('.l-theme-toggle');
  var html = document.documentElement;

  function handleScroll() {
    if (navbar) {
      if (window.scrollY > 20) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    }
  }

  handleScroll();
  window.addEventListener('scroll', handleScroll, { passive: true });

  document.querySelectorAll('.l-nav-links a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var targetId = this.getAttribute('href');
      if (targetId === '#') return;
      var target = document.querySelector(targetId);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

  var showcaseImg = document.querySelector('.l-showcase-img');

  function switchTab(tabId) {
    var tabs = document.querySelectorAll('.l-tab');
    tabs.forEach(function (t) {
      var isActive = t.getAttribute('data-tab') === tabId;
      t.classList.toggle('active', isActive);
      t.setAttribute('aria-selected', isActive ? 'true' : 'false');
      t.setAttribute('tabindex', isActive ? '0' : '-1');
    });

    var activeTab = document.querySelector('.l-tab.active');
    var thumbGroup = document.querySelector('[data-thumb-group="' + tabId + '"]');
    var defaultThumb = thumbGroup ? thumbGroup.querySelector('.l-thumb') : null;

    var currentMode = html.getAttribute('data-theme-mode') || 'dark';
    if (defaultThumb) {
      var thumbImg = defaultThumb.querySelector('img');
      var newSrc = thumbImg.getAttribute('data-src-' + currentMode);
      if (showcaseImg && newSrc) {
        showcaseImg.classList.add('l-fade');
        setTimeout(function () {
          showcaseImg.src = newSrc;
          showcaseImg.alt = thumbImg.alt || showcaseImg.alt;
          showcaseImg.classList.remove('l-fade');
        }, 200);
      }
    }

    document.querySelectorAll('.l-thumb-group').forEach(function (g) {
      g.hidden = g.getAttribute('data-thumb-group') !== tabId;
    });

    var title = document.querySelector('.l-showcase-title');
    var desc = document.querySelector('.l-showcase-desc');
    if (title) title.textContent = activeTab ? activeTab.textContent : '';
    if (desc) {
      var descMap = {
        billing: 'Process sales, search products, apply line-item discounts and GST, manage customer credit payments, and print receipts — all from one screen.',
        inventory: 'Track batch-based stock, monitor stock levels with real-time balances, identify low-stock products, and manage restocking with a complete audit trail.',
        vendors: 'Manage your vendor directory, log purchase orders, track payments, and view outstanding balances — all in one centralised list.',
        'vendor-history': 'Review detailed vendor history including purchase records, payment tracking, and financial summaries with date-range filtering.',
        'product-history': 'Analyse product performance with velocity classification, daily sales averages, days of supply, revenue tracking, and stock health indicators.'
      };
      desc.textContent = descMap[tabId] || '';
    }
  }

  document.querySelectorAll('.l-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      var tabId = this.getAttribute('data-tab');
      if (!this.classList.contains('active')) switchTab(tabId);
    });
    tab.addEventListener('keydown', function (e) {
      var tabs = Array.from(document.querySelectorAll('.l-tab'));
      var idx = tabs.indexOf(this);
      var target = null;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
        target = tabs[(idx + 1) % tabs.length];
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        target = tabs[(idx - 1 + tabs.length) % tabs.length];
      }
      if (target) {
        e.preventDefault();
        target.click();
        target.focus();
      }
    });
  });

  document.querySelectorAll('.l-thumb').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      var group = this.closest('.l-thumb-group');
      if (!group) return;
      group.querySelectorAll('.l-thumb').forEach(function (t) { t.classList.remove('active'); });
      this.classList.add('active');

      var thumbImg = this.querySelector('img');
      if (showcaseImg && thumbImg) {
        var currentMode = html.getAttribute('data-theme-mode') || 'dark';
        showcaseImg.classList.add('l-fade');
        setTimeout(function () {
          showcaseImg.src = thumbImg.getAttribute('data-src-' + currentMode);
          showcaseImg.alt = thumbImg.alt || showcaseImg.alt;
          showcaseImg.classList.remove('l-fade');
        }, 200);
      }
    });
  });

  var animatedElements = document.querySelectorAll('.l-animate-in');

  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('l-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
    );

    animatedElements.forEach(function (el) {
      observer.observe(el);
    });
  } else {
    animatedElements.forEach(function (el) {
      el.classList.add('l-visible');
    });
  }

  function updateGalleryImages(mode) {
    document.querySelectorAll('[data-src-dark]').forEach(function (img) {
      img.src = img.getAttribute('data-src-' + mode);
    });
  }

  if (themeToggle) {
    function setTheme(mode) {
      html.setAttribute('data-theme-mode', mode);
      localStorage.setItem('theme', mode);
      updateToggle(mode);
      updateGalleryImages(mode);
    }

    function updateToggle(mode) {
      var isDark = mode === 'dark';
      themeToggle.innerHTML = isDark
        ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
    }

    var savedTheme = localStorage.getItem('theme') || 'dark';
    setTheme(savedTheme);

    themeToggle.addEventListener('click', function () {
      var current = html.getAttribute('data-theme-mode') || 'dark';
      setTheme(current === 'dark' ? 'light' : 'dark');
    });
  }

  var faqItems = document.querySelectorAll('.l-faq-item');
  if (faqItems.length) {
    faqItems.forEach(function (item) {
      item.querySelector('.l-faq-question').addEventListener('click', function (e) {
        var wasOpen = item.open;
        faqItems.forEach(function (other) {
          if (other !== item) other.open = false;
        });
        item.open = !wasOpen;
        e.preventDefault();
      });
    });
  }

  var toggleBtns = document.querySelectorAll('.l-toggle-btn');
  if (toggleBtns.length) {
    toggleBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var period = this.getAttribute('data-period');

        toggleBtns.forEach(function (b) {
          b.classList.remove('active');
          b.setAttribute('aria-checked', 'false');
          b.setAttribute('tabindex', '-1');
        });

        this.classList.add('active');
        this.setAttribute('aria-checked', 'true');
        this.setAttribute('tabindex', '0');

        document.querySelectorAll('.l-price').forEach(function (el) {
          el.textContent = el.getAttribute('data-' + period);
        });

        document.querySelectorAll('.l-price-period').forEach(function (el) {
          el.textContent = el.getAttribute('data-' + period);
        });

        document.querySelectorAll('.l-pricing-save').forEach(function (el) {
          el.style.display = el.getAttribute('data-period') === period ? 'block' : 'none';
        });
      });
    });
  }
})();
