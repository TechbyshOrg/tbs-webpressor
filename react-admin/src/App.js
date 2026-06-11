import React, { useEffect, useRef, useState } from 'react';
import './App.css';

function App({ wpData = {}, initialTab = 'dashboard' }) {
  const [activeTab, setActiveTab] = useState(initialTab);
  
  // Settings State
  const [targetFormats, setTargetFormats] = useState(wpData.settings?.target_formats || ['webp']);
  const [webpQuality, setWebpQuality] = useState(wpData.settings?.webp_quality || 80);
  const [avifQuality, setAvifQuality] = useState(wpData.settings?.avif_quality || 65);
  const [deliveryMethod, setDeliveryMethod] = useState(wpData.settings?.delivery_method || 'html');
  const [compressionMode, setCompressionMode] = useState(wpData.settings?.compression_mode || 'lossy');
  const [convertOnUpload, setConvertOnUpload] = useState(wpData.settings?.convert_on_upload === 1);
  
  // Library Statistics State
  const [count, setCount] = useState(0);
  const [pendingCount, setPendingCount] = useState(0);
  const [totalOriginal, setTotalOriginal] = useState(wpData.stats?.total_original || 0);
  const [totalOptimized, setTotalOptimized] = useState(wpData.stats?.total_optimized || 0);
  
  // Processing States
  const [isConverting, setIsConverting] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [logs, setLogs] = useState([]);
  const stopConversion = useRef(true);
  
  // Toast notifications
  const [toast, setToast] = useState(null);
  
  const consoleEndRef = useRef(null);

  // Helper to format bytes
  const formatBytes = (bytes, decimals = 2) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
  };

  // Helper to add toast
  const showToast = (type, message) => {
    setToast({ type, message });
    setTimeout(() => {
      setToast(null);
    }, 4000);
  };

  // Add entry to console log
  const addLog = (text) => {
    const time = new Date().toLocaleTimeString();
    setLogs((prevLogs) => [...prevLogs, `[${time}] ${text}`]);
  };

  // Fetch counts from server
  const fetchMediaCount = async () => {
    try {
      const response = await fetch(wpData.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'tbswebpressor_get_media_count',
          nonce: wpData.nonce,
        })
      });
      const result = await response.json();
      if (result.success) setCount(result.data.count);
    } catch (error) {
      console.error(error);
    }
  };

  const fetchPendingMediaCount = async () => {
    try {
      const response = await fetch(wpData.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'tbswebpressor_get_pending_media_count',
          nonce: wpData.nonce,
        })
      });
      const result = await response.json();
      if (result.success) setPendingCount(result.data.count);
    } catch (error) {
      console.error(error);
    }
  };

  const fetchStats = async () => {
    try {
      const response = await fetch(wpData.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'tbswebpressor_get_stats',
          nonce: wpData.nonce,
        })
      });
      const result = await response.json();
      if (result.success) {
        setTotalOriginal(result.data.total_original);
        setTotalOptimized(result.data.total_optimized);
      }
    } catch (error) {
      console.error(error);
    }
  };

  // Conversion process
  const startConverter = () => {
    setIsConverting(true);
    stopConversion.current = false;
    setLogs([]);
    addLog('Starting bulk conversion process...');

    const runBatch = async (page = 1) => {
      if (stopConversion.current) {
        addLog('Conversion process stopped by user.');
        setIsConverting(false);
        return;
      }

      addLog(`Processing batch #${page}...`);
      try {
        const response = await fetch(wpData.ajax_url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'tbswebpressor_start_conversion',
            nonce: wpData.nonce,
            page: page
          })
        });

        if (!response.ok) throw new Error('Network error');
        
        const res = await response.json();
        if (!res.success) {
          addLog(`Error: ${res.data?.message || 'Unknown error occurred'}`);
          setIsConverting(false);
          return;
        }

        const data = res.data;
        if (data.processed && data.processed.length > 0) {
          data.processed.forEach(img => {
            const savings = img.original_size > 0 
              ? Math.round(((img.original_size - img.optimized_size) / img.original_size) * 100) 
              : 0;
            addLog(`✓ ${img.filename} optimized: Original ${formatBytes(img.original_size)} → Optimized ${formatBytes(img.optimized_size)} (-${savings}%)`);
          });
        } else {
          addLog('Batch was empty or images already processed.');
        }

        // Refresh numbers after each batch
        await fetchMediaCount();
        await fetchPendingMediaCount();
        await fetchStats();

        if (data.hasMorePages && !stopConversion.current) {
          setTimeout(() => runBatch(page + 1), 800);
        } else {
          addLog('🎉 All images processed successfully! Bulk conversion complete.');
          setIsConverting(false);
          showToast('success', 'Conversion completed successfully!');
        }
      } catch (error) {
        addLog(`Fatal Error: ${error.message}`);
        setIsConverting(false);
        showToast('error', 'Error during conversion process.');
      }
    };

    runBatch();
  };

  const stopConverter = () => {
    stopConversion.current = true;
    addLog('Stopping process after current batch...');
  };

  const resetConvertedMedia = async () => {
    if (!window.confirm('Are you sure you want to delete all WebP/AVIF images and restore original images? This cannot be undone.')) {
      return;
    }

    addLog('Resetting conversions and deleting files...');
    try {
      const response = await fetch(wpData.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'tbswebpressor_reset_conversion',
          nonce: wpData.nonce,
        })
      });
      const result = await response.json();
      if (result.success) {
        addLog('Reset successful! Deleted all converted formats.');
        showToast('success', 'Conversions reset successfully!');
        setTotalOriginal(0);
        setTotalOptimized(0);
        setLogs([]);
        await fetchMediaCount();
        await fetchPendingMediaCount();
      } else {
        showToast('error', 'Failed to reset conversions.');
      }
    } catch (error) {
      showToast('error', 'Network error during reset.');
    }
  };

  // Save Settings
  const saveSettings = async (e) => {
    e.preventDefault();
    setIsSaving(true);
    
    try {
      const params = new URLSearchParams({
        action: 'tbswebpressor_save_settings',
        nonce: wpData.nonce,
        webp_quality: webpQuality,
        avif_quality: avifQuality,
        delivery_method: deliveryMethod,
        compression_mode: compressionMode,
        convert_on_upload: convertOnUpload ? 1 : 0
      });
      
      targetFormats.forEach(format => {
        params.append('target_formats[]', format);
      });

      const response = await fetch(wpData.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
      });
      const result = await response.json();
      if (result.success) {
        showToast('success', 'Settings saved successfully!');
      } else {
        showToast('error', 'Failed to save settings.');
      }
    } catch (error) {
      showToast('error', 'Network error while saving settings.');
    } finally {
      setIsSaving(false);
    }
  };

  // Toggle formats selection
  const handleFormatToggle = (format) => {
    if (targetFormats.includes(format)) {
      if (targetFormats.length === 1) {
        showToast('info', 'You must select at least one format.');
        return;
      }
      setTargetFormats(targetFormats.filter(f => f !== format));
    } else {
      setTargetFormats([...targetFormats, format]);
    }
  };

  useEffect(() => {
    fetchMediaCount();
    fetchPendingMediaCount();
    fetchStats();
  }, [wpData.ajax_url, wpData.nonce]);

  useEffect(() => {
    if (consoleEndRef.current) {
      consoleEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [logs]);

  // Derived variables
  const completedCount = Math.max(0, count - pendingCount);
  const progressPercentage = count > 0 ? Math.round((completedCount / count) * 100) : 0;
  const totalSaved = Math.max(0, totalOriginal - totalOptimized);
  const savingsPercent = totalOriginal > 0 ? Math.round((totalSaved / totalOriginal) * 100) : 0;

  // Render compatibility checkers
  const compat = wpData.compatibility || {};

  return (
    <div className="wrap tbsw-wrap">
      {/* Toast Alert */}
      {toast && (
        <div className={`tbsw-toast tbsw-toast-${toast.type}`}>
          <span className="dashicons dashicons-info"></span>
          <p>{toast.message}</p>
        </div>
      )}

      {/* Title Header */}
      <div className="tbsw-header">
        <div className="tbsw-header-title">
          <h1>WebPressor</h1>
          <span className="tbsw-version">v{wpData.version || '2.0.0'}</span>
        </div>
        <p className="tbsw-subtitle">Next-Generation Image Compression & Optimization for WordPress</p>
      </div>

      {/* Tabs Menu */}
      <nav className="tbsw-nav-tabs">
        <button 
          className={`tbsw-nav-item ${activeTab === 'dashboard' ? 'active' : ''}`}
          onClick={() => setActiveTab('dashboard')}
        >
          <span className="dashicons dashicons-dashboard"></span> Dashboard
        </button>
        <button 
          className={`tbsw-nav-item ${activeTab === 'settings' ? 'active' : ''}`}
          onClick={() => setActiveTab('settings')}
        >
          <span className="dashicons dashicons-admin-generic"></span> Settings
        </button>
        <button 
          className={`tbsw-nav-item ${activeTab === 'status' ? 'active' : ''}`}
          onClick={() => setActiveTab('status')}
        >
          <span className="dashicons dashicons-analytics"></span> System Status
        </button>
      </nav>

      {/* Content Area */}
      <div className="tbsw-content">
        
        {/* DASHBOARD TAB */}
        {activeTab === 'dashboard' && (
          <div className="tbsw-tab-panel">
            {/* Top Cards Grid */}
            <div className="tbsw-grid">
              
              {/* Card 1: Storage Savings */}
              <div className="tbsw-card tbsw-stat-card tbsw-card-gradient-blue">
                <div className="tbsw-card-icon">
                  <span className="dashicons dashicons-admin-media"></span>
                </div>
                <div className="tbsw-card-info">
                  <h3>Storage Saved</h3>
                  <div className="tbsw-stat-main">{formatBytes(totalSaved)}</div>
                  <p className="tbsw-stat-sub">
                    Saved {savingsPercent}% of total images volume ({formatBytes(totalOriginal)} original size)
                  </p>
                  <div className="tbsw-visual-bar">
                    <div className="tbsw-visual-bar-fill" style={{ width: `${savingsPercent}%` }}></div>
                  </div>
                </div>
              </div>

              {/* Card 2: Optimization Progress */}
              <div className="tbsw-card tbsw-stat-card tbsw-card-gradient-purple">
                <div className="tbsw-card-icon">
                  <span className="dashicons dashicons-update"></span>
                </div>
                <div className="tbsw-card-info">
                  <h3>Library Optimized</h3>
                  <div className="tbsw-stat-main">{progressPercentage}%</div>
                  <p className="tbsw-stat-sub">
                    Optimized {completedCount} of {count} image assets ({pendingCount} remaining)
                  </p>
                  <div className="tbsw-visual-bar">
                    <div className="tbsw-visual-bar-fill" style={{ width: `${progressPercentage}%` }}></div>
                  </div>
                </div>
              </div>

              {/* Card 3: Formats Active */}
              <div className="tbsw-card tbsw-stat-card tbsw-card-gradient-green">
                <div className="tbsw-card-icon">
                  <span className="dashicons dashicons-image-filter"></span>
                </div>
                <div className="tbsw-card-info">
                  <h3>Active Outputs</h3>
                  <div className="tbsw-formats-list">
                    {targetFormats.map(format => (
                      <span key={format} className="tbsw-badge-format">
                        {format.toUpperCase()}
                      </span>
                    ))}
                  </div>
                  <p className="tbsw-stat-sub">
                    Method: {deliveryMethod === 'rewrite' ? 'Server Rules (.htaccess)' : 'HTML Alterations'}
                  </p>
                </div>
              </div>

            </div>

            {/* Bulk Optimization Controller */}
            <div className="tbsw-card">
              <h2>Bulk Optimizer</h2>
              <p className="tbsw-section-desc">
                Convert all existing JPEG and PNG images in your WordPress Media Library into WebP and AVIF.
              </p>

              <div className="tbsw-bulk-controls">
                <div className="tbsw-progress-section">
                  <div className="tbsw-progress-label">
                    <span>Overall Library Status</span>
                    <span>{completedCount} / {count} Images</span>
                  </div>
                  <div className="tbsw-progress-bar-large">
                    <div className="tbsw-progress-fill-large" style={{ width: `${progressPercentage}%` }}></div>
                  </div>
                </div>

                <div className="tbsw-actions-row">
                  {!isConverting ? (
                    <button 
                      className="tbsw-btn tbsw-btn-primary" 
                      onClick={startConverter}
                      disabled={pendingCount === 0}
                    >
                      <span className="dashicons dashicons-images-alt"></span> Start Optimization
                    </button>
                  ) : (
                    <button className="tbsw-btn tbsw-btn-danger" onClick={stopConverter}>
                      <span className="dashicons dashicons-controls-pause"></span> Pause Optimization
                    </button>
                  )}
                  
                  <button 
                    className="tbsw-btn tbsw-btn-secondary" 
                    onClick={resetConvertedMedia}
                    disabled={isConverting || completedCount === 0}
                  >
                    <span className="dashicons dashicons-trash"></span> Reset Conversions
                  </button>
                </div>
              </div>

              {/* Log Console Output */}
              {(logs.length > 0 || isConverting) && (
                <div className="tbsw-console-card">
                  <div className="tbsw-console-header">
                    <span>Live Optimization Log</span>
                    {isConverting && <div className="tbsw-pulse-loader"></div>}
                  </div>
                  <div className="tbsw-console-logs">
                    {logs.map((log, index) => (
                      <div key={index} className="tbsw-log-line">{log}</div>
                    ))}
                    <div ref={consoleEndRef} />
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* SETTINGS TAB */}
        {activeTab === 'settings' && (
          <div className="tbsw-tab-panel">
            <div className="tbsw-card">
              <h2>Compression & Delivery Settings</h2>
              <p className="tbsw-section-desc">
                Customize compression behaviors, quality benchmarks, and the delivery methodology.
              </p>

              <form onSubmit={saveSettings}>
                <div className="tbsw-form-group">
                  <label className="tbsw-form-label">Next-Gen Image Formats</label>
                  <p className="tbsw-field-desc">Choose which formats you want to generate. Serving AVIF first is recommended due to its superior compression ratio.</p>
                  
                  <div className="tbsw-checkbox-grid">
                    <div 
                      className={`tbsw-selectable-card ${targetFormats.includes('webp') ? 'selected' : ''}`}
                      onClick={() => handleFormatToggle('webp')}
                    >
                      <div className="tbsw-selectable-header">
                        <span className="dashicons dashicons-yes"></span>
                        <h4>WebP Format</h4>
                      </div>
                      <p>High compatibility, universally supported by all modern browsers. Replaces PNG/JPG with ~30% saving.</p>
                    </div>

                    <div 
                      className={`tbsw-selectable-card ${!compat.avif_supported ? 'disabled' : ''} ${targetFormats.includes('avif') ? 'selected' : ''}`}
                      onClick={() => compat.avif_supported && handleFormatToggle('avif')}
                    >
                      <div className="tbsw-selectable-header">
                        <span className="dashicons dashicons-yes"></span>
                        <h4>AVIF Format</h4>
                      </div>
                      <p>Next-gen AV1 codec compression. Up to 50% smaller files than JPEG, and 20% smaller than WebP.</p>
                      {!compat.avif_supported && (
                        <span className="tbsw-error-badge">Not supported by server GD</span>
                      )}
                    </div>
                  </div>
                </div>

                <div className="tbsw-form-row">
                  {targetFormats.includes('webp') && (
                    <div className="tbsw-form-col">
                      <label className="tbsw-form-label" htmlFor="webp-qty">WebP Quality</label>
                      <div className="tbsw-slider-container">
                        <input 
                          type="range" 
                          id="webp-qty"
                          min="10" 
                          max="100" 
                          value={webpQuality} 
                          onChange={(e) => setWebpQuality(parseInt(e.target.value))}
                        />
                        <span className="tbsw-slider-value">{webpQuality}%</span>
                      </div>
                      <p className="tbsw-field-desc">Recommended quality: 80 (best size/quality tradeoff).</p>
                    </div>
                  )}

                  {targetFormats.includes('avif') && (
                    <div className="tbsw-form-col">
                      <label className="tbsw-form-label" htmlFor="avif-qty">AVIF Quality</label>
                      <div className="tbsw-slider-container">
                        <input 
                          type="range" 
                          id="avif-qty"
                          min="10" 
                          max="100" 
                          value={avifQuality} 
                          onChange={(e) => setAvifQuality(parseInt(e.target.value))}
                        />
                        <span className="tbsw-slider-value">{avifQuality}%</span>
                      </div>
                      <p className="tbsw-field-desc">Recommended quality: 65 (comparable to WebP 80 but smaller size).</p>
                    </div>
                  )}
                </div>

                <hr className="tbsw-divider" />

                <div className="tbsw-form-group">
                  <label className="tbsw-form-label">Delivery Method</label>
                  <p className="tbsw-field-desc">Select how the plugin will serve next-gen formats to compatible browsers.</p>
                  
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
                        <h4>HTML Alteration (Default)</h4>
                        <p>Rewrites image URLs inside the HTML post content on-the-fly. Safe, requires no server setup, and operates on any shared hosting.</p>
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
                        <h4>Server Rewrite Rules (.htaccess)</h4>
                        <p>Injects Apache/LiteSpeed redirection rules into the upload directory. Intercepts incoming requests and serves next-gen images without modifying HTML. Works with background CSS and sliders.</p>
                      </div>
                    </label>
                  </div>
                </div>

                <div className="tbsw-form-group">
                  <label className="tbsw-form-label">Compression Mode</label>
                  <div className="tbsw-radio-row">
                    <label className="tbsw-radio-inline">
                      <input 
                        type="radio" 
                        name="compression_mode" 
                        value="lossy" 
                        checked={compressionMode === 'lossy'}
                        onChange={() => setCompressionMode('lossy')}
                      />
                      <span>Lossy (Highly Recommended)</span>
                    </label>
                    <label className="tbsw-radio-inline">
                      <input 
                        type="radio" 
                        name="compression_mode" 
                        value="lossless" 
                        checked={compressionMode === 'lossless'}
                        onChange={() => setCompressionMode('lossless')}
                      />
                      <span>Lossless (Forces 100% Quality)</span>
                    </label>
                  </div>
                </div>

                <div className="tbsw-form-group">
                  <label className="tbsw-checkbox-inline">
                    <input 
                      type="checkbox" 
                      checked={convertOnUpload} 
                      onChange={(e) => setConvertOnUpload(e.target.checked)} 
                    />
                    <span>Automatically convert images to WebP/AVIF when uploaded to the Media Library.</span>
                  </label>
                </div>

                <div className="tbsw-form-submit">
                  <button type="submit" className="tbsw-btn tbsw-btn-primary" disabled={isSaving}>
                    {isSaving ? 'Saving Settings...' : 'Save Configuration'}
                  </button>
                </div>
              </form>
            </div>
            
            {/* Show Nginx rewrite warning if selected but running Nginx */}
            {deliveryMethod === 'rewrite' && compat.server_type?.toLowerCase().includes('nginx') && (
              <div className="tbsw-card tbsw-notice-card">
                <h3>Nginx Server Detected</h3>
                <p>Nginx doesn't read <code>.htaccess</code> configuration files. To apply server rewrite rules, you must manually inject the following rules into your Nginx virtual host configuration block:</p>
                <pre className="tbsw-code-block">
{`# Place this block inside your server config
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
}`}
                </pre>
              </div>
            )}
          </div>
        )}

        {/* STATUS TAB */}
        {activeTab === 'status' && (
          <div className="tbsw-tab-panel">
            <div className="tbsw-card">
              <h2>System Compatibility & Diagnostic</h2>
              <p className="tbsw-section-desc">
                Review server libraries and folder write permissions required for the image generator.
              </p>

              <table className="tbsw-status-table">
                <thead>
                  <tr>
                    <th>Requirement Check</th>
                    <th>Status</th>
                    <th>Detail</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>GD Extension Installed</td>
                    <td>
                      <span className={`tbsw-status-pill ${compat.gd_supported ? 'success' : 'error'}`}>
                        {compat.gd_supported ? 'Active' : 'Missing'}
                      </span>
                    </td>
                    <td>Required for standard PHP image processing.</td>
                  </tr>
                  <tr>
                    <td>WebP Conversion Support</td>
                    <td>
                      <span className={`tbsw-status-pill ${compat.webp_supported ? 'success' : 'error'}`}>
                        {compat.webp_supported ? 'Available' : 'Unavailable'}
                      </span>
                    </td>
                    <td>GD must be compiled with WebP support.</td>
                  </tr>
                  <tr>
                    <td>AVIF Conversion Support</td>
                    <td>
                      <span className={`tbsw-status-pill ${compat.avif_supported ? 'success' : 'warning'}`}>
                        {compat.avif_supported ? 'Available' : 'Unavailable'}
                      </span>
                    </td>
                    <td>Requires PHP 8.1+ and GD compiled with AVIF options.</td>
                  </tr>
                  <tr>
                    <td>Uploads Directory Writable</td>
                    <td>
                      <span className={`tbsw-status-pill ${compat.upload_writable ? 'success' : 'error'}`}>
                        {compat.upload_writable ? 'Yes' : 'No'}
                      </span>
                    </td>
                    <td>Required to write optimized images and `.htaccess` file.</td>
                  </tr>
                  <tr>
                    <td>Detected Server Type</td>
                    <td>
                      <span className="tbsw-status-pill info">
                        {compat.server_type || 'Unknown'}
                      </span>
                    </td>
                    <td>Used for server rewrite rules implementation.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default App;