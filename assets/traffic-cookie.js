(function() {
    function getUTMParameters() {
        const utmParameters = {};
        const utmKeys = ['utm_source','utm_medium','utm_campaign','utm_term','utm_adgroup','utm_content'];
        try {
            const urlParams = new URLSearchParams(window.location.search || '');
            for (const key of utmKeys) {
                const value = urlParams.get(key);
                if (value) utmParameters[key] = value || '';
            }
            const gclid = urlParams.get('gclid');
            const fbclid = urlParams.get('fbclid');
            if (gclid || fbclid) utmParameters.clid = gclid || fbclid || '';
        } catch (_) {}
        return utmParameters;
    }

    function getTrafficSource(referrer) {
        const trafficSources = {
            'google.com': (url) => url.searchParams.get('utm_medium') === 'cpc' ? 'Google Ads' : 'Google Organic',
            'facebook.com': (url) => url.searchParams.get('utm_medium') === 'cpc' ? 'Facebook Ads' : 'Facebook Organic',
            'bing.com': () => 'Bing',
            'linkedin.com': () => 'LinkedIn',
            'tiktok.com': () => 'TikTok',
            'youtube.com': () => 'YouTube'
        };
        if (!referrer) return 'Direct';
        try {
            const url = new URL(referrer);
            const hostname = url.hostname || '';
            for (const domain in trafficSources) {
                if ((hostname || '').includes(domain)) return trafficSources[domain](url);
            }
            return 'Other';
        } catch (_) {
            return 'Invalid Referrer';
        }
    }

    function getCookie(name) {
        return document.cookie
            .split('; ')
            .find(row => row.startsWith(name + '='))
            ?.split('=')[1];
    }

    function setReferrerSourceCookie() {
        if (getCookie('referrer_source')) return;

        const utmParameters = getUTMParameters();
        const trafficSource = getTrafficSource(document.referrer || '');

        const data = {
            traffic_source: trafficSource || 'Direct',
            utm_source: utmParameters.utm_source || '',
            utm_medium: utmParameters.utm_medium || '',
            utm_campaign: utmParameters.utm_campaign || '',
            utm_term: utmParameters.utm_term || '',
            utm_adgroup: utmParameters.utm_adgroup || '',
            utm_content: utmParameters.utm_content || '',
            clid: utmParameters.clid || ''
        };

        try {
            const serializedData = encodeURIComponent(JSON.stringify(data));
            const now = new Date();
            now.setTime(now.getTime() + 30*24*60*60*1000);
            const expires = "expires=" + now.toUTCString();
            const secureFlag = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = `referrer_source=${serializedData}; path=/; ${expires}; SameSite=Lax${secureFlag}`;
        } catch (_) {}
    }

    document.addEventListener('DOMContentLoaded', setReferrerSourceCookie);
})();
