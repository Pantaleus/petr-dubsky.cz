<?php
// includes/footer.php
if (!defined('APP_LOADED')) exit;

$base = isset($base_path) ? $base_path : '';
$link_home = (defined('IS_HOME_PAGE') && IS_HOME_PAGE) ? '#home' : $base . 'index.php?lang=' . $current_lang_code . '#home';
?>
<footer id="footer" class="footer-section">
    <div class="container">
        <div class="footer-content" style="text-align: center;">
            <div class="footer-copyright-wrapper" style="display: flex; align-items: center; justify-content: center; margin-bottom: 25px;">
                <div class="footer-logo" style="margin-right: 15px;">
                    <a href="<?php echo htmlspecialchars($link_home); ?>" aria-label="<?php echo htmlspecialchars($lang['nav_home']); ?>" style="text-decoration: none; display: flex; align-items: center;">
                        <svg width="45" height="30" viewBox="0 0 60 30" fill="none" xmlns="http://www.w3.org/2000/svg" class="logo-svg">
                          <text x="5" y="23" class="logo-svg-p">P</text>
                          <text x="28" y="23" class="logo-svg-d">D</text>
                        </svg>
                    </a>
                </div>
                <p class="copyright" style="margin: 0; padding-top: 2px;"><?php echo $lang['footer_copyright']; ?></p>
            </div>
            <div class="footer-links">
                 <a href="https://www.linkedin.com/in/pietro-dubsky/" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($lang['footer_linkedin_link_title'] ?? 'LinkedIn'); ?>">LinkedIn</a>
                 <a href="https://gitlab.petr-dubsky.cz/pantaleus" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($lang['footer_github_link_title'] ?? 'GitHub'); ?>">GitHub</a>
                 <a href="https://www.facebook.com/pietro.dubsky" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($lang['footer_facebook_link_title'] ?? 'Facebook'); ?>">Facebook</a>
                 <a href="https://www.instagram.com/pietro.dubsky" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($lang['footer_instagram_link_title'] ?? 'Instagram'); ?>">Instagram</a>
                 <a href="https://x.com/PietroDubsky" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($lang['footer_x_link_title'] ?? 'Platforma X'); ?>">Platforma X</a>
                 <a href="https://bcrypt.petr-dubsky.cz" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($lang['footer_bcrypt_title'] ?? 'Bcrypt'); ?>">Bcrypt</a> 
                 <a href="https://password.petr-dubsky.cz" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($lang['footer_password_title'] ?? 'Password generator'); ?>">Password generator</a>
                 <a href="https://dubnet.cz" target="_blank" rel="noopener noreferrer" title="DubNet CZ">DubNet CZ</a>
                 <a href="https://badminton.coach" target="_blank" rel="noopener noreferrer" title="Badminton Coach">Badminton Coach</a>
                 <a href="https://petr-dubsky.cz/ai" target="_blank" rel="noopener noreferrer" title="Nejlepší AI nástroje">Nejlepší AI nástroje</a>
           </div>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Nastavení Cookie s rozlišením pro další kliknutí na webu (platnost 1 rok)
    if (document.cookie.indexOf('screen_res=') === -1) {
        let res = window.screen.width + "x" + window.screen.height;
        document.cookie = "screen_res=" + res + "; path=/; max-age=31536000; SameSite=Lax";
        
        let p = "<?php echo isset($base_path) ? $base_path : ''; ?>";
        fetch(p + 'scripts/track_res.php?res=' + res).catch(e => console.error(e));
    }
    // Nástroj pro Behaviorální Analytiku (Zcela Asynchronní start)
    let p = "<?php echo isset($base_path) ? $base_path : ''; ?>";
    
    fetch(p + 'scripts/track_init.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ 
            url: window.location.href, 
            referer: document.referrer 
        })
    })
    .then(r => r.json())
    .then(data => {
        let visitorLogId = data.id;
        
        if (visitorLogId > 0) {
            let timeOnPage = 0;
            let maxScroll = 0;
            let isActive = true;
            let lastPingTime = Date.now();
            let pingInterval;

            // Aktualizace scrollování
            function updateScroll() {
                let s = window.scrollY || document.documentElement.scrollTop;
                let h = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                let percent = h > 0 ? Math.round((s / h) * 100) : 100;
                if (percent > maxScroll) maxScroll = percent;
                if (maxScroll > 100) maxScroll = 100;
            }
            
            window.addEventListener('scroll', updateScroll, {passive: true});

            // Kontrola okna v popředí
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    isActive = false;
                    sendPing();
                } else {
                    isActive = true;
                    lastPingTime = Date.now();
                }
            });

            function sendPing(actionString = null) {
                if (timeOnPage === 0 && maxScroll === 0 && !actionString) return;
                let payload = {id: visitorLogId, time: timeOnPage, scroll: maxScroll};
                if (actionString) payload.action = actionString;

                let vData = new Blob([JSON.stringify(payload)], {type: 'application/json'});
                let ep = p + "scripts/track_behavior.php";
                
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(ep, vData);
                } else {
                    fetch(ep, {method: 'POST', body: vData, keepalive: true}).catch(()=>{});
                }
            }

            // Měřící interval
            pingInterval = setInterval(function() {
                if (isActive) {
                    let now = Date.now();
                    timeOnPage += Math.round((now - lastPingTime) / 1000);
                    lastPingTime = now;
                    updateScroll();
                    
                    if (timeOnPage % 15 === 0) {
                        sendPing();
                    }
                }
            }, 1000);

            // Měření odchozích kliků na tlačítka/odkazy mající data-track="true" 
            document.querySelectorAll('[data-track="true"], .track-click').forEach(function(el) {
                el.addEventListener('click', function(e) {
                    let actionText = el.getAttribute('data-action') || el.innerText || "Unknown Action";
                    sendPing("Klik: " + actionText.trim());
                });
            });

            // Finální odeslání
            window.addEventListener('beforeunload', function() {
                if (isActive) {
                    timeOnPage += Math.round((Date.now() - lastPingTime) / 1000);
                }
                sendPing();
            });
        }
    })
    .catch(e => console.error("Init analytiky zlyhalo:", e));
});
</script>
