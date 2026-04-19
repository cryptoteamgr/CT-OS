<?php
/**
 * CT-OS | copyright by cryptoteam.gr - footer.php
 * ----------------------------------------------------------------
 * Σκοπός: Καθολικό υποσέλιδο συστήματος με ενσωματωμένο Real-time Broadcast & Notification Engine.
 */
?>
    <style>
        /* Animations για τις ειδοποιήσεις */
        .notif-active {
            animation: slideInNotif 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28) forwards;
            transition: bottom 0.4s ease, transform 0.3s ease, opacity 0.3s ease;
        }
        
        .notif-exit {
            transform: translateX(120%) !important;
            opacity: 0 !important;
        }

        @keyframes slideInNotif {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Responsive προσαρμογή για μικρές οθόνες */
        @media (max-width: 640px) {
            .notif-active {
                width: calc(100% - 48px) !important;
                right: 24px !important;
                left: 24px !important;
            }
        }
    </style>

    <div id="admin-typing-indicator" style="display:none; position:fixed; bottom:90px; right:24px; z-index:9998; font-size:11px; font-weight:900; color:#3b82f6; background:rgba(15, 23, 42, 0.95); padding:8px 15px; border-radius:12px; border:1px solid rgba(59, 130, 246, 0.3); text-transform:uppercase; letter-spacing:1px; box-shadow: 0 10px 25px rgba(0,0,0,0.4); font-family: 'Inter', sans-serif; backdrop-filter: blur(4px);">
        <span style="display:inline-block; width:8px; height:8px; background:#3b82f6; border-radius:50%; margin-right:8px;" class="animate-pulse"></span>
        Admin is typing...
    </div>

    <?php 
    // Εμφάνιση του οπτικού footer ΜΟΝΟ αν ΔΕΝ είμαστε στην console.php
    if (basename($_SERVER['PHP_SELF']) !== 'console.php'): 
    ?>
    <footer class="max-w-7xl mx-auto mt-20 mb-10 text-center">
        <p class="text-[9px] text-slate-700 font-black uppercase tracking-[0.5em] italic">Cryptoteam OS Cluster | Node Active</p>
    </footer>
    <?php endif; ?>

    <script>
    // Καθολική μεταβλητή για τη διαχείριση του ύψους των popups
    window.activeNotifs = [];

    async function fetchBroadcast() {
        try {
            const response = await fetch('api_fetch_broadcast.php');
            if (!response.ok) return;
            const data = await response.json();

            // 1. Διαχείριση Λίστας Ειδοποιήσεων
            if (data.notifs && data.notifs.length > 0) {
                data.notifs.forEach((notif) => {
                    // Έλεγχος αν ο χρήστης έχει ήδη διαβάσει ΤΟ ΣΥΓΚΕΚΡΙΜΕΝΟ ID
                    if (localStorage.getItem('last_notif_seen_' + notif.id) !== 'true') {
                        if (!document.getElementById('active-notif-' + notif.id)) {
                            showPopup(notif.message, notif.type, notif.id);
                        }
                    }
                });
            }

            // 2. Έλεγχος για Typing Indicator
            const typingIndicator = document.getElementById('admin-typing-indicator');
            if (typingIndicator) {
                typingIndicator.style.display = (data.is_typing == 1) ? 'block' : 'none';
            }

        } catch (e) { /* silent fail */ }
    }

    function showPopup(msg, type, id) {
        const colors = { 'info': '#3b82f6', 'warning': '#eab308', 'danger': '#ef4444' };
        const popup = document.createElement('div');
        popup.id = 'active-notif-' + id;
        popup.className = 'notif-active'; 
        
        // Προσθήκη στη λίστα ενεργών για υπολογισμό ύψους
        window.activeNotifs.push(id);
        const index = window.activeNotifs.indexOf(id);
        const bottomOffset = 24 + (index * 150); // Δυναμικό ύψος

        Object.assign(popup.style, {
            position: 'fixed',
            bottom: bottomOffset + 'px',
            right: '24px',
            zIndex: '9999',
            background: '#0f172a',
            color: 'white',
            padding: '20px',
            borderRadius: '16px',
            boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.6)',
            borderLeft: `4px solid ${colors[type] || '#1e293b'}`,
            borderTop: '1px solid rgba(255,255,255,0.05)',
            width: '350px',
            fontFamily: 'Inter, sans-serif'
        });

        popup.innerHTML = `
            <div style="font-size:10px; font-weight:900; text-transform:uppercase; margin-bottom:8px; opacity:0.6; letter-spacing:1px; display:flex; justify-content:space-between; align-items:center;">
                <span>System Message</span>
                <span style="color:${colors[type]}; display:flex; align-items:center; gap:4px;">
                    <span style="width:6px; height:6px; background:${colors[type]}; border-radius:50%; display:inline-block; box-shadow: 0 0 8px ${colors[type]};"></span>
                    Live
                </span>
            </div>
            <div style="font-size:13px; line-height:1.5; font-weight:500; word-wrap: break-word;">${msg}</div>
            <button onclick="dismissNotif(${id})" style="margin-top:15px; width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; font-size:10px; font-weight:900; padding:10px; border-radius:8px; cursor:pointer; transition:0.3s; text-transform:uppercase; letter-spacing:1px;">Mark as Read</button>
        `;
        
        document.body.appendChild(popup);
    }

    function dismissNotif(id) {
        localStorage.setItem('last_notif_seen_' + id, 'true');
        const el = document.getElementById('active-notif-' + id);
        if (el) {
            el.classList.add('notif-exit'); 
            setTimeout(() => {
                el.remove();
                // Αφαίρεση από τη λίστα και επαναυπολογισμός θέσεων για τα υπόλοιπα
                window.activeNotifs = window.activeNotifs.filter(notifId => notifId !== id);
                repositionNotifs();
            }, 300);
        }
    }

    function repositionNotifs() {
        window.activeNotifs.forEach((id, index) => {
            const el = document.getElementById('active-notif-' + id);
            if (el) {
                el.style.bottom = (24 + (index * 150)) + 'px';
            }
        });
    }

    // Έλεγχος κάθε 15 δευτερόλεπτα
    setInterval(fetchBroadcast, 15000);
    fetchBroadcast();

    // Συγχρονισμός Dismiss μεταξύ καρτελών (Cross-tab sync)
    window.addEventListener('storage', (event) => {
        if (event.key.startsWith('last_notif_seen_') && event.newValue === 'true') {
            const id = parseInt(event.key.replace('last_notif_seen_', ''));
            const el = document.getElementById('active-notif-' + id);
            if (el) {
                el.remove();
                window.activeNotifs = window.activeNotifs.filter(notifId => notifId !== id);
                repositionNotifs();
            }
        }
    });
    </script>
</body>
</html>