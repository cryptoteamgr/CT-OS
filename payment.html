<?php
/**
 * CT-OS | copyright by cryptoteam.gr - payment.php
 * ----------------------------------------------------------------
 * Σκοπός: Η κεντρική πύλη πληρωμών (Payment Gateway) του συστήματος. Διαχειρίζεται τον διαχωρισμό μεταξύ λιανικής (Retail) και εταιρικής (Business) τιμολόγησης, ενσωματώνοντας παραδοσιακές και κρυπτογραφικές μεθόδους πληρωμής.
 */
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway | CTT-OS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { background-color: #020617; color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .glass-card { background: #0f172a; border: 1px solid #1e293b; transition: all 0.3s ease; cursor: pointer; }
        .glass-card:hover { border-color: #3b82f6; background: #1e293b; }
        .tab-btn.active { background-color: #3b82f6; color: white; border-color: #3b82f6; }
        .hidden-tab { display: none; }
        .clickable-info { transition: transform 0.1s; }
        .clickable-info:active { transform: scale(0.98); }
    </style>
</head>
<body class="p-4 md:p-10">

    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-black italic text-blue-500 tracking-tighter uppercase mb-2">CTT-OS PAY</h1>
            <p class="text-slate-400 text-xs tracking-widest uppercase">Secure Transaction Portal</p>
        </div>

        <div class="flex justify-center mb-10 p-1 bg-slate-900 rounded-xl max-w-sm mx-auto border border-slate-800">
            <button onclick="showTab('retail')" id="btn-retail" class="tab-btn active flex-1 py-2 px-4 rounded-lg text-xs font-black uppercase transition-all">ΙΔΙΩΤΗΣ</button>
            <button onclick="showTab('business')" id="btn-business" class="tab-btn flex-1 py-2 px-4 rounded-lg text-xs font-black uppercase transition-all text-slate-400">ΤΙΜΟΛΟΓΙΟ</button>
        </div>

        <div id="tab-retail" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="glass-card p-4 rounded-2xl clickable-info" onclick="openInfo(this)">
                    <span class="text-[9px] font-black text-yellow-500 uppercase italic">Piraeus Bank</span>
                    <p class="font-mono text-xs mt-1 uppercase">GR3101721030005103014335341</p>
                </div>
                <div class="glass-card p-4 rounded-2xl clickable-info" onclick="openInfo(this)">
                    <span class="text-[9px] font-black text-blue-400 uppercase italic">Alpha Bank</span>
                    <p class="font-mono text-xs mt-1 uppercase">GR0301403970397002310000481</p>
                </div>
                <div class="glass-card p-4 rounded-2xl clickable-info" onclick="openInfo(this)">
                    <span class="text-[9px] font-black text-red-500 uppercase italic">Eurobank</span>
                    <p class="font-mono text-xs mt-1 uppercase">GR3502602580000700102095129</p>
                </div>
                <div class="glass-card p-4 rounded-2xl border-l-4 border-l-emerald-600 clickable-info" onclick="openInfo(this)">
                    <span class="text-[9px] font-black text-emerald-500 uppercase italic">NBG Bank</span>
                    <p class="font-mono text-[11px] mt-1 uppercase">GR3501107130000071360139373</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gradient-to-br from-pink-600/20 to-purple-600/20 p-5 rounded-3xl border border-pink-500/30 cursor-pointer clickable-info" onclick="openInfo(this)">
                    <p class="text-[10px] font-black text-pink-500 uppercase mb-1 italic">⚡ IRIS Payment (Κινητό)</p>
                    <p class="text-xl font-black text-white">6945 817 567</p>
                </div>
                <div class="bg-gradient-to-br from-slate-800 to-slate-900 p-5 rounded-3xl border border-slate-700 cursor-pointer clickable-info" onclick="openInfo(this)">
                    <p class="text-[10px] font-black text-white uppercase mb-1 italic">💳 Revolut</p>
                    <p class="text-xl font-black text-white">6945 817 567</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <div class="glass-card p-4 rounded-2xl border border-yellow-500/30 clickable-info" onclick="openInfo(this)">
                    <span class="text-[9px] font-black text-yellow-500 uppercase italic">Binance Pay</span>
                    <p class="font-mono text-lg font-black text-white mt-1">6945 817 567</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="glass-card p-4 rounded-2xl border-l-4 border-l-emerald-500 clickable-info" onclick="openInfo(this)">
                        <span class="text-[9px] font-black text-emerald-500 uppercase italic">USDT (TRC20)</span>
                        <p class="font-mono text-[10px] mt-1 break-all uppercase">TPgBvzJADz7sTTJ3qymbdE1gMtGn39YaXP</p>
                    </div>
                    <div class="glass-card p-4 rounded-2xl border-l-4 border-l-blue-500 clickable-info" onclick="openInfo(this)">
                        <span class="text-[9px] font-black text-blue-500 uppercase italic">USDC (ERC20)</span>
                        <p class="font-mono text-[10px] mt-1 break-all uppercase">0x9c6cdc77d4f7710f93f2cf6d1610e4f13cbe9a99</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-8 border-t border-slate-800">
                <h2 class="text-center text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mb-4">Άλλοι Τρόποι (Ιδιώτες)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-center">
                    <a href="https://wise.com/pay/me/georgiosd180" target="_blank" class="bg-emerald-600 hover:bg-emerald-500 p-3 rounded-xl text-[10px] font-black uppercase">Wise</a>
                    <a href="https://www.paypal.com/donate/?hosted_button_id=YSVJDJ2K3QCAA" target="_blank" class="bg-blue-800 hover:bg-blue-700 p-3 rounded-xl text-[10px] font-black uppercase">PayPal</a>
                </div>
            </div>
        </div>

        <div id="tab-business" class="space-y-6 hidden-tab">
            <div class="grid grid-cols-1 gap-3">
                <div class="glass-card p-4 rounded-2xl border-l-4 border-l-blue-600 clickable-info" onclick="openInfo(this)">
                    <span class="text-[9px] font-black text-slate-500 uppercase">Alpha Bank (Business)</span>
                    <p class="font-mono text-sm mt-1">GR7701403970397002002003779</p>
                </div>
                <div class="glass-card p-4 rounded-2xl border-l-4 border-l-emerald-600 clickable-info" onclick="openInfo(this)">
                    <span class="text-[9px] font-black text-slate-500 uppercase">National Bank (Business)</span>
                    <p class="font-mono text-sm mt-1">GR2801106350000063500277403</p>
                </div>
                <div class="glass-card p-4 rounded-2xl border-l-4 border-l-orange-500 clickable-info" onclick="openInfo(this)">
                    <span class="text-[9px] font-black text-orange-500 uppercase">Viva Bank (Business)</span>
                    <p class="font-mono text-sm mt-1">GR9505700000000279553214582</p>
                </div>
            </div>

            <div class="bg-pink-600 p-5 rounded-3xl shadow-lg shadow-pink-900/20 cursor-pointer clickable-info" onclick="openInfo(this)">
                <p class="text-[10px] font-black text-white/80 uppercase mb-1 italic">⚡ IRIS Business (Με ΑΦΜ)</p>
                <p class="text-2xl font-black text-white tracking-widest">066589078</p>
            </div>

            <div class="mt-8 pt-8 border-t border-slate-800">
                <h2 class="text-center text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mb-4">Άλλοι Τρόποι (Επαγγελματικό)</h2>
                <a href="https://pay.vivawallet.com/doulfisgr" target="_blank" class="block bg-blue-600 hover:bg-blue-500 p-4 rounded-2xl text-center text-xs font-black uppercase tracking-widest">
                    Πληρωμή με Κάρτα (Card Pay)
                </a>
            </div>
        </div>

        <footer class="mt-12 text-center pb-12 opacity-40">
            <p class="text-[10px] font-bold uppercase italic tracking-tighter">Γεώργιος Δουλφής - doulfis.gr</p>
            <p class="text-[9px] mt-1 italic uppercase">Υπηρεσίες Τεχνικής Υποστήριξης Λογισμικού</p>
        </footer>
    </div>

    <script>
        function openInfo(element) {
            const textToCopy = element.querySelector('p')?.innerText || element.innerText;
            
            const newWindow = window.open('about:blank', '_blank');
            newWindow.document.write(`
                <html>
                    <head>
                        <title>Payment Info</title>
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <style>
                            body { background:#020617; color:white; display:flex; align-items:center; justify-content:center; height:100vh; font-family:sans-serif; margin:0; padding:20px; }
                            .container { text-align:center; background:#0f172a; padding:40px; border-radius:24px; border:1px solid #1e293b; max-width:100%; }
                            h1 { color:#3b82f6; text-transform:uppercase; font-size:14px; letter-spacing:2px; margin-bottom:20px; }
                            p { font-size:20px; font-family:monospace; background:#1e293b; padding:15px; border-radius:12px; word-break:break-all; }
                            button { background:#3b82f6; color:white; border:none; padding:12px 24px; border-radius:8px; cursor:pointer; font-weight:bold; margin-top:20px; text-transform:uppercase; font-size:12px; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <h1>DATA COPIED</h1>
                            <p>${textToCopy}</p>
                            <button onclick="window.close()">CLOSE TAB</button>
                        </div>
                    </body>
                </html>
            `);
        }

        function showTab(type) {
            const retailTab = document.getElementById('tab-retail');
            const businessTab = document.getElementById('tab-business');
            const btnRetail = document.getElementById('btn-retail');
            const btnBusiness = document.getElementById('btn-business');

            if (type === 'retail') {
                retailTab.classList.remove('hidden-tab');
                businessTab.classList.add('hidden-tab');
                btnRetail.classList.add('active');
                btnRetail.classList.remove('text-slate-400');
                btnBusiness.classList.remove('active');
                btnBusiness.classList.add('text-slate-400');
            } else {
                retailTab.classList.add('hidden-tab');
                businessTab.classList.remove('hidden-tab');
                btnBusiness.classList.add('active');
                btnBusiness.classList.remove('text-slate-400');
                btnRetail.classList.remove('active');
                btnRetail.classList.add('text-slate-400');
            }
        }
    </script>
</body>
</html>