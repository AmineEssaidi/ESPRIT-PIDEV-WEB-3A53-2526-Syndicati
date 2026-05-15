(function () {
    const endpoint = '/sync/changes';
    const storageKey = 'syndicati-sync-versions';
    const pollMs = 18000;
    let timer = null;
    let inFlight = false;

    const pageTopics = () => {
        const path = window.location.pathname.toLowerCase();
        const topics = [];
        if (path.includes('/forum')) topics.push('forum');
        if (path.includes('/residence')) topics.push('residence');
        if (path.includes('/evenement') || path.includes('/event')) topics.push('evenement');
        return topics;
    };

    const isBusy = () => {
        const active = document.activeElement;
        if (active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)) return true;
        if (document.querySelector('.modal.show, .glass-modal.show, [data-modal-open="true"]')) return true;
        return Boolean(document.querySelector('form[data-dirty="true"]'));
    };

    const notify = (message) => {
        if (window.pushNotif) {
            window.pushNotif('Syndicati updated', message, 'INFO');
        }
    };

    const handleVersions = (versions) => {
        const previous = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
        sessionStorage.setItem(storageKey, JSON.stringify(versions));

        if (!Object.keys(previous).length) return;

        const changed = pageTopics().filter(topic => previous[topic] && versions[topic] && previous[topic] !== versions[topic]);
        if (!changed.length) return;

        window.dispatchEvent(new CustomEvent('syndicati:data-changed', { detail: { topics: changed, versions } }));

        if (isBusy()) {
            notify('New data is available. Finish your edit, then refresh.');
            return;
        }

        window.location.reload();
    };

    const poll = () => {
        if (document.hidden || inFlight || !pageTopics().length) return;
        inFlight = true;

        fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
            .then(response => response.ok ? response.json() : null)
            .then(payload => {
                if (payload && payload.status === 'ok' && payload.versions) {
                    handleVersions(payload.versions);
                }
            })
            .catch(() => {})
            .finally(() => { inFlight = false; });
    };

    document.addEventListener('input', (event) => {
        const form = event.target && event.target.closest ? event.target.closest('form') : null;
        if (form) form.dataset.dirty = 'true';
    });

    document.addEventListener('submit', (event) => {
        if (event.target && event.target.matches('form')) {
            event.target.dataset.dirty = 'false';
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });

    document.addEventListener('DOMContentLoaded', () => {
        if (!pageTopics().length) return;
        poll();
        timer = window.setInterval(poll, pollMs);
    });

    window.addEventListener('beforeunload', () => {
        if (timer) window.clearInterval(timer);
    });
})();
