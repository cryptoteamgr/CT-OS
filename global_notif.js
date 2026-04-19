/**
 * CT-OS | copyright by cryptoteam.gr - global_notif.js
 * ----------------------------------------------------------------
 * Σκοπός: Front-end μηχανισμός (Polling Service) για τη λήψη και την οπτική προβολή ζωντανών ανακοινώσεων συστήματος από τον διαχειριστή.
 */

async function checkGlobalNotifications() {
    try {
        const response = await fetch('api_fetch_broadcast.php');
        const data = await response.json();

        // Έλεγχος αν υπάρχει μήνυμα και αν δεν το έχουμε δείξει ήδη
        if (data && data.id && !sessionStorage.getItem('notif_shown_' + data.id)) {
            showPopUp(data.message, data.type);
            sessionStorage.setItem('notif_shown_' + data.id, 'true');
        }
    } catch (e) { /* ignore errors */ }
}

function showPopUp(text, type) {
    const colors = { 'info': '#3b82f6', 'warning': '#f59e0b', 'danger': '#ef4444', 'success': '#10b981' };
    const bg = colors[type] || '#1e293b';

    const el = document.createElement('div');
    el.innerHTML = `
        <div style="position:fixed; bottom:20px; right:20px; z-index:99999; background:${bg}; color:white; padding:15px 25px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.5); min-width:280px; font-family:sans-serif; animation: slideIn 0.5s ease;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <b style="font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.8;">System Message</b>
                <button onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:white; cursor:pointer; font-size:16px;">&times;</button>
            </div>
            <div style="font-size:14px; font-weight:500;">${text}</div>
        </div>
        <style> @keyframes slideIn { from { transform: translateX(100%); opacity:0; } to { transform: translateX(0); opacity:1; } } </style>
    `;
    document.body.appendChild(el);
}

// Έλεγχος κάθε 15 δευτερόλεπτα
setInterval(checkGlobalNotifications, 15000);
checkGlobalNotifications();