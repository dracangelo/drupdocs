(function (Drupal) {
  'use strict';

  Drupal.behaviors.docportal = {
    attach: function (context, settings) {

      // Don't run on login/password pages
      var isAuthPage = document.body.classList.contains('path-user-login') ||
                       document.body.classList.contains('path-user-password') ||
                       document.body.classList.contains('path-user-register');
      if (isAuthPage) return;

      // Only run once
      if (document.querySelector('.docportal-layout')) return;

      // Get user info from Drupal settings
      var userName = (settings.docportal && settings.docportal.userName) || '';
      var userCountry = (settings.docportal && settings.docportal.userCountry) || '';
      var isAdmin = (settings.docportal && settings.docportal.isAdmin) || false;

      // Build initials
      var initials = userName.substring(0, 2).toUpperCase() || 'U';

      // Build sidebar
      var currentPath = window.location.pathname;

      var adminLinks = isAdmin
        ? '<div class="nav-section">Admin</div>' +
          '<a href="/admin/content" class="nav-item' + (currentPath.indexOf('/admin/content') === 0 ? ' active' : '') + '">' +
            svgIcon('grid') + ' All documents</a>' +
          '<a href="/admin/people" class="nav-item' + (currentPath.indexOf('/admin/people') === 0 ? ' active' : '') + '">' +
            svgIcon('users') + ' Manage users</a>' +
          '<a href="/admin" class="nav-item' + (currentPath === '/admin' ? ' active' : '') + '">' +
            svgIcon('settings') + ' Settings</a>'
        : '';

      var sidebar = document.createElement('div');
      sidebar.className = 'docportal-sidebar';
      sidebar.innerHTML =
        '<a href="/documents" class="docportal-brand">' +
          '<div class="docportal-brand-mark">' +
            '<svg viewBox="0 0 15 15" fill="none"><rect x="1" y="1" width="5" height="8" rx="1" fill="currentColor" opacity="0.95"/><rect x="8" y="1" width="6" height="5" rx="1" fill="currentColor" opacity="0.72"/><rect x="1" y="11" width="13" height="3" rx="1" fill="currentColor" opacity="0.5"/></svg>' +
          '</div>' +
          '<div class="docportal-brand-copy">' +
            '<span class="docportal-brand-kicker">Secure workspace</span>' +
            '<strong>DocPortal</strong>' +
            '<span>Store, manage, and share critical files with confidence.</span>' +
          '</div>' +
        '</a>' +
        '<div class="nav-section">Navigation</div>' +
        '<a href="/documents" class="nav-item' + (currentPath === '/documents' || currentPath === '/' ? ' active' : '') + '">' +
          svgIcon('docs') + ' All Documents</a>' +
        '<a href="/my-documents" class="nav-item' + (currentPath === '/my-documents' ? ' active' : '') + '">' +
          svgIcon('upload') + ' My Uploads</a>' +
        '<a href="/user" class="nav-item' + (currentPath.indexOf('/user') === 0 && currentPath !== '/user/logout' ? ' active' : '') + '">' +
          svgIcon('user') + ' My Account</a>' +
        adminLinks +
        '<div class="sidebar-card">' +
          '<span class="sidebar-card-label">Signed in</span>' +
          '<strong>' + (userName || 'User') + '</strong>' +
          '<p>' + (userCountry || 'Document workspace') + '</p>' +
        '</div>' +
        '<a href="/user/logout" class="sidebar-logout">' + svgIcon('logout') + '<span>Sign out</span></a>';

      // Wrap layout
      var layout = document.createElement('div');
      layout.className = 'docportal-layout';

      var main = document.createElement('div');
      main.className = 'docportal-main';

      var contentShell = document.createElement('div');
      contentShell.className = 'docportal-content-shell';

      var layoutContainer = document.querySelector('.layout-container') || document.querySelector('main') || document.body;

      if (layoutContainer.parentNode) {
        layoutContainer.parentNode.insertBefore(layout, layoutContainer);
        layout.appendChild(sidebar);
        layout.appendChild(main);
        main.appendChild(contentShell);
        contentShell.appendChild(layoutContainer);
      }

      function svgIcon(name) {
        var icons = {
          docs:     '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="8" height="10" rx="1.5"/><path d="M5 6h4M5 8.5h3"/><rect x="6" y="5" width="8" height="10" rx="1.5" fill="white" stroke="currentColor"/><path d="M9 9h2M9 11h2"/></svg>',
          upload:   '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 11V4M5 7l3-3 3 3"/><path d="M3 13h10"/></svg>',
          user:     '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="5.5" r="2.5"/><path d="M3 13c0-2.8 2.2-5 5-5s5 2.2 5 5"/></svg>',
          users:    '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6" cy="5" r="2"/><path d="M2 13c0-2.2 1.8-4 4-4s4 1.8 4 4"/><circle cx="11" cy="5" r="2"/><path d="M14 13c0-2.2-1.8-4-4-4"/></svg>',
          grid:     '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="2" width="5" height="5" rx="1"/><rect x="2" y="9" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/></svg>',
          settings: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="2"/><path d="M8 2v1.5M8 12.5V14M2 8h1.5M12.5 8H14M3.5 3.5l1 1M11.5 11.5l1 1M12.5 3.5l-1 1M4.5 11.5l-1 1"/></svg>',
          logout:   '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H3a1 1 0 00-1 1v10a1 1 0 001 1h3"/><path d="M11 11l3-3-3-3M14 8H6"/></svg>'
        };
        return icons[name] || '';
      }
    }
  };

})(Drupal);

