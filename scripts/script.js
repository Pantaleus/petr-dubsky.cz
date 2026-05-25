// script.js - Vylepšený JavaScript s trackingem času a bezpečnostními funkcemi

document.addEventListener('DOMContentLoaded', function() {
    
    // --- Tracking času stráveného na webu ---
    let startTime = Date.now();
    let timeOnSite = 0;
    
    // Aktualizace času každou sekundu
    setInterval(() => {
        timeOnSite = Math.floor((Date.now() - startTime) / 1000);
    }, 1000);
    
    // --- Získání rozlišení obrazovky ---
    function getScreenResolution() {
        return screen.width + 'x' + screen.height;
    }
    
    // --- Získání CSRF tokenu ---
    let csrfToken = null;
    
    async function getCSRFToken() {
        try {
            const response = await fetch('scripts/send_email.php?action=get_csrf_token');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            if (data.success && data.csrf_token) {
                csrfToken = data.csrf_token;
                console.log('CSRF token obtained successfully');
                return true;
            } else {
                console.error('Failed to get CSRF token:', data.message);
                return false;
            }
        } catch (error) {
            console.error('Error getting CSRF token:', error);
            return false;
        }
    }
    
    // Získáme CSRF token při načtení stránky
    getCSRFToken();

    // --- Mobilní Navigace Toggle ---
    const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
    const mainNav = document.querySelector('.main-nav'); 
    const body = document.body;

    if (mobileNavToggle && mainNav) {
        mobileNavToggle.addEventListener('click', function() {
            body.classList.toggle('nav-open'); 
            const isExpanded = body.classList.contains('nav-open');
            mobileNavToggle.setAttribute('aria-expanded', isExpanded);
        });
    }

    // --- Plynulý Scroll pro Anchor Linky v Navigaci ---
    const navLinks = document.querySelectorAll('.main-nav a[href^="#"], .logo a[href^="#"], .hero-buttons a[href^="#"]');

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            let targetId = this.getAttribute('href');
            if (targetId === '#') targetId = '#home'; 
            
            const targetElement = document.querySelector(targetId);

            if (targetElement) {
                const headerOffset = document.getElementById('header') ? document.getElementById('header').offsetHeight : 0;
                const elementPosition = targetElement.getBoundingClientRect().top + window.pageYOffset;
                const offsetPosition = elementPosition - headerOffset - 10; 

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });

                if (body.classList.contains('nav-open')) {
                    body.classList.remove('nav-open');
                    if(mobileNavToggle) mobileNavToggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
    });

    // --- Aktivní stav navigace při scrollu ---
    const sections = document.querySelectorAll('main section[id]');
    const navLiAnchors = document.querySelectorAll('.main-nav ul li a[href^="#"]'); 

    if (sections.length > 0 && navLiAnchors.length > 0) {
        window.addEventListener('scroll', () => {
            let current = '';
            const headerHeight = document.getElementById('header') ? document.getElementById('header').offsetHeight : 0;
            const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;

            sections.forEach(section => {
                const sectionTop = section.offsetTop - headerHeight - 60;
                if (scrollPosition >= sectionTop) {
                    current = section.getAttribute('id');
                }
            });
            
            if (scrollPosition < sections[0].offsetTop - headerHeight - 60) {
                 current = 'home';
            }

            navLiAnchors.forEach(a => {
                a.classList.remove('active');
                if (a.getAttribute('href').substring(1) === current) {
                    a.classList.add('active');
                }
            });
        });
        window.dispatchEvent(new Event('scroll'));
    }

    // --- Kontaktní formulář AJAX odeslání ---
    const contactForm = document.getElementById('contact-form');
    const formStatusDiv = document.getElementById('form-status');
    const submitButtonContact = document.getElementById('submit-button');

    const siteLang = typeof currentSiteLanguage !== 'undefined' ? currentSiteLanguage : 'en';

    const getLangText = (key, fallback) => {
        if (typeof jsLang !== 'undefined' && jsLang[key]) {
            return jsLang[key];
        }
        return fallback;
    };

    if (contactForm && formStatusDiv && submitButtonContact) {
        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            let isValid = true;
            const nameInput = contactForm.querySelector('#name');
            const emailInput = contactForm.querySelector('#email');
            const messageInput = contactForm.querySelector('#message');

            formStatusDiv.textContent = '';
            formStatusDiv.className = 'form-message';

            // Základní validace
            if (!nameInput.value.trim() || !emailInput.value.trim() || !messageInput.value.trim()) {
                formStatusDiv.textContent = getLangText('contact_form_fill_all_fields', 'Please fill in all required fields.');
                formStatusDiv.classList.add('error');
                isValid = false;
            }
            
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailInput.value.trim() && !emailPattern.test(emailInput.value.trim())) {
                formStatusDiv.textContent = getLangText('contact_form_invalid_email', 'Please enter a valid email address.');
                formStatusDiv.classList.add('error');
                isValid = false;
            }

            if (!isValid) {
                return;
            }

            // Ověříme, že máme CSRF token
            if (!csrfToken) {
                console.warn('No CSRF token available, attempting to get one...');
                const tokenObtained = await getCSRFToken();
                if (!tokenObtained) {
                    formStatusDiv.textContent = 'Security token not available. Please refresh the page and try again.';
                    formStatusDiv.classList.add('error');
                    return;
                }
            }

            formStatusDiv.textContent = getLangText('contact_form_sending', 'Sending...');
            formStatusDiv.classList.remove('success', 'error', 'info');
            submitButtonContact.disabled = true;
            submitButtonContact.style.opacity = '0.7';

            const formData = new FormData(contactForm);
            formData.append('lang', siteLang);
            formData.append('csrf_token', csrfToken);
            formData.append('time_on_site', timeOnSite);
            formData.append('screen_resolution', getScreenResolution());

            try {
                const response = await fetch('scripts/send_email.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(`Server responded with ${response.status}: ${text || response.statusText}`);
                }

                const data = await response.json();
                
                formStatusDiv.textContent = data.message;
                if (data.success) {
                    formStatusDiv.classList.add('success');
                    contactForm.reset();
                    // Získáme nový CSRF token pro další použití
                    await getCSRFToken();
                } else {
                    formStatusDiv.classList.add('error');
                    // Pokud byl problém s tokenem, zkusíme získat nový
                    if (data.message.includes('token') || data.message.includes('security')) {
                        console.log('Token issue detected, refreshing token...');
                        await getCSRFToken();
                    }
                }
            } catch (error) {
                console.error('Contact form submission error:', error);
                formStatusDiv.textContent = getLangText('contact_form_error_network', 'A network error occurred. Please try again.');
                formStatusDiv.classList.add('error');
                // Zkusíme obnovit CSRF token
                await getCSRFToken();
            } finally {
                submitButtonContact.disabled = false;
                submitButtonContact.style.opacity = '1';
            }
        });
    }

    // --- Periodické obnovení CSRF tokenu (každých 30 minut) ---
    setInterval(() => {
        getCSRFToken();
    }, 30 * 60 * 1000);

    // --- Automatické uložení času při odchodu ze stránky ---
    window.addEventListener('beforeunload', function() {
        // Uložíme finální čas do localStorage pro případné debugování
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('lastTimeOnSite', timeOnSite);
        }
    });

    // --- Debug informace v konzoli (pouze pro vývoj) ---
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        console.log('Contact form enhanced features loaded:');
        console.log('- Time tracking enabled');
        console.log('- CSRF protection enabled');
        console.log('- Screen resolution tracking enabled');
        console.log('- Rate limiting protection enabled');
        
        // Zobrazíme aktuální čas na webu každých 10 sekund
        setInterval(() => {
            console.log(`Time on site: ${timeOnSite} seconds`);
        }, 10000);
    }
});

