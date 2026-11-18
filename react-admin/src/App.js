import React, { useEffect, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';

// Nginx rules shown (and copied) when server rewrite delivery is used on Nginx.
const NGINX_RULES = `# Place this block inside your server config
map $http_accept $webp_suffix {
    default "";
    "~*image/webp" ".webp";
}
map $http_accept $avif_suffix {
    default "";
    "~*image/avif" ".avif";
}

location ~* ^/wp-content/uploads/(?<path>.+)\\.(png|jpe?g)$ {
    add_header Vary Accept;
    try_files /wp-content/uploads/$path$avif_suffix /wp-content/uploads/$path$webp_suffix $uri =404;
}`;

const TABS = ['dashboard', 'settings', 'status'];

// Getting-started progress is a per-browser convenience, so it lives in localStorage.
// Every access is guarded: storage can be unavailable (private mode, blocked site data).
const ONBOARDING_KEY = 'tbswebpressor_onboarding';

const readOnboarding = () => {
  try {
    const stored = JSON.parse(window.localStorage.getItem(ONBOARDING_KEY) || '{}');
    return stored && typeof stored === 'object' ? stored : {};
  } catch (error) {
    return {};
  }
};

const writeOnboarding = (value) => {
  try {
    window.localStorage.setItem(ONBOARDING_KEY, JSON.stringify(value));
  } catch (error) {
    // Not persisted; the checklist still works for this page view.
  }
};

function App({ wpData = {}, initialTab = 'dashboard' }) {
  const pages = wpData.pages || {};

  // Which tab the current URL (?page=...) points to, if any.
  const tabFromLocation = () => {
    if (typeof window === 'undefined') return null;
    const page = new URLSearchParams(window.location.search).get('page');
    return TABS.find((tab) => pages[tab] && pages[tab].slug === page) || null;
  };

  const [activeTab, setActiveTab] = useState(() => tabFromLocation() || (TABS.includes(initialTab) ? initialTab : 'dashboard'));
  const tabRefs = useRef({});

  // Last saved settings. The dashboard shows these (not unsaved edits), and the
  // settings form compares against them to detect unsaved changes.
  const [savedSettings, setSavedSettings] = useState(() => ({
    target_formats: wpData.settings?.target_formats || ['webp'],
    webp_quality: wpData.settings?.webp_quality || 80,
    avif_quality: wpData.settings?.avif_quality || 65,
    delivery_method: wpData.settings?.delivery_method || 'html',
    compression_mode: wpData.settings?.compression_mode || 'lossy',
    convert_on_upload: wpData.settings?.convert_on_upload === 1,
    skip_larger: wpData.settings?.skip_larger !== 0,
    html_scope: wpData.settings?.html_scope || 'content',
    exclude_paths: wpData.settings?.exclude_paths || '',
    max_image_dimension: String(wpData.settings?.max_image_dimension || 0),
  }));

  // Settings State (editable copy)
  const [targetFormats, setTargetFormats] = useState(savedSettings.target_formats);
  const [webpQuality, setWebpQuality] = useState(savedSettings.webp_quality);
  const [avifQuality, setAvifQuality] = useState(savedSettings.avif_quality);
  const [deliveryMethod, setDeliveryMethod] = useState(savedSettings.delivery_method);
  const [compressionMode, setCompressionMode] = useState(savedSettings.compression_mode);
  const [convertOnUpload, setConvertOnUpload] = useState(savedSettings.convert_on_upload);
  const [skipLarger, setSkipLarger] = useState(savedSettings.skip_larger);
  const [htmlScope, setHtmlScope] = useState(savedSettings.html_scope);
  const [excludePaths, setExcludePaths] = useState(savedSettings.exclude_paths);
  const [maxDimension, setMaxDimension] = useState(savedSettings.max_image_dimension);
  const [advancedOpen, setAdvancedOpen] = useState(
    savedSettings.exclude_paths.trim() !== '' || savedSettings.max_image_dimension !== '0'
  );
  const [maxDimensionTouched, setMaxDimensionTouched] = useState(false);
  const maxDimensionRef = useRef(null);

  // Library Statistics State
  const [countsStatus, setCountsStatus] = useState('loading'); // loading | ready | error
  const [count, setCount] = useState(0);
  const [pendingCount, setPendingCount] = useState(0);
  const [failedCount, setFailedCount] = useState(0);
  const [skippedCount, setSkippedCount] = useState(0);
  const [excludedCount, setExcludedCount] = useState(0);
  const [totalOriginal, setTotalOriginal] = useState(wpData.stats?.total_original || 0);
  const [totalOptimized, setTotalOptimized] = useState(wpData.stats?.total_optimized || 0);
  const [failedItems, setFailedItems] = useState(null);

  // Processing States
  const [isConverting, setIsConverting] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [logs, setLogs] = useState([]);
  const stopConversion = useRef(true);
  const [background, setBackground] = useState(wpData.background || { status: 'idle' });
  const [isBackgroundBusy, setIsBackgroundBusy] = useState(false);

  // Delivery self-test
  const [isTesting, setIsTesting] = useState(false);
  const [testResults, setTestResults] = useState(null);
  const [nginxCopied, setNginxCopied] = useState(false);

  // Getting-started checklist (dismissed, deliveryReviewed, testRun)
  const [onboarding, setOnboarding] = useState(() => (typeof window === 'undefined' ? {} : readOnboarding()));
  const markOnboarding = (key, value = true) => {
    setOnboarding((previous) => {
      const next = { ...previous, [key]: value };
      writeOnboarding(next);
      return next;
    });
  };

  // Toast notifications
  const [toast, setToast] = useState(null);
  const [toastPaused, setToastPaused] = useState(false);

  // Screen reader announcements (one concise message instead of every log line)
  const [announcement, setAnnouncement] = useState('');

  const consoleEndRef = useRef(null);

  // Locale-aware number formatting (WordPress locale, e.g. de_DE -> de-DE)
  const formatNumber = (value, options = {}) => {
    try {
      return new Intl.NumberFormat(wpData.locale || undefined, options).format(value);
    } catch (error) {
      return String(value);
    }
  };

  // Helper to format bytes
  const formatBytes = (bytes, decimals = 2) => {
    if (!bytes) return sprintf(/* translators: %s: number of bytes. */ __('%s Bytes', 'webpressor-webp-image-converter-optimizer'), formatNumber(0));
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = [__('Bytes', 'webpressor-webp-image-converter-optimizer'), 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(sizes.length - 1, Math.floor(Math.log(bytes) / Math.log(k)));
    return formatNumber(parseFloat((bytes / Math.pow(k, i)).toFixed(dm)), { maximumFractionDigits: dm }) + ' ' + sizes[i];
  };

  // Helper to add toast. Errors stay until dismissed; others close after a few seconds.
  const showToast = (type, message) => {
    setToastPaused(false);
    setToast({ id: Date.now(), type, message });
  };

  const dismissToast = () => setToast(null);

  // Add entry to console log
  const addLog = (text) => {
    const time = new Date().toLocaleTimeString();
    setLogs((prevLogs) => [...prevLogs, `[${time}] ${text}`]);
  };

  // POST helper for admin-ajax requests
  const ajaxRequest = async (action, data = {}) => {
    const params = new URLSearchParams({ action, nonce: wpData.nonce });
    Object.keys(data).forEach((key) => {
      const value = data[key];
      if (Array.isArray(value)) {
        value.forEach((item) => params.append(`${key}[]`, item));
      } else {
        params.append(key, value);
      }
    });
    const response = await fetch(wpData.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params
    });
    if (!response.ok) throw new Error(__('Network error', 'webpressor-webp-image-converter-optimizer'));
    return response.json();
  };

  // Fetch all library counts from server
  const fetchMediaCount = async (refresh = false) => {
    try {
      const result = await ajaxRequest('tbswebpressor_get_media_count', refresh ? { refresh: '1' } : {});
      if (result.success) {
        setCount(result.data.count);
        setPendingCount(result.data.pending || 0);
        setFailedCount(result.data.failed || 0);
        setSkippedCount(result.data.skipped || 0);
        setExcludedCount(result.data.excluded || 0);
        setCountsStatus('ready');
        return result.data;
      }
    } catch (error) {
      // Handled below: the first load shows an error state with a retry button.
    }
    setCountsStatus((status) => (status === 'ready' ? status : 'error'));
    return null;
  };

  const retryLoadingCounts = () => {
    setCountsStatus('loading');
    fetchMediaCount(true);
  };

  const fetchStats = async () => {
    try {
      const result = await ajaxRequest('tbswebpressor_get_stats');
      if (result.success) {
        setTotalOriginal(result.data.total_original);
        setTotalOptimized(result.data.total_optimized);
      }
    } catch (error) {
      // Stats are refreshed again on the next action.
    }
  };

  const fetchFailedItems = async () => {
    try {
      const result = await ajaxRequest('tbswebpressor_get_failed_items');
      if (result.success) setFailedItems(result.data.items || []);
    } catch (error) {
      setFailedItems([]);
    }
  };

  const describeResult = (img) => {
    const name = img.filename || `#${img.id}`;
    if (img.result === 'converted' || (img.result === undefined && img.optimized_size > 0)) {
      const savings = img.original_size > 0
        ? Math.round(((img.original_size - img.optimized_size) / img.original_size) * 100)
        : 0;
      let line = sprintf(
        /* translators: 1: file name, 2: original size, 3: optimized size, 4: percentage saved. */
        __('✓ %1$s optimized: Original %2$s → Optimized %3$s (-%4$d%%)', 'webpressor-webp-image-converter-optimizer'),
        name, formatBytes(img.original_size), formatBytes(img.optimized_size), savings
      );
      if (img.message) line += ` (${img.message})`;
      return line;
    }
    if (img.result === 'failed') {
      return sprintf(/* translators: 1: file name, 2: error message. */ __('✗ %1$s failed: %2$s', 'webpressor-webp-image-converter-optimizer'), name, img.message);
    }
    return sprintf(/* translators: 1: file name, 2: reason. */ __('• %1$s: %2$s', 'webpressor-webp-image-converter-optimizer'), name, img.message);
  };

  // Conversion process
  const startConverter = () => {
    setIsConverting(true);
    stopConversion.current = false;
    setLogs([]);
    setFailedItems(null);
    addLog(__('Starting bulk conversion process...', 'webpressor-webp-image-converter-optimizer'));

    const runBatch = async (page = 1) => {
      if (stopConversion.current) {
        addLog(__('Conversion process stopped by user.', 'webpressor-webp-image-converter-optimizer'));
        setIsConverting(false);
        return;
      }

      /* translators: %d: batch number. */
      addLog(sprintf(__('Processing batch #%d...', 'webpressor-webp-image-converter-optimizer'), page));
      try {
        const res = await ajaxRequest('tbswebpressor_start_conversion', { page });
        if (!res.success) {
          /* translators: %s: error message. */
          addLog(sprintf(__('Error: %s', 'webpressor-webp-image-converter-optimizer'), res.data?.message || __('Unknown error occurred', 'webpressor-webp-image-converter-optimizer')));
          setIsConverting(false);
          return;
        }

        const data = res.data;
        if (data.processed && data.processed.length > 0) {
          data.processed.forEach(img => addLog(describeResult(img)));
        } else {
          addLog(__('Batch was empty or images already processed.', 'webpressor-webp-image-converter-optimizer'));
        }

        // Refresh numbers after each batch
        const counts = await fetchMediaCount(true);
        await fetchStats();
        if (counts) {
          setAnnouncement(sprintf(
            /* translators: 1: processed images, 2: total images. */
            __('Processed %1$s of %2$s images.', 'webpressor-webp-image-converter-optimizer'),
            formatNumber(Math.max(0, counts.count - counts.pending)),
            formatNumber(counts.count)
          ));
        }

        if (data.hasMorePages && !stopConversion.current) {
          setTimeout(() => runBatch(page + 1), 800);
        } else if (stopConversion.current) {
          addLog(__('Conversion process stopped by user.', 'webpressor-webp-image-converter-optimizer'));
          setIsConverting(false);
        } else {
          const failed = counts ? counts.failed : 0;
          if (failed > 0) {
            addLog(sprintf(
              /* translators: %s: number of images. */
              _n('Bulk conversion complete. %s image could not be converted — see the list below.', 'Bulk conversion complete. %s images could not be converted — see the list below.', failed, 'webpressor-webp-image-converter-optimizer'),
              formatNumber(failed)
            ));
            showToast('info', __('Conversion finished with some failed images.', 'webpressor-webp-image-converter-optimizer'));
            fetchFailedItems();
          } else {
            addLog(__('🎉 All images processed successfully! Bulk conversion complete.', 'webpressor-webp-image-converter-optimizer'));
            showToast('success', __('Conversion completed successfully!', 'webpressor-webp-image-converter-optimizer'));
          }
          setIsConverting(false);
        }
      } catch (error) {
        /* translators: %s: error message. */
        addLog(sprintf(__('Fatal Error: %s', 'webpressor-webp-image-converter-optimizer'), error.message));
        setIsConverting(false);
        showToast('error', __('Error during conversion process.', 'webpressor-webp-image-converter-optimizer'));
      }
    };

    runBatch();
  };

  const stopConverter = () => {
    stopConversion.current = true;
    addLog(__('Stopping process after current batch...', 'webpressor-webp-image-converter-optimizer'));
  };

  const resetConvertedMedia = async () => {
    if (!window.confirm(__('Delete all WebP and AVIF files created by WebPressor?\n\nYour original JPEG and PNG images are not changed. You can create the files again at any time with Start Optimization.', 'webpressor-webp-image-converter-optimizer'))) {
      return;
    }

    addLog(__('Deleting WebP and AVIF files...', 'webpressor-webp-image-converter-optimizer'));
    try {
      const result = await ajaxRequest('tbswebpressor_reset_conversion');
      if (result.success) {
        addLog(__('All WebP and AVIF files were deleted.', 'webpressor-webp-image-converter-optimizer'));
        showToast('success', __('All WebP and AVIF files were deleted. Your original images are unchanged.', 'webpressor-webp-image-converter-optimizer'));
        setTotalOriginal(0);
        setTotalOptimized(0);
        setLogs([]);
        setFailedItems(null);
        setBackground((prev) => ({ ...prev, status: 'idle' }));
        await fetchMediaCount(true);
      } else {
        showToast('error', result.data?.message || __('The files could not be deleted. Please try again.', 'webpressor-webp-image-converter-optimizer'));
      }
    } catch (error) {
      showToast('error', __('The files could not be deleted because of a network error. Please try again.', 'webpressor-webp-image-converter-optimizer'));
    }
  };

  const retryFailed = async () => {
    try {
      const result = await ajaxRequest('tbswebpressor_retry_failed');
      if (result.success) {
        setFailedItems(null);
        await fetchMediaCount(true);
        showToast('success', result.data.message);
      } else {
        showToast('error', result.data?.message || __('Something went wrong. Please try again.', 'webpressor-webp-image-converter-optimizer'));
      }
    } catch (error) {
      showToast('error', __('Something went wrong. Please try again.', 'webpressor-webp-image-converter-optimizer'));
    }
  };

  // Background optimization
  const backgroundRequest = async (operation) => {
    setIsBackgroundBusy(true);
    try {
      const result = await ajaxRequest('tbswebpressor_background', { operation });
      if (result.success) {
        setBackground(result.data.state);
        return result.data.state;
      }
      if (operation !== 'status') {
        showToast('error', result.data?.message || __('Something went wrong. Please try again.', 'webpressor-webp-image-converter-optimizer'));
      }
    } catch (error) {
      if (operation !== 'status') {
        showToast('error', __('Something went wrong. Please try again.', 'webpressor-webp-image-converter-optimizer'));
      }
    } finally {
      setIsBackgroundBusy(false);
    }
    return null;
  };

  const startBackground = async () => {
    const state = await backgroundRequest('start');
    if (state && state.status === 'running') {
      showToast('success', __('Background optimization started. You can close this page.', 'webpressor-webp-image-converter-optimizer'));
    }
  };

  const stopBackground = async () => {
    await backgroundRequest('stop');
    await fetchMediaCount(true);
    showToast('info', __('Background optimization stopped.', 'webpressor-webp-image-converter-optimizer'));
  };

  // Save Settings
  const saveSettings = async (e) => {
    e.preventDefault();

    if (maxDimensionError) {
      setMaxDimensionTouched(true);
      setAdvancedOpen(true);
      // Focus after the Advanced section has opened.
      setTimeout(() => maxDimensionRef.current && maxDimensionRef.current.focus(), 0);
      return;
    }

    setIsSaving(true);

    try {
      const result = await ajaxRequest('tbswebpressor_save_settings', {
        webp_quality: webpQuality,
        avif_quality: avifQuality,
        delivery_method: deliveryMethod,
        compression_mode: compressionMode,
        convert_on_upload: convertOnUpload ? 1 : 0,
        skip_larger: skipLarger ? 1 : 0,
        html_scope: htmlScope,
        exclude_paths: excludePaths,
        max_image_dimension: maxDimensionValue,
        target_formats: targetFormats,
      });
      if (result.success) {
        showToast('success', __('Settings saved successfully!', 'webpressor-webp-image-converter-optimizer'));
        const saved = result.data?.settings || {};
        const next = {
          ...currentSettings,
          target_formats: saved.target_formats || targetFormats,
          exclude_paths: typeof saved.exclude_paths === 'string' ? saved.exclude_paths : excludePaths,
          max_image_dimension: String(saved.max_image_dimension !== undefined ? saved.max_image_dimension : maxDimensionValue),
        };
        setTargetFormats(next.target_formats);
        setExcludePaths(next.exclude_paths);
        setMaxDimension(next.max_image_dimension);
        setMaxDimensionTouched(false);
        setSavedSettings(next);
        markOnboarding('deliveryReviewed');
        fetchMediaCount(true);
      } else {
        showToast('error', __('Failed to save settings.', 'webpressor-webp-image-converter-optimizer'));
      }
    } catch (error) {
      showToast('error', __('Network error while saving settings.', 'webpressor-webp-image-converter-optimizer'));
    } finally {
      setIsSaving(false);
    }
  };

  // Highlight the matching WordPress admin submenu item.
  const syncAdminMenu = (tab) => {
    const slug = pages[tab] && pages[tab].slug;
    const menu = document.getElementById('toplevel_page_tbswebpressor-dashboard');
    if (!slug || !menu) return;
    menu.querySelectorAll('.wp-submenu li').forEach((item) => {
      item.classList.remove('current');
      const link = item.querySelector('a');
      if (link) {
        link.classList.remove('current');
        link.removeAttribute('aria-current');
      }
    });
    const current = menu.querySelector(`.wp-submenu a[href$="page=${slug}"]`);
    if (current) {
      current.classList.add('current');
      current.setAttribute('aria-current', 'page');
      if (current.parentElement) current.parentElement.classList.add('current');
    }
  };

  // Switch tabs in place while keeping the URL, browser title and admin menu in sync.
  const switchTab = (tab, { updateHistory = true } = {}) => {
    if (!TABS.includes(tab)) return;
    setActiveTab(tab);
    const page = pages[tab];
    if (!page) return;
    if (updateHistory && window.history && window.history.pushState && tab !== activeTab) {
      window.history.pushState({ tbswebpressorTab: tab }, '', page.url);
    }
    if (page.title) document.title = page.title;
    syncAdminMenu(tab);
  };

  const handleTabClick = (event, tab) => {
    // Let the browser handle new-tab/new-window clicks on the real link.
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button === 1) return;
    event.preventDefault();
    switchTab(tab);
  };

  // Arrow keys move between tabs (manual activation: Enter or Space opens the focused tab).
  const handleTabKeyDown = (event, tab) => {
    const index = TABS.indexOf(tab);
    const rtl = document.documentElement.dir === 'rtl';
    let next = null;
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = index + (rtl && event.key === 'ArrowRight' ? -1 : 1);
    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = index + (rtl && event.key === 'ArrowLeft' ? 1 : -1);
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = TABS.length - 1;
    if (next !== null) {
      event.preventDefault();
      const target = TABS[(next + TABS.length) % TABS.length];
      if (tabRefs.current[target]) tabRefs.current[target].focus();
      return;
    }
    if (event.key === ' ') {
      event.preventDefault();
      switchTab(tab);
    }
  };

  // Restore every field to the last saved values.
  const discardSettings = () => {
    setTargetFormats(savedSettings.target_formats);
    setWebpQuality(savedSettings.webp_quality);
    setAvifQuality(savedSettings.avif_quality);
    setDeliveryMethod(savedSettings.delivery_method);
    setCompressionMode(savedSettings.compression_mode);
    setConvertOnUpload(savedSettings.convert_on_upload);
    setSkipLarger(savedSettings.skip_larger);
    setHtmlScope(savedSettings.html_scope);
    setExcludePaths(savedSettings.exclude_paths);
    setMaxDimension(savedSettings.max_image_dimension);
    setMaxDimensionTouched(false);
    setAnnouncement(__('Unsaved changes were discarded.', 'webpressor-webp-image-converter-optimizer'));
  };

  // Toggle formats selection
  const handleFormatToggle = (format) => {
    if (targetFormats.includes(format)) {
      if (targetFormats.length === 1) {
        showToast('info', __('You must select at least one format.', 'webpressor-webp-image-converter-optimizer'));
        return;
      }
      setTargetFormats(targetFormats.filter(f => f !== format));
    } else {
      setTargetFormats([...targetFormats, format]);
    }
  };

  const handleFormatKeyDown = (event, format, enabled) => {
    if (event.key === ' ' || event.key === 'Enter') {
      event.preventDefault();
      if (enabled) handleFormatToggle(format);
    }
  };

  const copyNginxRules = async () => {
    let copied = false;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(NGINX_RULES);
        copied = true;
      }
    } catch (error) {
      copied = false;
    }
    if (!copied) {
      // Fallback for non-HTTPS admin screens where the Clipboard API is unavailable.
      const textarea = document.createElement('textarea');
      textarea.value = NGINX_RULES;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      try {
        copied = document.execCommand('copy');
      } catch (error) {
        copied = false;
      }
      document.body.removeChild(textarea);
    }
    if (copied) {
      setNginxCopied(true);
      setAnnouncement(__('Nginx rules copied to the clipboard.', 'webpressor-webp-image-converter-optimizer'));
      setTimeout(() => setNginxCopied(false), 3000);
    } else {
      showToast('error', __('The rules could not be copied. Select the text and copy it manually.', 'webpressor-webp-image-converter-optimizer'));
    }
  };

  const runDeliveryTest = async () => {
    setIsTesting(true);
    setTestResults(null);
    try {
      const result = await ajaxRequest('tbswebpressor_test_delivery');
      if (result.success) {
        setTestResults(result.data);
        if (result.data && result.data.sample) markOnboarding('testRun');
      } else {
        showToast('error', result.data?.message || __('Something went wrong. Please try again.', 'webpressor-webp-image-converter-optimizer'));
      }
    } catch (error) {
      showToast('error', __('Something went wrong. Please try again.', 'webpressor-webp-image-converter-optimizer'));
    } finally {
      setIsTesting(false);
    }
  };

  useEffect(() => {
    fetchMediaCount();
    fetchStats();
  }, [wpData.ajax_url, wpData.nonce]);

  // Browser Back/Forward between tabs.
  useEffect(() => {
    const onPopState = () => {
      const tab = tabFromLocation();
      if (tab) switchTab(tab, { updateHistory: false });
    };
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  useEffect(() => {
    if (consoleEndRef.current) {
      consoleEndRef.current.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }, [logs]);

  // Auto-close non-error toasts; hovering or focusing the toast pauses the timer.
  useEffect(() => {
    if (!toast || toast.type === 'error' || toastPaused) return undefined;
    const timer = setTimeout(() => setToast(null), 6000);
    return () => clearTimeout(timer);
  }, [toast, toastPaused]);

  // Poll the background run while it is active.
  useEffect(() => {
    if (background.status !== 'running') return undefined;
    const timer = setInterval(async () => {
      const state = await backgroundRequest('status');
      await fetchMediaCount(true);
      await fetchStats();
      if (state && state.status !== 'running' && state.message === 'completed') {
        showToast('success', __('Background optimization finished.', 'webpressor-webp-image-converter-optimizer'));
      }
    }, 5000);
    return () => clearInterval(timer);
  }, [background.status]);

  // Load failed items when the dashboard shows failures.
  useEffect(() => {
    if (activeTab === 'dashboard' && failedCount > 0 && failedItems === null && !isConverting) {
      fetchFailedItems();
    }
  }, [activeTab, failedCount, isConverting]);

  // Derived variables
  const completedCount = Math.max(0, count - pendingCount);
  const progressPercentage = count > 0 ? Math.round((completedCount / count) * 100) : 0;
  const totalSaved = Math.max(0, totalOriginal - totalOptimized);
  const savingsPercent = totalOriginal > 0 ? Math.round((totalSaved / totalOriginal) * 100) : 0;
  const backgroundRunning = background.status === 'running';

  // Settings form: current values, validation and unsaved-change detection.
  const currentSettings = {
    target_formats: targetFormats,
    webp_quality: webpQuality,
    avif_quality: avifQuality,
    delivery_method: deliveryMethod,
    compression_mode: compressionMode,
    convert_on_upload: convertOnUpload,
    skip_larger: skipLarger,
    html_scope: htmlScope,
    exclude_paths: excludePaths,
    max_image_dimension: maxDimension,
  };
  const settingsKey = (settings) => JSON.stringify({
    ...settings,
    target_formats: [...settings.target_formats].sort(),
    max_image_dimension: String(settings.max_image_dimension).trim(),
  });
  const isDirty = settingsKey(currentSettings) !== settingsKey(savedSettings);

  const maxDimensionTrimmed = String(maxDimension).trim();
  const maxDimensionValue = maxDimensionTrimmed === '' ? 0 : Number(maxDimensionTrimmed);
  const maxDimensionError = !Number.isInteger(maxDimensionValue)
    || maxDimensionValue < 0
    || (maxDimensionValue > 0 && (maxDimensionValue < 640 || maxDimensionValue > 10000))
    ? __('Enter 0 to use the WordPress default, or a whole number from 640 to 10000.', 'webpressor-webp-image-converter-optimizer')
    : '';
  const showMaxDimensionError = maxDimensionError && maxDimensionTouched;
  const isLossless = compressionMode === 'lossless';

  // Warn before leaving the page with unsaved settings.
  useEffect(() => {
    if (!isDirty) return undefined;
    const onBeforeUnload = (event) => {
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', onBeforeUnload);
    return () => window.removeEventListener('beforeunload', onBeforeUnload);
  }, [isDirty]);

  const countsReady = countsStatus === 'ready';
  const optimizedCount = Math.max(0, completedCount - skippedCount - excludedCount - failedCount);

  // Explains the current bulk state, including why the buttons may be disabled.
  let bulkHelp = '';
  if (countsReady && !backgroundRunning) {
    if (isConverting) {
      bulkHelp = __('Optimizing in this window. Keep this page open, or pause and choose Run in Background.', 'webpressor-webp-image-converter-optimizer');
    } else if (pendingCount === 0) {
      bulkHelp = savedSettings.convert_on_upload
        ? __('All images are processed. New uploads are converted automatically.', 'webpressor-webp-image-converter-optimizer')
        : __('All images are processed. Automatic conversion of new uploads is turned off in Settings.', 'webpressor-webp-image-converter-optimizer');
    } else {
      bulkHelp = sprintf(
        /* translators: %s: number of images. */
        _n(
          '%s image is waiting. Start Optimization runs in this window; Run in Background continues on the server after you close this page.',
          '%s images are waiting. Start Optimization runs in this window; Run in Background continues on the server after you close this page.',
          pendingCount,
          'webpressor-webp-image-converter-optimizer'
        ),
        formatNumber(pendingCount)
      );
    }
  }

  // Getting-started checklist
  const libraryEmpty = countsReady && count === 0;
  const onboardingSteps = [
    {
      key: 'optimize',
      done: countsReady && count > 0 && pendingCount === 0,
      title: libraryEmpty
        ? __('Upload your first images', 'webpressor-webp-image-converter-optimizer')
        : __('Optimize your existing images', 'webpressor-webp-image-converter-optimizer'),
      text: libraryEmpty
        ? __('Add JPEG or PNG images to your Media Library.', 'webpressor-webp-image-converter-optimizer')
        : __('Use Start Optimization below to convert the images already in your library.', 'webpressor-webp-image-converter-optimizer'),
      action: libraryEmpty ? { label: __('Upload Images', 'webpressor-webp-image-converter-optimizer'), href: wpData.upload_url } : null,
    },
    {
      key: 'delivery',
      done: !!onboarding.deliveryReviewed || savedSettings.delivery_method !== 'html',
      title: __('Choose a delivery method', 'webpressor-webp-image-converter-optimizer'),
      text: __('Pick how WebP and AVIF images reach your visitors. If you use a page cache, choose <picture> Elements.', 'webpressor-webp-image-converter-optimizer'),
      action: { label: __('Open Settings', 'webpressor-webp-image-converter-optimizer'), tab: 'settings' },
    },
    {
      key: 'test',
      done: !!onboarding.testRun,
      title: __('Check that images are delivered', 'webpressor-webp-image-converter-optimizer'),
      text: __('Run the delivery self-test to confirm your server sends WebP/AVIF to browsers.', 'webpressor-webp-image-converter-optimizer'),
      action: { label: __('Open System Status', 'webpressor-webp-image-converter-optimizer'), tab: 'status' },
    },
  ];
  const onboardingDone = onboardingSteps.filter((step) => step.done).length;
  const showOnboarding = countsReady && !onboarding.dismissed;

  // Render compatibility checkers
  const compat = wpData.compatibility || {};
  const avifUnavailableButSelected = targetFormats.includes('avif') && !compat.avif_supported;
  const savedAvifUnavailable = savedSettings.target_formats.includes('avif') && !compat.avif_supported;

  const deliveryLabels = {
    html: __('HTML Alterations', 'webpressor-webp-image-converter-optimizer'),
    picture: __('<picture> Elements', 'webpressor-webp-image-converter-optimizer'),
    rewrite: __('Server Rules (.htaccess)', 'webpressor-webp-image-converter-optimizer'),
  };

  const statusPillLabels = {
    pass: __('Pass', 'webpressor-webp-image-converter-optimizer'),
    warn: __('Warning', 'webpressor-webp-image-converter-optimizer'),
    fail: __('Fail', 'webpressor-webp-image-converter-optimizer'),
    info: __('Info', 'webpressor-webp-image-converter-optimizer'),
  };
  const statusPillClass = { pass: 'success', warn: 'warning', fail: 'error', info: 'info' };
  const toastIcons = { success: 'dashicons-yes-alt', error: 'dashicons-warning', info: 'dashicons-info' };
  const tabLabels = {
    dashboard: __('Dashboard', 'webpressor-webp-image-converter-optimizer'),
    settings: __('Settings', 'webpressor-webp-image-converter-optimizer'),
    status: __('System Status', 'webpressor-webp-image-converter-optimizer'),
  };
  const tabIcons = { dashboard: 'dashicons-dashboard', settings: 'dashicons-admin-generic', status: 'dashicons-analytics' };

  const breakdown = [];
  if (completedCount > 0) {
    /* translators: %s: number of images. */
    breakdown.push(sprintf(_n('%s optimized', '%s optimized', optimizedCount, 'webpressor-webp-image-converter-optimizer'), formatNumber(optimizedCount)));
  }
  if (skippedCount > 0) {
    /* translators: %s: number of images. */
    breakdown.push(sprintf(_n('%s kept as original (not smaller)', '%s kept as original (not smaller)', skippedCount, 'webpressor-webp-image-converter-optimizer'), formatNumber(skippedCount)));
  }
  if (excludedCount > 0) {
    /* translators: %s: number of images. */
    breakdown.push(sprintf(_n('%s excluded', '%s excluded', excludedCount, 'webpressor-webp-image-converter-optimizer'), formatNumber(excludedCount)));
  }
  if (failedCount > 0) {
    /* translators: %s: number of images. */
    breakdown.push(sprintf(_n('%s failed', '%s failed', failedCount, 'webpressor-webp-image-converter-optimizer'), formatNumber(failedCount)));
  }

  return (
    <div className="tbsw-wrap">
      {/* Toast Alert */}
      {toast && (
        <div
          key={toast.id}
          className={`tbsw-toast tbsw-toast-${toast.type}`}
          role={toast.type === 'error' ? 'alert' : 'status'}
          onMouseEnter={() => setToastPaused(true)}
          onMouseLeave={() => setToastPaused(false)}
          onFocus={() => setToastPaused(true)}
          onBlur={() => setToastPaused(false)}
        >
          <span className={`dashicons ${toastIcons[toast.type] || toastIcons.info}`} aria-hidden="true"></span>
          <p>{toast.message}</p>
          <button
            type="button"
            className="tbsw-toast-close"
            onClick={dismissToast}
            aria-label={__('Dismiss notification', 'webpressor-webp-image-converter-optimizer')}
          >
            <span className="dashicons dashicons-no-alt" aria-hidden="true"></span>
          </button>
        </div>
      )}

      {/* Polite announcements for screen reader users */}
      <div className="screen-reader-text" role="status" aria-live="polite">{announcement}</div>

      {/* Title Header */}
      <div className="tbsw-header">
        <div className="tbsw-header-title">
          <h1>WebPressor</h1>
          <span className="tbsw-version">v{wpData.version || '2.2.0'}</span>
        </div>
        <p className="tbsw-subtitle">{__('Convert JPEG and PNG images to WebP and AVIF and serve them automatically.', 'webpressor-webp-image-converter-optimizer')}</p>
      </div>

      {/* Tabs: real links (open in new tab works) with the ARIA tabs pattern */}
      <div className="tbsw-nav-tabs" role="tablist" aria-label={__('WebPressor sections', 'webpressor-webp-image-converter-optimizer')}>
        {TABS.map((tab) => (
          <a
            key={tab}
            ref={(element) => { tabRefs.current[tab] = element; }}
            id={`tbsw-tab-${tab}`}
            href={pages[tab] ? pages[tab].url : `#${tab}`}
            role="tab"
            className={`tbsw-nav-item ${activeTab === tab ? 'active' : ''}`}
            aria-selected={activeTab === tab}
            aria-controls="tbsw-tabpanel"
            tabIndex={activeTab === tab ? 0 : -1}
            onClick={(event) => handleTabClick(event, tab)}
            onKeyDown={(event) => handleTabKeyDown(event, tab)}
          >
            <span className={`dashicons ${tabIcons[tab]}`} aria-hidden="true"></span> {tabLabels[tab]}
          </a>
        ))}
      </div>

      {/* Content Area */}
      <div className="tbsw-content" role="tabpanel" id="tbsw-tabpanel" aria-labelledby={`tbsw-tab-${activeTab}`}>

        {/* DASHBOARD TAB */}
        {activeTab === 'dashboard' && (
          <div className="tbsw-tab-panel">
            {showOnboarding && (
              <section className="tbsw-card tbsw-onboarding" aria-labelledby="tbsw-onboarding-title">
                <div className="tbsw-onboarding-header">
                  <div>
                    <h2 id="tbsw-onboarding-title">
                      {onboardingDone === onboardingSteps.length
                        ? __("You're all set", 'webpressor-webp-image-converter-optimizer')
                        : __('Getting Started', 'webpressor-webp-image-converter-optimizer')}
                    </h2>
                    <p className="tbsw-field-desc">
                      {sprintf(
                        /* translators: 1: completed steps, 2: total steps. */
                        __('%1$s of %2$s steps done', 'webpressor-webp-image-converter-optimizer'),
                        formatNumber(onboardingDone),
                        formatNumber(onboardingSteps.length)
                      )}
                    </p>
                  </div>
                  <button
                    type="button"
                    className="tbsw-btn tbsw-btn-secondary tbsw-btn-compact"
                    onClick={() => {
                      markOnboarding('dismissed');
                      setAnnouncement(__('Getting started checklist hidden.', 'webpressor-webp-image-converter-optimizer'));
                      // The button disappears; keep keyboard focus in a predictable place.
                      if (tabRefs.current.dashboard) tabRefs.current.dashboard.focus();
                    }}
                  >
                    {__('Hide Checklist', 'webpressor-webp-image-converter-optimizer')}
                  </button>
                </div>
                <ol className="tbsw-checklist">
                  {onboardingSteps.map((step) => (
                    <li key={step.key} className={`tbsw-checklist-item ${step.done ? 'is-done' : ''}`}>
                      <span className={`tbsw-checklist-marker dashicons ${step.done ? 'dashicons-yes-alt' : 'dashicons-marker'}`} aria-hidden="true"></span>
                      <div className="tbsw-checklist-text">
                        <strong>
                          <span className="screen-reader-text">
                            {step.done
                              ? __('Completed:', 'webpressor-webp-image-converter-optimizer')
                              : __('Not completed:', 'webpressor-webp-image-converter-optimizer')}{' '}
                          </span>
                          {step.title}
                        </strong>
                        <p className="tbsw-field-desc">{step.text}</p>
                      </div>
                      {!step.done && step.action && (
                        step.action.href ? (
                          <a className="tbsw-btn tbsw-btn-secondary tbsw-btn-compact" href={step.action.href}>{step.action.label}</a>
                        ) : (
                          <a
                            className="tbsw-btn tbsw-btn-secondary tbsw-btn-compact"
                            href={pages[step.action.tab] ? pages[step.action.tab].url : `#${step.action.tab}`}
                            onClick={(event) => handleTabClick(event, step.action.tab)}
                          >
                            {step.action.label}
                          </a>
                        )
                      )}
                    </li>
                  ))}
                </ol>
              </section>
            )}

            {/* Top Cards Grid */}
            <div className="tbsw-grid">

              {/* Card 1: Storage Savings */}
              <div className="tbsw-card tbsw-stat-card tbsw-card-gradient-blue">
                <div className="tbsw-card-icon">
                  <span className="dashicons dashicons-admin-media" aria-hidden="true"></span>
                </div>
                <div className="tbsw-card-info">
                  <h2 className="tbsw-stat-title">{__('Storage Saved', 'webpressor-webp-image-converter-optimizer')}</h2>
                  <div className="tbsw-stat-main">{formatBytes(totalSaved)}</div>
                  <p className="tbsw-stat-sub">
                    {sprintf(
                      /* translators: 1: percentage saved, e.g. 37%, 2: original size, e.g. 12 MB. */
                      __('%1$s smaller than the originals (%2$s)', 'webpressor-webp-image-converter-optimizer'),
                      formatNumber(savingsPercent / 100, { style: 'percent' }),
                      formatBytes(totalOriginal)
                    )}
                  </p>
                  <div className="tbsw-visual-bar">
                    <div className="tbsw-visual-bar-fill" style={{ width: `${savingsPercent}%` }}></div>
                  </div>
                </div>
              </div>

              {/* Card 2: Optimization Progress */}
              <div className="tbsw-card tbsw-stat-card tbsw-card-gradient-purple">
                <div className="tbsw-card-icon">
                  <span className="dashicons dashicons-update" aria-hidden="true"></span>
                </div>
                <div className="tbsw-card-info">
                  <h2 className="tbsw-stat-title">{__('Library Processed', 'webpressor-webp-image-converter-optimizer')}</h2>
                  <div className="tbsw-stat-main">
                    {countsReady ? formatNumber(progressPercentage / 100, { style: 'percent' }) : '…'}
                  </div>
                  <p className="tbsw-stat-sub">
                    {countsReady
                      ? sprintf(
                        /* translators: 1: processed images, 2: total images, 3: remaining images. */
                        __('Processed %1$s of %2$s images (%3$s remaining)', 'webpressor-webp-image-converter-optimizer'),
                        formatNumber(completedCount),
                        formatNumber(count),
                        formatNumber(pendingCount)
                      )
                      : __('Counting images…', 'webpressor-webp-image-converter-optimizer')}
                  </p>
                  {countsReady && breakdown.length > 0 && (
                    <p className="tbsw-stat-sub tbsw-stat-breakdown">{breakdown.join(' · ')}</p>
                  )}
                  <div className="tbsw-visual-bar">
                    <div className="tbsw-visual-bar-fill" style={{ width: `${progressPercentage}%` }}></div>
                  </div>
                </div>
              </div>

              {/* Card 3: Formats Active */}
              <div className="tbsw-card tbsw-stat-card tbsw-card-gradient-green">
                <div className="tbsw-card-icon">
                  <span className="dashicons dashicons-image-filter" aria-hidden="true"></span>
                </div>
                <div className="tbsw-card-info">
                  <h2 className="tbsw-stat-title">{__('Active Outputs', 'webpressor-webp-image-converter-optimizer')}</h2>
                  <div className="tbsw-formats-list">
                    {savedSettings.target_formats.map(format => (
                      <span key={format} className="tbsw-badge-format">
                        {format.toUpperCase()}
                      </span>
                    ))}
                  </div>
                  <p className="tbsw-stat-sub">
                    {sprintf(/* translators: %s: delivery method name. */ __('Method: %s', 'webpressor-webp-image-converter-optimizer'), deliveryLabels[savedSettings.delivery_method] || deliveryLabels.html)}
                  </p>
                </div>
              </div>

            </div>

            {savedAvifUnavailable && (
              <div className="tbsw-card tbsw-notice-card" role="note">
                <h3>{__('AVIF is not available on this server', 'webpressor-webp-image-converter-optimizer')}</h3>
                <p>{__('AVIF is selected in Settings, but this server cannot create AVIF files. Only the other selected formats are generated, and AVIF images are not counted as pending.', 'webpressor-webp-image-converter-optimizer')}</p>
              </div>
            )}

            {/* Bulk Optimization Controller */}
            <div className="tbsw-card">
              <h2>{__('Bulk Optimizer', 'webpressor-webp-image-converter-optimizer')}</h2>
              <p className="tbsw-section-desc">
                {__('Create WebP and AVIF versions of the JPEG and PNG images already in your Media Library. Your original images are never changed.', 'webpressor-webp-image-converter-optimizer')}
              </p>

              {countsStatus === 'error' && (
                <div className="tbsw-inline-notice tbsw-inline-notice-error" role="alert">
                  <span className="dashicons dashicons-warning" aria-hidden="true"></span>
                  <p>{__('Your library status could not be loaded. Check your connection and try again.', 'webpressor-webp-image-converter-optimizer')}</p>
                  <button type="button" className="tbsw-btn tbsw-btn-secondary" onClick={retryLoadingCounts}>
                    {__('Try Again', 'webpressor-webp-image-converter-optimizer')}
                  </button>
                </div>
              )}

              {countsReady && count === 0 && (
                <div className="tbsw-empty-state">
                  <span className="dashicons dashicons-format-image" aria-hidden="true"></span>
                  <h3>{__('No JPEG or PNG images yet', 'webpressor-webp-image-converter-optimizer')}</h3>
                  <p>
                    {savedSettings.convert_on_upload
                      ? __('Upload images to your Media Library. WebPressor converts new uploads automatically.', 'webpressor-webp-image-converter-optimizer')
                      : __('Upload images to your Media Library, then come back here to optimize them.', 'webpressor-webp-image-converter-optimizer')}
                  </p>
                  <a className="tbsw-btn tbsw-btn-primary" href={wpData.upload_url}>
                    <span className="dashicons dashicons-upload" aria-hidden="true"></span> {__('Upload Images', 'webpressor-webp-image-converter-optimizer')}
                  </a>
                </div>
              )}

              {countsStatus !== 'error' && !(countsReady && count === 0) && (
              <div className="tbsw-bulk-controls">
                <div className="tbsw-progress-section">
                  <div className="tbsw-progress-label">
                    <span id="tbsw-progress-label">{__('Overall Library Status', 'webpressor-webp-image-converter-optimizer')}</span>
                    <span>
                      {countsReady
                        ? sprintf(/* translators: 1: processed images, 2: total images. */ __('%1$s / %2$s Images', 'webpressor-webp-image-converter-optimizer'), formatNumber(completedCount), formatNumber(count))
                        : __('Counting images…', 'webpressor-webp-image-converter-optimizer')}
                    </span>
                  </div>
                  <div
                    className="tbsw-progress-bar-large"
                    role="progressbar"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={countsReady ? progressPercentage : undefined}
                    aria-labelledby="tbsw-progress-label"
                  >
                    <div className="tbsw-progress-fill-large" style={{ width: `${progressPercentage}%` }}></div>
                  </div>
                </div>

                {backgroundRunning && (
                  <div className="tbsw-inline-notice" role="status">
                    <div className="tbsw-pulse-loader" aria-hidden="true"></div>
                    <p>
                      {sprintf(
                        /* translators: 1: converted images, 2: failed images. */
                        __('Background optimization is running — %1$s converted, %2$s failed so far. You can close this page; it continues on the server.', 'webpressor-webp-image-converter-optimizer'),
                        formatNumber(background.converted || 0),
                        formatNumber(background.failed || 0)
                      )}
                      {compat.cron_disabled ? ' ' + __('WP-Cron is disabled on this site, so progress depends on your server cron job.', 'webpressor-webp-image-converter-optimizer') : ''}
                    </p>
                  </div>
                )}

                <div className="tbsw-actions-row">
                  {!isConverting ? (
                    <button
                      type="button"
                      className="tbsw-btn tbsw-btn-primary"
                      onClick={startConverter}
                      disabled={!countsReady || pendingCount === 0 || backgroundRunning}
                      aria-describedby={bulkHelp ? 'tbsw-bulk-help' : undefined}
                    >
                      <span className="dashicons dashicons-images-alt" aria-hidden="true"></span> {__('Start Optimization', 'webpressor-webp-image-converter-optimizer')}
                    </button>
                  ) : (
                    <button type="button" className="tbsw-btn tbsw-btn-danger" onClick={stopConverter}>
                      <span className="dashicons dashicons-controls-pause" aria-hidden="true"></span> {__('Pause Optimization', 'webpressor-webp-image-converter-optimizer')}
                    </button>
                  )}

                  {!backgroundRunning ? (
                    <button
                      type="button"
                      className="tbsw-btn tbsw-btn-secondary"
                      onClick={startBackground}
                      disabled={!countsReady || isConverting || pendingCount === 0 || isBackgroundBusy}
                      aria-describedby={bulkHelp ? 'tbsw-bulk-help' : undefined}
                    >
                      <span className="dashicons dashicons-clock" aria-hidden="true"></span> {__('Run in Background', 'webpressor-webp-image-converter-optimizer')}
                    </button>
                  ) : (
                    <button
                      type="button"
                      className="tbsw-btn tbsw-btn-danger"
                      onClick={stopBackground}
                      disabled={isBackgroundBusy}
                    >
                      <span className="dashicons dashicons-controls-pause" aria-hidden="true"></span> {__('Stop Background Run', 'webpressor-webp-image-converter-optimizer')}
                    </button>
                  )}
                </div>
                {bulkHelp && <p className="tbsw-field-desc tbsw-bulk-help" id="tbsw-bulk-help">{bulkHelp}</p>}
              </div>
              )}

              {/* Log Console Output */}
              {(logs.length > 0 || isConverting) && (
                <div className="tbsw-console-card">
                  <div className="tbsw-console-header">
                    <span>{__('Live Optimization Log', 'webpressor-webp-image-converter-optimizer')}</span>
                    {isConverting && <div className="tbsw-pulse-loader" aria-hidden="true"></div>}
                  </div>
                  {/* Not a live region: progress is announced once per batch instead of per line. */}
                  <div
                    className="tbsw-console-logs"
                    role="region"
                    tabIndex={0}
                    aria-label={__('Live Optimization Log', 'webpressor-webp-image-converter-optimizer')}
                  >
                    {logs.map((log, index) => (
                      <div key={index} className="tbsw-log-line">{log}</div>
                    ))}
                    <div ref={consoleEndRef} />
                  </div>
                </div>
              )}

              {/* Failed images */}
              {failedCount > 0 && !isConverting && (
                <div className="tbsw-failed-panel">
                  <div className="tbsw-failed-header">
                    <h3>
                      {sprintf(
                        /* translators: %s: number of images. */
                        _n('%s image could not be converted', '%s images could not be converted', failedCount, 'webpressor-webp-image-converter-optimizer'),
                        formatNumber(failedCount)
                      )}
                    </h3>
                    <button
                      type="button"
                      className="tbsw-btn tbsw-btn-secondary"
                      onClick={retryFailed}
                      disabled={backgroundRunning}
                    >
                      <span className="dashicons dashicons-update" aria-hidden="true"></span> {__('Retry Failed Images', 'webpressor-webp-image-converter-optimizer')}
                    </button>
                  </div>
                  {failedItems === null ? (
                    <p className="tbsw-field-desc">{__('Loading details…', 'webpressor-webp-image-converter-optimizer')}</p>
                  ) : (
                    <ul className="tbsw-failed-list">
                      {failedItems.map((item) => (
                        <li key={item.id}>
                          <strong>{item.filename}</strong> — {item.message}
                        </li>
                      ))}
                    </ul>
                  )}
                  <p className="tbsw-field-desc">
                    {__('Failed images are skipped by the bulk optimizer so it never gets stuck. Fix the cause (for example a missing or corrupt file), then retry. You can also exclude single images from the Media Library list view.', 'webpressor-webp-image-converter-optimizer')}
                  </p>
                </div>
              )}

              <div className="tbsw-media-link">
                <p className="tbsw-field-desc">
                  {__('To optimize, remove or exclude a single image, use the WebPressor column in the Media Library list view. In grid view, the same controls appear in the image details.', 'webpressor-webp-image-converter-optimizer')}
                </p>
                <a className="tbsw-btn tbsw-btn-secondary" href={wpData.media_library_url}>
                  <span className="dashicons dashicons-admin-media" aria-hidden="true"></span> {__('Open Media Library (List View)', 'webpressor-webp-image-converter-optimizer')}
                </a>
              </div>

              {countsReady && (completedCount > 0 || totalOptimized > 0) && (
                <div className="tbsw-danger-zone">
                  <div className="tbsw-danger-zone-text">
                    <h3>{__('Delete Generated Files', 'webpressor-webp-image-converter-optimizer')}</h3>
                    <p className="tbsw-field-desc" id="tbsw-reset-help">
                      {isConverting || backgroundRunning
                        ? __('Available when no optimization is running.', 'webpressor-webp-image-converter-optimizer')
                        : __('Removes every WebP and AVIF file created by WebPressor. Your original images are not changed, and you can create the files again at any time.', 'webpressor-webp-image-converter-optimizer')}
                    </p>
                  </div>
                  <button
                    type="button"
                    className="tbsw-btn tbsw-btn-danger-outline"
                    onClick={resetConvertedMedia}
                    disabled={isConverting || backgroundRunning}
                    aria-describedby="tbsw-reset-help"
                  >
                    <span className="dashicons dashicons-trash" aria-hidden="true"></span> {__('Delete All WebP/AVIF Files', 'webpressor-webp-image-converter-optimizer')}
                  </button>
                </div>
              )}
            </div>
          </div>
        )}

        {/* SETTINGS TAB */}
        {activeTab === 'settings' && (
          <div className="tbsw-tab-panel">
            <div className="tbsw-card">
              <h2>{__('Compression & Delivery Settings', 'webpressor-webp-image-converter-optimizer')}</h2>
              <p className="tbsw-section-desc">
                {__('Choose which formats WebPressor creates and how they reach your visitors.', 'webpressor-webp-image-converter-optimizer')}
              </p>

              <form onSubmit={saveSettings} noValidate>
                {/* 1. Output formats and quality */}
                <section className="tbsw-settings-section" aria-labelledby="tbsw-section-formats">
                  <h3 className="tbsw-settings-heading" id="tbsw-section-formats">{__('Output Formats and Quality', 'webpressor-webp-image-converter-optimizer')}</h3>

                  <div className="tbsw-form-group" role="group" aria-labelledby="tbsw-formats-label">
                    <span className="tbsw-form-label" id="tbsw-formats-label">{__('Formats to Create', 'webpressor-webp-image-converter-optimizer')}</span>
                    <p className="tbsw-field-desc">{__('When both are selected, browsers that support AVIF get AVIF and all others get WebP.', 'webpressor-webp-image-converter-optimizer')}</p>

                    <div className="tbsw-checkbox-grid">
                      <div
                        className={`tbsw-selectable-card ${targetFormats.includes('webp') ? 'selected' : ''}`}
                        onClick={() => handleFormatToggle('webp')}
                        onKeyDown={(e) => handleFormatKeyDown(e, 'webp', true)}
                        role="checkbox"
                        tabIndex={0}
                        aria-checked={targetFormats.includes('webp')}
                        aria-labelledby="tbsw-format-webp-title"
                        aria-describedby="tbsw-format-webp-desc"
                      >
                        <div className="tbsw-selectable-header">
                          <span className="dashicons dashicons-yes" aria-hidden="true"></span>
                          <h4 id="tbsw-format-webp-title">{__('WebP Format', 'webpressor-webp-image-converter-optimizer')}</h4>
                        </div>
                        <p id="tbsw-format-webp-desc">{__('Supported by all modern browsers. Typically 25–35% smaller than JPEG. Your original images are always kept.', 'webpressor-webp-image-converter-optimizer')}</p>
                      </div>

                      <div
                        className={`tbsw-selectable-card ${!compat.avif_supported ? 'disabled' : ''} ${targetFormats.includes('avif') ? 'selected' : ''}`}
                        onClick={() => compat.avif_supported && handleFormatToggle('avif')}
                        onKeyDown={(e) => handleFormatKeyDown(e, 'avif', !!compat.avif_supported)}
                        role="checkbox"
                        tabIndex={0}
                        aria-checked={targetFormats.includes('avif')}
                        aria-disabled={!compat.avif_supported}
                        aria-labelledby="tbsw-format-avif-title"
                        aria-describedby="tbsw-format-avif-desc"
                      >
                        <div className="tbsw-selectable-header">
                          <span className="dashicons dashicons-yes" aria-hidden="true"></span>
                          <h4 id="tbsw-format-avif-title">{__('AVIF Format', 'webpressor-webp-image-converter-optimizer')}</h4>
                        </div>
                        <p id="tbsw-format-avif-desc">
                          {__('Up to 50% smaller than JPEG and about 20% smaller than WebP. Supported by most modern browsers.', 'webpressor-webp-image-converter-optimizer')}
                          {!compat.avif_supported && (
                            <>
                              {' '}
                              <span className="tbsw-error-badge">{__('Not supported by server GD', 'webpressor-webp-image-converter-optimizer')}</span>
                            </>
                          )}
                        </p>
                      </div>
                    </div>
                    {avifUnavailableButSelected && (
                      <p className="tbsw-field-desc">
                        {__('AVIF stays selected from an earlier configuration but is skipped on this server. Select WebP to keep converting images.', 'webpressor-webp-image-converter-optimizer')}
                      </p>
                    )}
                  </div>

                  <div className="tbsw-form-group" role="radiogroup" aria-labelledby="tbsw-compression-label">
                    <span className="tbsw-form-label" id="tbsw-compression-label">{__('Compression Mode', 'webpressor-webp-image-converter-optimizer')}</span>
                    <div className="tbsw-radio-row">
                      <label className="tbsw-radio-inline">
                        <input
                          type="radio"
                          name="compression_mode"
                          value="lossy"
                          checked={compressionMode === 'lossy'}
                          onChange={() => setCompressionMode('lossy')}
                        />
                        <span>{__('Lossy (Highly Recommended)', 'webpressor-webp-image-converter-optimizer')}</span>
                      </label>
                      <label className="tbsw-radio-inline">
                        <input
                          type="radio"
                          name="compression_mode"
                          value="lossless"
                          checked={compressionMode === 'lossless'}
                          onChange={() => setCompressionMode('lossless')}
                        />
                        <span>{__('Lossless (Forces 100% Quality)', 'webpressor-webp-image-converter-optimizer')}</span>
                      </label>
                    </div>
                  </div>

                  <div className="tbsw-form-row">
                    {targetFormats.includes('webp') && (
                      <div className="tbsw-form-col">
                        <label className="tbsw-form-label" htmlFor="webp-qty">{__('WebP Quality', 'webpressor-webp-image-converter-optimizer')}</label>
                        <div className="tbsw-slider-container">
                          <input
                            type="range"
                            id="webp-qty"
                            min="10"
                            max="100"
                            value={webpQuality}
                            disabled={isLossless}
                            aria-describedby="webp-qty-desc"
                            onChange={(e) => setWebpQuality(parseInt(e.target.value, 10))}
                          />
                          <span className="tbsw-slider-value" aria-hidden="true">{webpQuality}<span className="tbsw-slider-max">/100</span></span>
                        </div>
                        <p className="tbsw-field-desc" id="webp-qty-desc">
                          {isLossless
                            ? __('Lossless mode always uses full quality, so this setting is not used.', 'webpressor-webp-image-converter-optimizer')
                            : __('Recommended quality: 80 (best size/quality tradeoff).', 'webpressor-webp-image-converter-optimizer')}
                        </p>
                      </div>
                    )}

                    {targetFormats.includes('avif') && (
                      <div className="tbsw-form-col">
                        <label className="tbsw-form-label" htmlFor="avif-qty">{__('AVIF Quality', 'webpressor-webp-image-converter-optimizer')}</label>
                        <div className="tbsw-slider-container">
                          <input
                            type="range"
                            id="avif-qty"
                            min="10"
                            max="100"
                            value={avifQuality}
                            disabled={isLossless}
                            aria-describedby="avif-qty-desc"
                            onChange={(e) => setAvifQuality(parseInt(e.target.value, 10))}
                          />
                          <span className="tbsw-slider-value" aria-hidden="true">{avifQuality}<span className="tbsw-slider-max">/100</span></span>
                        </div>
                        <p className="tbsw-field-desc" id="avif-qty-desc">
                          {isLossless
                            ? __('Lossless mode always uses full quality, so this setting is not used.', 'webpressor-webp-image-converter-optimizer')
                            : __('Recommended quality: 65 (comparable to WebP 80 but smaller size).', 'webpressor-webp-image-converter-optimizer')}
                        </p>
                      </div>
                    )}
                  </div>

                  <div className="tbsw-form-group">
                    <label className="tbsw-checkbox-inline">
                      <input
                        type="checkbox"
                        checked={skipLarger}
                        aria-describedby="tbsw-skip-larger-desc"
                        onChange={(e) => setSkipLarger(e.target.checked)}
                      />
                      <span>{__('Keep the original when the WebP/AVIF file would be larger (recommended).', 'webpressor-webp-image-converter-optimizer')}</span>
                    </label>
                    <p className="tbsw-field-desc tbsw-indented-desc" id="tbsw-skip-larger-desc">
                      {__('Some already-compressed JPEGs and simple PNGs grow when converted. Serving them would slow pages down, so the original is used instead.', 'webpressor-webp-image-converter-optimizer')}
                    </p>
                  </div>
                </section>

                {/* 2. Delivery */}
                <section className="tbsw-settings-section" aria-labelledby="tbsw-section-delivery">
                  <h3 className="tbsw-settings-heading" id="tbsw-section-delivery">{__('Delivery', 'webpressor-webp-image-converter-optimizer')}</h3>

                  <div className="tbsw-form-group" role="radiogroup" aria-labelledby="tbsw-delivery-label">
                    <span className="tbsw-form-label" id="tbsw-delivery-label">{__('Delivery Method', 'webpressor-webp-image-converter-optimizer')}</span>
                    <p className="tbsw-field-desc">{__("Choose how WebP and AVIF images reach your visitors' browsers.", 'webpressor-webp-image-converter-optimizer')}</p>

                    <div className="tbsw-radio-group">
                      <label className={`tbsw-radio-card ${deliveryMethod === 'html' ? 'selected' : ''}`}>
                        <input
                          type="radio"
                          name="delivery_method"
                          value="html"
                          checked={deliveryMethod === 'html'}
                          onChange={() => setDeliveryMethod('html')}
                        />
                        <div className="tbsw-radio-card-content">
                          <h4>{__('HTML Alteration (Default)', 'webpressor-webp-image-converter-optimizer')}</h4>
                          <p>{__("Swaps image URLs in the page for each visitor, based on what their browser supports. Needs no server setup and works on any hosting.", 'webpressor-webp-image-converter-optimizer')}</p>
                        </div>
                      </label>

                      <label className={`tbsw-radio-card ${deliveryMethod === 'picture' ? 'selected' : ''}`}>
                        <input
                          type="radio"
                          name="delivery_method"
                          value="picture"
                          checked={deliveryMethod === 'picture'}
                          onChange={() => setDeliveryMethod('picture')}
                        />
                        <div className="tbsw-radio-card-content">
                          <h4>{__('<picture> Elements (Cache-Safe)', 'webpressor-webp-image-converter-optimizer')}</h4>
                          <p>{__('Wraps images in <picture> tags that list the AVIF/WebP versions, so each browser picks the best format itself. Works with page caching plugins and CDNs. Recommended when a page cache is active.', 'webpressor-webp-image-converter-optimizer')}</p>
                        </div>
                      </label>

                      <label className={`tbsw-radio-card ${deliveryMethod === 'rewrite' ? 'selected' : ''}`}>
                        <input
                          type="radio"
                          name="delivery_method"
                          value="rewrite"
                          checked={deliveryMethod === 'rewrite'}
                          onChange={() => setDeliveryMethod('rewrite')}
                        />
                        <div className="tbsw-radio-card-content">
                          <h4>{__('Server Rewrite Rules (.htaccess)', 'webpressor-webp-image-converter-optimizer')}</h4>
                          <p>{__("Adds rules to the uploads folder's .htaccess file (Apache and LiteSpeed). Image requests get WebP/AVIF without changing your pages, including CSS background images and sliders.", 'webpressor-webp-image-converter-optimizer')}</p>
                        </div>
                      </label>
                    </div>
                    {compat.page_cache && deliveryMethod === 'html' ? (
                      <p className="tbsw-field-desc tbsw-warning-text">
                        {__('A page cache is active on this site. HTML Alteration chooses the format per visitor, so cached pages can show the wrong format to other browsers. <picture> Elements are recommended.', 'webpressor-webp-image-converter-optimizer')}
                      </p>
                    ) : null}
                  </div>

                  {deliveryMethod !== 'rewrite' && (
                    <div className="tbsw-form-group" role="radiogroup" aria-labelledby="tbsw-scope-label" aria-describedby="tbsw-scope-desc">
                      <span className="tbsw-form-label" id="tbsw-scope-label">{__('Where to Replace Images', 'webpressor-webp-image-converter-optimizer')}</span>
                      <div className="tbsw-radio-row tbsw-radio-row-wrap">
                        <label className="tbsw-radio-inline">
                          <input
                            type="radio"
                            name="html_scope"
                            value="content"
                            checked={htmlScope === 'content'}
                            onChange={() => setHtmlScope('content')}
                          />
                          <span>{__('Post content, widgets and featured images (Default)', 'webpressor-webp-image-converter-optimizer')}</span>
                        </label>
                        <label className="tbsw-radio-inline">
                          <input
                            type="radio"
                            name="html_scope"
                            value="full"
                            checked={htmlScope === 'full'}
                            onChange={() => setHtmlScope('full')}
                          />
                          <span>{__('Entire page (theme, page builders, headers and footers)', 'webpressor-webp-image-converter-optimizer')}</span>
                        </label>
                      </div>
                      <p className="tbsw-field-desc" id="tbsw-scope-desc">
                        {deliveryMethod === 'picture'
                          ? __('"Entire page" processes the final page HTML. Inline background images are not changed in <picture> mode; use server rules for those.', 'webpressor-webp-image-converter-optimizer')
                          : __('"Entire page" processes the final page HTML, including inline background images.', 'webpressor-webp-image-converter-optimizer')}
                      </p>
                    </div>
                  )}
                </section>

                {/* 3. Automation */}
                <section className="tbsw-settings-section" aria-labelledby="tbsw-section-automation">
                  <h3 className="tbsw-settings-heading" id="tbsw-section-automation">{__('Automation', 'webpressor-webp-image-converter-optimizer')}</h3>
                  <div className="tbsw-form-group">
                    <label className="tbsw-checkbox-inline">
                      <input
                        type="checkbox"
                        checked={convertOnUpload}
                        aria-describedby="tbsw-convert-upload-desc"
                        onChange={(e) => setConvertOnUpload(e.target.checked)}
                      />
                      <span>{__('Automatically convert images to WebP/AVIF when uploaded to the Media Library.', 'webpressor-webp-image-converter-optimizer')}</span>
                    </label>
                    <p className="tbsw-field-desc tbsw-indented-desc" id="tbsw-convert-upload-desc">
                      {__('Also re-converts images after you crop, rotate or scale them in the WordPress image editor.', 'webpressor-webp-image-converter-optimizer')}
                    </p>
                  </div>
                </section>

                {/* 4. Advanced (collapsed unless something is configured) */}
                <details
                  className="tbsw-settings-section tbsw-advanced"
                  open={advancedOpen}
                  onToggle={(e) => setAdvancedOpen(e.currentTarget.open)}
                >
                  <summary className="tbsw-advanced-summary">
                    <h3 className="tbsw-settings-heading">{__('Advanced', 'webpressor-webp-image-converter-optimizer')}</h3>
                    <span className="tbsw-advanced-hint">{__('Maximum upload size and exclusions', 'webpressor-webp-image-converter-optimizer')}</span>
                  </summary>

                  <div className="tbsw-form-group">
                    <label className="tbsw-form-label" htmlFor="tbsw-max-dimension">{__('Maximum Image Size for New Uploads', 'webpressor-webp-image-converter-optimizer')}</label>
                    <p className="tbsw-field-desc" id="tbsw-max-dimension-desc">
                      {__('Very large uploads are scaled down to this width/height in pixels before conversion. The full-size original is kept by WordPress. Leave 0 to use the WordPress default (2560 px). Allowed range: 640–10000.', 'webpressor-webp-image-converter-optimizer')}
                    </p>
                    <input
                      ref={maxDimensionRef}
                      type="number"
                      id="tbsw-max-dimension"
                      className={`tbsw-number-input ${showMaxDimensionError ? 'has-error' : ''}`}
                      min="0"
                      max="10000"
                      step="1"
                      inputMode="numeric"
                      value={maxDimension}
                      aria-invalid={showMaxDimensionError ? 'true' : 'false'}
                      aria-describedby={`tbsw-max-dimension-desc${showMaxDimensionError ? ' tbsw-max-dimension-error' : ''}`}
                      onChange={(e) => setMaxDimension(e.target.value)}
                      onBlur={() => setMaxDimensionTouched(true)}
                    />
                    {showMaxDimensionError && (
                      <p className="tbsw-field-error" id="tbsw-max-dimension-error">
                        <span className="dashicons dashicons-warning" aria-hidden="true"></span> {maxDimensionError}
                      </p>
                    )}
                  </div>

                  <div className="tbsw-form-group">
                    <label className="tbsw-form-label" htmlFor="tbsw-exclude-paths">{__('Excluded Folders and Files', 'webpressor-webp-image-converter-optimizer')}</label>
                    <p className="tbsw-field-desc" id="tbsw-exclude-paths-desc">
                      {__('One entry per line. Images whose path inside the uploads folder contains an entry are never converted, for example "2019/" or "logos/". Existing WebP/AVIF files of newly excluded images are kept until you remove them.', 'webpressor-webp-image-converter-optimizer')}
                    </p>
                    <textarea
                      id="tbsw-exclude-paths"
                      className="tbsw-textarea"
                      rows="4"
                      value={excludePaths}
                      placeholder={'2019/\nlogos/'}
                      aria-describedby="tbsw-exclude-paths-desc"
                      onChange={(e) => setExcludePaths(e.target.value)}
                    />
                  </div>
                </details>

                {/* Save bar: sticks to the bottom of the screen while there are unsaved changes */}
                <div className={`tbsw-save-bar ${isDirty ? 'is-dirty' : ''}`}>
                  <p className="tbsw-save-status" role="status">
                    {isDirty
                      ? __('You have unsaved changes.', 'webpressor-webp-image-converter-optimizer')
                      : __('All changes are saved.', 'webpressor-webp-image-converter-optimizer')}
                  </p>
                  <div className="tbsw-save-actions">
                    {isDirty && (
                      <button type="button" className="tbsw-btn tbsw-btn-secondary" onClick={discardSettings} disabled={isSaving}>
                        {__('Discard Changes', 'webpressor-webp-image-converter-optimizer')}
                      </button>
                    )}
                    <button type="submit" className="tbsw-btn tbsw-btn-primary" disabled={isSaving}>
                      {isSaving ? __('Saving Settings...', 'webpressor-webp-image-converter-optimizer') : __('Save Changes', 'webpressor-webp-image-converter-optimizer')}
                    </button>
                  </div>
                </div>
              </form>
            </div>

            {/* Show Nginx rewrite warning if selected but running Nginx */}
            {deliveryMethod === 'rewrite' && compat.server_type?.toLowerCase().includes('nginx') && (
              <div className="tbsw-card tbsw-notice-card">
                <h3>{__('Nginx Server Detected', 'webpressor-webp-image-converter-optimizer')}</h3>
                <p>{__("Nginx doesn't read .htaccess configuration files. To apply server rewrite rules, you must manually inject the following rules into your Nginx virtual host configuration block:", 'webpressor-webp-image-converter-optimizer')}</p>
                <pre className="tbsw-code-block">{NGINX_RULES}</pre>
                <button type="button" className="tbsw-btn tbsw-btn-secondary" onClick={copyNginxRules}>
                  <span className={`dashicons ${nginxCopied ? 'dashicons-yes' : 'dashicons-clipboard'}`} aria-hidden="true"></span>
                  {nginxCopied ? __('Copied', 'webpressor-webp-image-converter-optimizer') : __('Copy Rules', 'webpressor-webp-image-converter-optimizer')}
                </button>
              </div>
            )}
          </div>
        )}

        {/* STATUS TAB */}
        {activeTab === 'status' && (
          <div className="tbsw-tab-panel">
            <div className="tbsw-card">
              <h2>{__('Server Compatibility', 'webpressor-webp-image-converter-optimizer')}</h2>
              <p className="tbsw-section-desc">
                {__('Checks the server features WebPressor needs to create WebP and AVIF files.', 'webpressor-webp-image-converter-optimizer')}
              </p>

              <div className="tbsw-table-scroll">
              <table className="tbsw-status-table">
                <thead>
                  <tr>
                    <th scope="col">{__('Requirement Check', 'webpressor-webp-image-converter-optimizer')}</th>
                    <th scope="col">{__('Status', 'webpressor-webp-image-converter-optimizer')}</th>
                    <th scope="col">{__('Detail', 'webpressor-webp-image-converter-optimizer')}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>{__('GD Extension Installed', 'webpressor-webp-image-converter-optimizer')}</td>
                    <td>
                      <span className={`tbsw-status-pill ${compat.gd_supported ? 'success' : 'error'}`}>
                        {compat.gd_supported ? __('Active', 'webpressor-webp-image-converter-optimizer') : __('Missing', 'webpressor-webp-image-converter-optimizer')}
                      </span>
                    </td>
                    <td>{compat.gd_supported ? __('Required for standard PHP image processing.', 'webpressor-webp-image-converter-optimizer') : __('Required for standard PHP image processing. Ask your hosting provider to enable the PHP GD extension.', 'webpressor-webp-image-converter-optimizer')}</td>
                  </tr>
                  <tr>
                    <td>{__('WebP Conversion Support', 'webpressor-webp-image-converter-optimizer')}</td>
                    <td>
                      <span className={`tbsw-status-pill ${compat.webp_supported ? 'success' : 'error'}`}>
                        {compat.webp_supported ? __('Available', 'webpressor-webp-image-converter-optimizer') : __('Unavailable', 'webpressor-webp-image-converter-optimizer')}
                      </span>
                    </td>
                    <td>{compat.webp_supported ? __('GD must be compiled with WebP support.', 'webpressor-webp-image-converter-optimizer') : __('GD must be compiled with WebP support. Ask your hosting provider for a PHP version with WebP support in GD.', 'webpressor-webp-image-converter-optimizer')}</td>
                  </tr>
                  <tr>
                    <td>{__('AVIF Conversion Support', 'webpressor-webp-image-converter-optimizer')}</td>
                    <td>
                      <span className={`tbsw-status-pill ${compat.avif_supported ? 'success' : 'warning'}`}>
                        {compat.avif_supported ? __('Available', 'webpressor-webp-image-converter-optimizer') : __('Unavailable', 'webpressor-webp-image-converter-optimizer')}
                      </span>
                    </td>
                    <td>{compat.avif_supported ? __('Requires PHP 8.1+ and GD compiled with AVIF options.', 'webpressor-webp-image-converter-optimizer') : __('Optional. Requires PHP 8.1 or newer with AVIF support in GD. WebP keeps working without it.', 'webpressor-webp-image-converter-optimizer')}</td>
                  </tr>
                  <tr>
                    <td>{__('Uploads Directory Writable', 'webpressor-webp-image-converter-optimizer')}</td>
                    <td>
                      <span className={`tbsw-status-pill ${compat.upload_writable ? 'success' : 'error'}`}>
                        {compat.upload_writable ? __('Yes', 'webpressor-webp-image-converter-optimizer') : __('No', 'webpressor-webp-image-converter-optimizer')}
                      </span>
                    </td>
                    <td>{compat.upload_writable ? __('Required to write optimized images and the .htaccess file.', 'webpressor-webp-image-converter-optimizer') : __('Required to write optimized images and the .htaccess file. Ask your hosting provider to make the wp-content/uploads folder writable.', 'webpressor-webp-image-converter-optimizer')}</td>
                  </tr>
                  <tr>
                    <td>{__('Detected Server Type', 'webpressor-webp-image-converter-optimizer')}</td>
                    <td className="tbsw-server-type">
                      {compat.server_type || __('Unknown', 'webpressor-webp-image-converter-optimizer')}
                    </td>
                    <td>{__('Used for server rewrite rules implementation.', 'webpressor-webp-image-converter-optimizer')}</td>
                  </tr>
                </tbody>
              </table>
              </div>
            </div>

            <div className="tbsw-card">
              <h2>{__('Delivery Self-Test', 'webpressor-webp-image-converter-optimizer')}</h2>
              <p className="tbsw-section-desc">
                {__('Checks that a converted image is actually delivered by your server with the saved delivery method. The test only requests files from your own site.', 'webpressor-webp-image-converter-optimizer')}
              </p>
              <button type="button" className="tbsw-btn tbsw-btn-primary" onClick={runDeliveryTest} disabled={isTesting}>
                <span className="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                {isTesting ? __('Testing…', 'webpressor-webp-image-converter-optimizer') : __('Run Delivery Test', 'webpressor-webp-image-converter-optimizer')}
              </button>

              <div aria-live="polite">
                {testResults && (
                  <div className="tbsw-table-scroll">
                    <table className="tbsw-status-table">
                      <thead>
                        <tr>
                          <th scope="col">{__('Check', 'webpressor-webp-image-converter-optimizer')}</th>
                          <th scope="col">{__('Result', 'webpressor-webp-image-converter-optimizer')}</th>
                          <th scope="col">{__('Detail', 'webpressor-webp-image-converter-optimizer')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {testResults.checks.map((check, index) => (
                          <tr key={index}>
                            <td>{check.label}</td>
                            <td>
                              <span className={`tbsw-status-pill ${statusPillClass[check.status] || 'info'}`}>
                                {statusPillLabels[check.status] || check.status}
                              </span>
                            </td>
                            <td>{check.message}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                    {testResults.sample && (
                      <p className="tbsw-field-desc tbsw-test-sample">
                        {sprintf(/* translators: %s: image URL. */ __('Tested image: %s', 'webpressor-webp-image-converter-optimizer'), testResults.sample)}
                      </p>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default App;
