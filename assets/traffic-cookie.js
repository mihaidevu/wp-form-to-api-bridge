(function() {
    function getUTMParameters() {
        const utmParameters = {
            utm_source: '', utm_medium: '', utm_campaign: '',
            utm_term: '', utm_adgroup: '', utm_content: '', clid: ''
        };

        try {
            const urlParams = new URLSearchParams(window.location.search);
            const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_adgroup', 'utm_content'];
            
            keys.forEach(key => {
                const val = urlParams.get(key);
                if (val) utmParameters[key] = val;
            });

            const gclid = urlParams.get('gclid');
            const fbclid = urlParams.get('fbclid');
            const msclkid = urlParams.get('msclkid');

            if (gclid) utmParameters.clid = 'gclid:' + gclid;
            else if (fbclid) utmParameters.clid = 'fbclid:' + fbclid;
            else if (msclkid) utmParameters.clid = 'msclkid:' + msclkid;
            
        } catch (_) {}
        return utmParameters;
    }

    function getTrafficSource(utm, referrer) {
        if (utm.clid) {
            if (utm.clid.startsWith('gclid:')) return 'Google Ads';
            if (utm.clid.startsWith('fbclid:')) return 'Facebook Ads';
            if (utm.clid.startsWith('msclkid:')) return 'Bing Ads';
        }

        if (utm.utm_medium) {
            const medium = utm.utm_medium.toLowerCase();
            if (['cpc', 'ppc', 'paid'].includes(medium)) {
                const source = utm.utm_source.toLowerCase();
                if (source.includes('google')) return 'Google Ads';
                if (source.includes('facebook') || source.includes('fb')) return 'Facebook Ads';
                return 'Paid Traffic';
            }
        }

        if (!referrer) return 'Direct';

        try {
            const refUrl = new URL(referrer);
            const host = refUrl.hostname.toLowerCase().replace(/^www\./, '');
            const isFrom = (domain) => host === domain || host.endsWith('.' + domain);

            if (isFrom('google.com') || host.includes('google.')) return 'Google Organic';
            if (isFrom('facebook.com')) return 'Facebook Organic';
            if (isFrom('bing.com')) return 'Bing Organic';
            if (isFrom('linkedin.com')) return 'LinkedIn Organic';
            if (isFrom('t.co')) return 'Twitter Organic';
            if (isFrom('tiktok.com')) return 'TikTok Organic';
            if (isFrom('instagram.com')) return 'Instagram Organic';

            return host; 
        } catch (_) {
            return 'Invalid Referrer';
        }
    }

    const setReferrerSourceCookie = () => {
        const getCookie = (name) => {
            const row = document.cookie.split('; ').find(r => r.startsWith(name + '='));
            return row ? row.split('=')[1] : null;
        };

        if (getCookie('referrer_source')) return;

        const utms = getUTMParameters();
        const source = getTrafficSource(utms, document.referrer);
        const finalClid = utms.clid ? utms.clid.split(':')[1] : '';

        const data = {
            traffic_source: source,
            utm_source: utms.utm_source,
            utm_medium: utms.utm_medium,
            utm_campaign: utms.utm_campaign,
            utm_term: utms.utm_term,
            utm_adgroup: utms.utm_adgroup,
            utm_content: utms.utm_content,
            clid: finalClid
        };

        try {
            const serializedData = encodeURIComponent(JSON.stringify(data));
            const expires = new Date(Date.now() + 30*24*60*60*1000).toUTCString();
            const secureFlag = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = `referrer_source=${serializedData}; path=/; expires=${expires}; SameSite=Lax${secureFlag}`;
        } catch (e) {}
    };

    setReferrerSourceCookie();
})();