// --- Google Analytics Init ---
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'G-00THZ794SJ');

// --- Cookie Notice Logic ---
document.addEventListener('DOMContentLoaded', function() {
    const cookieNotice = document.getElementById('cookie-notice');
    const acceptAllBtn = document.getElementById('cookie-accept-all');
    const acceptEssentialBtn = document.getElementById('cookie-accept-essential');
    
    if(!cookieNotice) return;

    const cookieConsent = localStorage.getItem('cookie-consent');
    if (!cookieConsent) {
        setTimeout(() => {
            cookieNotice.classList.remove('cookie-notice-hidden');
            cookieNotice.style.display = 'block';
            setTimeout(() => {
                cookieNotice.classList.add('show');
            }, 100);
        }, 1000);
    } else {
        if (cookieConsent === 'all') {
            loadAnalytics();
        }
    }
    
    if(acceptAllBtn) {
        acceptAllBtn.addEventListener('click', function() {
            localStorage.setItem('cookie-consent', 'all');
            hideCookieNotice();
            loadAnalytics();
        });
    }
    
    if(acceptEssentialBtn) {
        acceptEssentialBtn.addEventListener('click', function() {
            localStorage.setItem('cookie-consent', 'essential');
            hideCookieNotice();
        });
    }
    
    function hideCookieNotice() {
        cookieNotice.classList.remove('show');
        cookieNotice.classList.add('hide');
        setTimeout(() => {
            cookieNotice.style.display = 'none';
        }, 400);
    }
    
    function loadAnalytics() {
        if (typeof gtag !== 'undefined') {
            gtag('consent', 'update', {
                'analytics_storage': 'granted'
            });
        }
        console.log('Analytics loaded with user consent');
    }
});
