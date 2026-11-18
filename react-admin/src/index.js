import React from 'react';
import { createRoot, render } from '@wordpress/element';
import App from './App';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  // Find all React root elements
  const reactRootElements = document.querySelectorAll('.tbswebpressor-root');

  // Initialize React on each container found
  reactRootElements.forEach((container) => {
    // Get any data attributes passed from WordPress
    const wpData = window.tbswData || {};
    const initialTab = container.getAttribute('data-tab') || 'dashboard';

    const app = (
      <React.StrictMode>
        <App wpData={wpData} initialTab={initialTab} />
      </React.StrictMode>
    );

    // WordPress 6.2+ ships React 18 (createRoot); WordPress 6.0-6.1 ship React 17 (render).
    if (typeof createRoot === 'function') {
      createRoot(container).render(app);
    } else {
      render(app, container);
    }
  });
});
