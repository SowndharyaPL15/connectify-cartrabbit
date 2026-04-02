/* app.js — shared utilities */

// Global AJAX helper
window.ajax = function(url, method, data, isFormData = false) {
    const opts = {
        method: method.toUpperCase(),
        headers: {
            'X-CSRF-TOKEN': window.APP.csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    if (isFormData) {
        opts.body = data;
    } else if (data) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(data);
    }

    return fetch(url, opts).then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    });
};

// Auto-resize textarea
window.autoResize = function(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
};
