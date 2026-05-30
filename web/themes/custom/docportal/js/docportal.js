(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.docportal = {
    attach: function (context, settings) {
      if (context !== document || document.body.dataset.docportalReady === 'true') return;

      var isAuthPage = document.body.classList.contains('path-user-login') ||
        document.body.classList.contains('path-user-password') ||
        document.body.classList.contains('path-user-register');

      if (isAuthPage) {
        document.body.classList.add('docportal-auth-page');
        document.body.dataset.docportalReady = 'true';
        enhanceAuthPage();
        return;
      }

      var config = (settings && settings.docportal) ||
        (drupalSettings && drupalSettings.docportal) ||
        {};

      var state = {
        userName: config.userName || 'User',
        userCountry: config.userCountry || 'Workspace',
        isAdmin: !!config.isAdmin,
        currentPath: window.location.pathname
      };

      state.initials = getInitials(state.userName);
      document.body.classList.toggle('docportal-document-page', isDocumentRoute(state.currentPath));

      buildShell(state);
      enhancePage(state);

      // Make category pills clickable
      document.querySelectorAll('.views-field-field-document-category td, .views-field-field-document-category').forEach(function (el) {
        var cat = el.textContent.trim();
        if (!cat) return;
        el.style.cursor = 'pointer';
        el.title = 'Filter by ' + cat;
        el.addEventListener('click', function () {
          var input = document.querySelector('input[name="field_document_category_value"], input[id*="document-category"]');
          if (input) {
            input.value = cat;
            var submit = document.querySelector('.views-exposed-form input[type="submit"]');
            if (submit) submit.click();
          } else {
            window.location.href = '/documents?field_document_category_value=' + encodeURIComponent(cat);
          }
        });
      });

      document.body.dataset.docportalReady = 'true';
    }
  };

  function buildShell(state) {
    var layoutContainer = document.querySelector('.layout-container') || document.querySelector('main');
    if (!layoutContainer || layoutContainer.closest('.docportal-layout')) return;

    var layout = document.createElement('div');
    layout.className = 'docportal-layout';

    var sidebar = document.createElement('aside');
    sidebar.className = 'docportal-sidebar';
    sidebar.innerHTML = renderSidebar(state);

    var main = document.createElement('main');
    main.className = 'docportal-main';
    main.setAttribute('id', 'docportal-main');

    var contentShell = document.createElement('div');
    contentShell.className = 'docportal-content-shell';

    layoutContainer.parentNode.insertBefore(layout, layoutContainer);
    layout.appendChild(sidebar);
    layout.appendChild(main);
    main.appendChild(contentShell);
    contentShell.appendChild(layoutContainer);
  }

  function renderSidebar(state) {
    var nav = getNavItems(state);

    return '' +
      '<div class="docportal-sidebar-inner">' +
        '<a href="/documents" class="docportal-brand" aria-label="DocPortal home">' +
          '<span class="docportal-brand-mark">' + icon('brand') + '</span>' +
          '<span class="docportal-brand-copy">' +
            '<span class="docportal-brand-name">DocPortal</span>' +
            '<span class="docportal-brand-sub">Document vault</span>' +
          '</span>' +
        '</a>' +
        '<nav class="docportal-nav" aria-label="Primary navigation">' +
          nav.map(renderNavItem).join('') +
        '</nav>' +
        '<div class="docportal-sidebar-footer">' +
          '<div class="docportal-user-chip" title="' + escapeHtml(state.userName) + '">' +
            '<span class="docportal-avatar">' + escapeHtml(state.initials) + '</span>' +
            '<span class="docportal-user-meta">' +
              '<span class="docportal-username">' + escapeHtml(state.userName) + '</span>' +
              '<span class="docportal-user-country">' + escapeHtml(state.userCountry) + '</span>' +
            '</span>' +
          '</div>' +
          '<a href="/user/logout" class="docportal-logout-btn">' + icon('logout') + '<span>Sign out</span></a>' +
        '</div>' +
      '</div>';
  }

  function getNavItems(state) {
    var items = [
      { label: 'All Documents', href: '/documents', icon: 'docs', active: state.currentPath === '/documents' || state.currentPath === '/' },
      { label: 'My Uploads', href: '/my-documents', icon: 'upload', active: state.currentPath === '/my-documents' },
      { label: 'My Account', href: '/user', icon: 'user', active: state.currentPath.indexOf('/user') === 0 && state.currentPath !== '/user/logout' }
    ];

    if (state.isAdmin) {
      items.push(
        { label: 'Content', href: '/admin/content', icon: 'grid', active: state.currentPath.indexOf('/admin/content') === 0 },
        { label: 'Users', href: '/admin/people', icon: 'users', active: state.currentPath.indexOf('/admin/people') === 0 }
      );
    }

    return items;
  }

  function renderNavItem(item) {
    return '<a href="' + item.href + '" class="nav-item' + (item.active ? ' active' : '') + '">' +
      icon(item.icon) +
      '<span>' + escapeHtml(item.label) + '</span>' +
    '</a>';
  }

  function enhancePage(state) {
    var shell = document.querySelector('.docportal-content-shell');
    if (!shell) return;

    injectPageHero(shell, state);
    enhanceDocumentTables(shell);
    enhanceProfile(shell, state);
    enhanceSingleDocument(shell);
  }

  function injectPageHero(shell, state) {
    if (shell.querySelector('.docportal-page-hero')) return;

    var titleElement = shell.querySelector('.page-title, h1');
    var title = getPageTitle(shell, state);
    var sub = getPageSubtitle(state);
    var hero = document.createElement('div');
    hero.className = 'docportal-page-hero';
    hero.innerHTML = '' +
      '<div class="docportal-page-hero-left">' +
        '<span class="docportal-page-hero-icon">' + icon(getPageIcon(state)) + '</span>' +
        '<div>' +
          '<h1 class="docportal-page-hero-title">' + escapeHtml(title) + '</h1>' +
          '<p class="docportal-page-hero-sub">' + escapeHtml(sub) + '</p>' +
        '</div>' +
      '</div>' +
      '<span class="docportal-page-status">' + escapeHtml(state.userCountry) + '</span>';

    shell.insertBefore(hero, shell.firstChild);

    if (titleElement &&
      !titleElement.classList.contains('node__title') &&
      !titleElement.closest('.node--type-documents')) {
      titleElement.classList.add('docportal-hidden-title');
    }
  }

  function enhanceDocumentTables(shell) {
    var tables = shell.querySelectorAll('.view-document-library table, table.views-table');
    tables.forEach(function (table) {
      if (table.dataset.docportalEnhanced === 'true') return;
      table.dataset.docportalEnhanced = 'true';

      var labels = Array.prototype.map.call(table.querySelectorAll('thead th'), function (header) {
        return header.textContent.trim();
      });

      table.querySelectorAll('tbody tr').forEach(function (row) {
        row.querySelectorAll('td').forEach(function (cell, index) {
          if (cell.querySelector('.docportal-cell-wrap')) return;

          var wrap = document.createElement('span');
          wrap.className = 'docportal-cell-wrap';
          cell.setAttribute('data-docportal-label', labels[index] || '');

          if (index === 0) {
            wrap.appendChild(fileBadge(row));
          }

          while (cell.firstChild) wrap.appendChild(cell.firstChild);
          cell.appendChild(wrap);
        });
      });
    });
  }

  function enhanceProfile(shell, state) {
    var profile = shell.querySelector('.profile');
    if (!profile || profile.dataset.docportalEnhanced === 'true') return;

    profile.dataset.docportalEnhanced = 'true';
    var header = document.createElement('div');
    header.className = 'docportal-profile-header';
    header.innerHTML = '' +
      '<div class="docportal-profile-avatar">' + escapeHtml(state.initials) + '</div>' +
      '<div>' +
        '<h2 class="docportal-profile-name">' + escapeHtml(state.userName) + '</h2>' +
        '<p class="docportal-profile-meta">' + escapeHtml(state.userCountry) + ' account</p>' +
      '</div>';

    profile.insertBefore(header, profile.firstChild);
  }

  function enhanceSingleDocument(shell) {
    var doc = shell.querySelector('.node--type-documents');
    if (!doc || doc.dataset.docportalEnhanced === 'true') return;

    doc.dataset.docportalEnhanced = 'true';
    var title = doc.querySelector('.node__title, h1, h2');
    if (!title) return;

    var hero = document.createElement('div');
    hero.className = 'docportal-doc-hero';
    hero.appendChild(title);
    doc.insertBefore(hero, doc.firstChild);
  }

  function enhanceAuthPage() {
    var card = document.querySelector('.login-card-wrap');
    if (!card || card.dataset.docportalEnhanced === 'true') return;
    card.dataset.docportalEnhanced = 'true';
    card.classList.add('docportal-auth-card');
  }

  Drupal.behaviors.categoryChips = {
    attach: function (context) {
      if (!document.body.classList.contains('path-documents') &&
        !document.body.classList.contains('path-my-documents')) {
        return;
      }

      var root = context && context.querySelector ? context : document;
      var view = root.querySelector('.view-document-library');

      if (!view || view.querySelector('.docportal-chips')) return;

      var categoryInput = view.querySelector('input[name="field_document_category_value"], input[id*="document-category"], select[name="field_document_category_value"]');
      if (!categoryInput) return;

      var categories = [];
      view.querySelectorAll('.views-field-field-document-category td, .views-field-field-document-category').forEach(function (el) {
        var category = el.textContent.trim();
        if (category && categories.indexOf(category) === -1) {
          categories.push(category);
        }
      });

      if (categories.length === 0) return;

      var chipsWrap = document.createElement('div');
      chipsWrap.className = 'docportal-chips';

      var submitButton = view.querySelector('.views-exposed-form input[type="submit"]');
      var categoryFilterWrap = view.querySelector('.form-item-field-document-category-value');

      function setActiveChip(activeChip) {
        chipsWrap.querySelectorAll('.docportal-chip').forEach(function (chip) {
          chip.classList.toggle('active', chip === activeChip);
        });
      }

      function submitFilters() {
        if (submitButton) {
          submitButton.click();
        }
      }

      var allChip = document.createElement('button');
      allChip.type = 'button';
      allChip.textContent = 'All';
      allChip.className = 'docportal-chip active';
      allChip.addEventListener('click', function (event) {
        event.preventDefault();
        categoryInput.value = '';
        setActiveChip(allChip);
        submitFilters();
      });
      chipsWrap.appendChild(allChip);

      categories.forEach(function (category) {
        var chip = document.createElement('button');
        chip.type = 'button';
        chip.textContent = category;
        chip.className = 'docportal-chip';
        chip.addEventListener('click', function (event) {
          event.preventDefault();
          categoryInput.value = category;
          setActiveChip(chip);
          submitFilters();
        });
        chipsWrap.appendChild(chip);
      });

      var table = view.querySelector('.views-table, table');
      if (table && table.parentNode) {
        table.parentNode.insertBefore(chipsWrap, table);
      } else {
        view.insertBefore(chipsWrap, view.firstChild);
      }

      if (categoryFilterWrap) {
        categoryFilterWrap.style.display = 'none';
      }
    }
  };

  function getPageTitle(shell, state) {
    if (state.currentPath.indexOf('/user') === 0) return 'My Account';
    if (state.currentPath === '/my-documents') return 'My Uploads';
    if (state.currentPath.indexOf('/admin') === 0) return 'Admin Console';

    var title = shell.querySelector('.page-title, h1');
    return title ? title.textContent.trim() || 'All Documents' : 'All Documents';
  }

  function getPageSubtitle(state) {
    if (state.currentPath.indexOf('/user') === 0) return 'Profile, access details, and account settings';
    if (state.currentPath === '/my-documents') return 'Files you have uploaded to the workspace';
    if (state.currentPath.indexOf('/admin') === 0) return 'Manage content, users, and platform settings';
    return 'Search, review, and download shared documents';
  }

  function getPageIcon(state) {
    if (state.currentPath.indexOf('/user') === 0) return 'user';
    if (state.currentPath === '/my-documents') return 'upload';
    if (state.currentPath.indexOf('/admin') === 0) return 'grid';
    return 'docs';
  }

  function isDocumentRoute(path) {
    return path === '/' ||
      path === '/documents' ||
      path === '/my-documents' ||
      path.indexOf('/documents/') === 0 ||
      path.indexOf('/my-documents/') === 0;
  }

  function fileBadge(row) {
    var text = row.textContent.toLowerCase();
    var extension = 'file';
    var type = 'other';

    if (text.indexOf('.pdf') !== -1) { extension = 'pdf'; type = 'pdf'; }
    else if (text.indexOf('.docx') !== -1 || text.indexOf('.doc') !== -1) { extension = 'doc'; type = 'doc'; }
    else if (text.indexOf('.xlsx') !== -1 || text.indexOf('.xls') !== -1) { extension = 'xls'; type = 'xls'; }
    else if (text.indexOf('.zip') !== -1 || text.indexOf('.rar') !== -1) { extension = 'zip'; type = 'zip'; }

    var badge = document.createElement('span');
    badge.className = 'docportal-filetype ft-' + type;
    badge.textContent = extension;
    return badge;
  }

  function getInitials(name) {
    return (name || 'User')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map(function (part) { return part.charAt(0); })
      .join('')
      .toUpperCase() || 'U';
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function icon(name) {
    var icons = {
      brand: '<svg viewBox="0 0 18 18" fill="none"><rect x="2" y="2" width="6" height="10" rx="1.5" fill="currentColor" opacity="0.95"/><rect x="10" y="2" width="6" height="6" rx="1.5" fill="currentColor" opacity="0.65"/><rect x="2" y="14" width="14" height="2" rx="1" fill="currentColor" opacity="0.45"/></svg>',
      docs: '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="2.5" width="9" height="12" rx="1.8"/><path d="M6 6h5M6 9h5M6 12h3"/><path d="M12 5h3v10.5H6"/></svg>',
      upload: '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 12V4M5.8 7.2L9 4l3.2 3.2"/><path d="M3.5 14.5h11"/></svg>',
      user: '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="6" r="3"/><path d="M3.5 15c.7-3 2.5-4.5 5.5-4.5s4.8 1.5 5.5 4.5"/></svg>',
      users: '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="7" cy="6" r="2.5"/><path d="M2.8 15c.5-2.8 1.9-4 4.2-4s3.7 1.2 4.2 4"/><path d="M12 8.2a2.3 2.3 0 1 0-.8-4.4M13 11c1.3.5 2 1.8 2.2 4"/></svg>',
      grid: '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2.5" y="2.5" width="5" height="5" rx="1"/><rect x="10.5" y="2.5" width="5" height="5" rx="1"/><rect x="2.5" y="10.5" width="5" height="5" rx="1"/><rect x="10.5" y="10.5" width="5" height="5" rx="1"/></svg>',
      logout: '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 3H4.5A1.5 1.5 0 0 0 3 4.5v9A1.5 1.5 0 0 0 4.5 15H7"/><path d="M11.5 12.5 15 9l-3.5-3.5M15 9H7"/></svg>',
      menu: '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 5h12M3 9h12M3 13h12"/></svg>'
    };
    return icons[name] || '';
  }
})(Drupal, drupalSettings);

