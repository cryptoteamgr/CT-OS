<!DOCTYPE html>
<html lang="el" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTT-OS | The Future of Pair Trading by cryptoteam.gr</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&family=JetBrains+Mono&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #020617;
            color: #f1f5f9;
        }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(30, 41, 59, 0.5);
        }
        .text-gradient {
            background: linear-gradient(135deg, #3b82f6 0%, #10b981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-glow {
            position: absolute;
            top: -10%;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(2, 6, 23, 0) 70%);
            z-index: -1;
        }
    </style>
</head>
<body class="overflow-x-hidden">

    <div class="hero-glow"></div>

    <nav class="fixed w-full z-50 glass">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="text-2xl font-black italic text-blue-500 uppercase tracking-tighter">CTT-OS</span>
                <span class="bg-blue-500/10 text-blue-400 text-[10px] px-2 py-0.5 rounded border border-blue-500/20 font-bold uppercase">v3.9 Stable</span>
            </div>
            <div class="hidden md:flex gap-8 text-sm font-semibold uppercase tracking-widest text-slate-400">
                <a href="#architecture" class="hover:text-blue-400 transition-colors">Αρχιτεκτονική</a>
                <a href="#security" class="hover:text-blue-400 transition-colors">Ασφάλεια</a>
                <a href="#management" class="hover:text-blue-400 transition-colors">Διαχείριση</a>
            </div>
            <a href="https://cryptoteam.gr" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-full text-xs font-bold uppercase tracking-widest transition-all shadow-lg shadow-blue-600/20">
                Terminal Login
            </a>
        </div>
    </nav>

    <section class="pt-32 pb-20 px-6">
        <div class="max-w-7xl mx-auto text-center">
            <h2 class="text-blue-400 text-sm font-black uppercase tracking-[0.4em] mb-4" data-aos="fade-down">CryptoTeam Operating System</h2>
            <h1 class="text-5xl md:text-7xl font-black mb-8 leading-tight" data-aos="fade-up" data-aos-delay="100">
                Η Απόλυτη Μηχανή <br> <span class="text-gradient underline decoration-blue-500/30">Statistical Arbitrage</span>
            </h1>
            <p class="max-w-2xl mx-auto text-slate-400 text-lg mb-12" data-aos="fade-up" data-aos-delay="200">
                Ένα ολοκληρωμένο οικοσύστημα αυτοματοποιημένου trading, σχεδιασμένο για να εκμεταλλεύεται τις στατιστικές αποκλίσεις στην αγορά κρυπτονομισμάτων με Beta Neutral στρατηγική.
            </p>
            <div class="flex flex-col md:flex-row justify-center gap-6" data-aos="fade-up" data-aos-delay="300">
                <div class="glass p-4 rounded-2xl flex items-center gap-4">
                    <i class="fas fa-microchip text-emerald-400 text-2xl"></i>
                    <div class="text-left">
                        <p class="text-[10px] text-slate-500 uppercase font-bold">Execution Speed</p>
                        <p class="mono text-sm font-bold tracking-tighter">Under 350ms</p>
                    </div>
                </div>
                <div class="glass p-4 rounded-2xl flex items-center gap-4">
                    <i class="fas fa-shield-halved text-blue-400 text-2xl"></i>
                    <div class="text-left">
                        <p class="text-[10px] text-slate-500 uppercase font-bold">Risk Management</p>
                        <p class="mono text-sm font-bold tracking-tighter">Beta Neutrality</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="architecture" class="py-24 px-6 bg-slate-950/50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-20">
                <h3 class="text-3xl md:text-4xl font-black mb-4">Η Αρχιτεκτονική του Συστήματος</h3>
                <div class="w-20 h-1 bg-blue-600 mx-auto"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="glass p-8 rounded-3xl group hover:border-blue-500/50 transition-all shadow-xl" data-aos="fade-right">
                    <div class="w-14 h-14 bg-blue-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <i class="fas fa-radar text-blue-500 text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-4">Scanner Core</h4>
                    <p class="text-slate-400 text-sm leading-relaxed mb-4">
                        Η "καρδιά" του συστήματος. Σκανάρει διαρκώς την αγορά, υπολογίζοντας <strong>Z-Score</strong> και <strong>Beta</strong> μέσω ιστορικών δεδομένων (Klines).
                    </p>
                    <span class="mono text-[10px] text-blue-400 uppercase font-bold tracking-widest">Real-time Analysis</span>
                </div>

                <div class="glass p-8 rounded-3xl group hover:border-emerald-500/50 transition-all shadow-xl" data-aos="fade-right" data-aos-delay="100">
                    <div class="w-14 h-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <i class="fas fa-engine text-emerald-500 text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-4">Execution Engine</h4>
                    <p class="text-slate-400 text-sm leading-relaxed mb-4">
                        Μετατρέπει τα σήματα σε Market Orders με <strong>Rollback Logic</strong> και <strong>Auto-Leverage</strong> για μέγιστη ασφάλεια κεφαλαίου.
                    </p>
                    <span class="mono text-[10px] text-emerald-400 uppercase font-bold tracking-widest">Instant Execution</span>
                </div>

                <div class="glass p-8 rounded-3xl group hover:border-red-500/50 transition-all shadow-xl" data-aos="fade-right" data-aos-delay="200">
                    <div class="w-14 h-14 bg-red-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <i class="fas fa-eye text-red-500 text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-4">Monitor & Closer</h4>
                    <p class="text-slate-400 text-sm leading-relaxed mb-4">
                        Παρακολουθεί το <strong>PnL</strong> σε πραγματικό χρόνο και εκτελεί αυτόματη έξοδο (TP/SL) βάσει δολαρίων ή επιπέδων Z-Score.
                    </p>
                    <span class="mono text-[10px] text-red-400 uppercase font-bold tracking-widest">Autonomous Guard</span>
                </div>

                <div class="glass p-8 rounded-3xl group hover:border-purple-500/50 transition-all shadow-xl" data-aos="fade-right" data-aos-delay="300">
                    <div class="w-14 h-14 bg-purple-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <i class="fas fa-terminal text-purple-500 text-2xl"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-4">Terminal UI</h4>
                    <p class="text-slate-400 text-sm leading-relaxed mb-4">
                        Διεπαφή υψηλής αισθητικής για πλήρη έλεγχο των ρυθμίσεων (Capital, Leverage, Thresholds) και οπτικοποίηση θέσεων.
                    </p>
                    <span class="mono text-[10px] text-purple-400 uppercase font-bold tracking-widest">Master Control</span>
                </div>
            </div>
        </div>
    </section>

    <section id="security" class="py-24 px-6 overflow-hidden">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center gap-16">
            <div class="md:w-1/2" data-aos="fade-right">
                <h2 class="text-4xl font-black mb-8 leading-tight underline decoration-emerald-500/50">Στρατηγική & <br>Ασφάλεια Επιπέδου 4</h2>
                <div class="space-y-8">
                    <div class="flex gap-6">
                        <div class="flex-shrink-0 w-12 h-12 glass rounded-xl flex items-center justify-center text-blue-500 font-black">01</div>
                        <div>
                            <h5 class="text-lg font-bold mb-2 uppercase italic tracking-tighter">Beta Neutrality Logic</h5>
                            <p class="text-slate-400 text-sm leading-relaxed">Δεν ποντάρουμε στην τύχη. Το CT-OS στοχεύει στην επιστροφή της ισορροπίας (Convergence) μεταξύ συσχετισμένων περιουσιακών στοιχείων.</p>
                        </div>
                    </div>
                    <div class="flex gap-6">
                        <div class="flex-shrink-0 w-12 h-12 glass rounded-xl flex items-center justify-center text-emerald-500 font-black">02</div>
                        <div>
                            <h5 class="text-lg font-bold mb-2 uppercase italic tracking-tighter">Ghost Buster Technology</h5>
                            <p class="text-slate-400 text-sm leading-relaxed">Αυτόματος συγχρονισμός DB και Binance για την πλήρη εξάλειψη "εικονικών" θέσεων που προκαλούν σφάλματα.</p>
                        </div>
                    </div>
                    <div class="flex gap-6">
                        <div class="flex-shrink-0 w-12 h-12 glass rounded-xl flex items-center justify-center text-red-500 font-black">03</div>
                        <div>
                            <h5 class="text-lg font-bold mb-2 uppercase italic tracking-tighter">Secure Price Caching</h5>
                            <p class="text-slate-400 text-sm leading-relaxed">Χρήση τοπικού <code>prices_cache.json</code> για ακαριαία ταχύτητα στο UI, εκμηδενίζοντας το lag του δικτύου.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="md:w-1/2 relative" data-aos="fade-left">
                <div class="glass p-2 rounded-[2rem] shadow-2xl shadow-blue-500/10">
                    <img src="https://images.unsplash.com/photo-1639762681485-074b7f938ba0?q=80&w=2832&auto=format&fit=crop" class="rounded-[1.8rem] opacity-80" alt="Cyber Security">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#020617] via-transparent to-transparent rounded-[2rem]"></div>
                    <div class="absolute bottom-8 left-8">
                        <p class="mono text-xs text-blue-400 font-bold mb-1">DATA ENCRYPTION</p>
                        <p class="text-2xl font-black uppercase tracking-widest italic">AES-256 SECURED</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="management" class="py-24 px-6 bg-slate-900/30">
        <div class="max-w-7xl mx-auto">
            <div class="glass rounded-[3rem] p-12 overflow-hidden relative">
                <div class="absolute top-0 right-0 p-12 opacity-10">
                    <i class="fas fa-chart-line text-[15rem]"></i>
                </div>
                <div class="relative z-10 flex flex-col md:flex-row gap-12 items-center">
                    <div class="md:w-2/3">
                        <h3 class="text-4xl font-black mb-6 italic uppercase tracking-tighter">Διαχείριση & Performance Journal</h3>
                        <p class="text-slate-400 text-lg mb-8 leading-relaxed italic">
                            Το CT-OS λειτουργεί και ως πανίσχυρο εργαλείο management. Μέσω του ενσωματωμένου ημερολογίου, καταγράφεται κάθε λεπτομέρεια για τη βελτιστοποίηση της στρατηγικής σας.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <ul class="space-y-4 text-slate-300">
                                <li class="flex items-center gap-3"><i class="fas fa-check-circle text-blue-500"></i> Performance Journaling</li>
                                <li class="flex items-center gap-3"><i class="fas fa-check-circle text-blue-500"></i> Detailed Exit Reasons</li>
                            </ul>
                            <ul class="space-y-4 text-slate-300">
                                <li class="flex items-center gap-3"><i class="fas fa-check-circle text-blue-500"></i> Global Telegram Alerts</li>
                                <li class="flex items-center gap-3"><i class="fas fa-check-circle text-blue-500"></i> Multi-User Admin Control</li>
                            </ul>
                        </div>
                    </div>
                    <div class="md:w-1/3 text-center">
                        <div class="glass inline-block p-8 rounded-full border-blue-500/30 shadow-2xl shadow-blue-500/20">
                            <i class="fab fa-telegram text-[5rem] text-blue-400 mb-4 animate-pulse"></i>
                            <p class="text-[10px] text-slate-500 uppercase font-black tracking-[0.2em]">Global Hub Status</p>
                            <p class="text-emerald-400 font-bold italic tracking-tighter">CONNECTED</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-20 px-6 border-t border-slate-900">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="text-center md:text-left">
                <p class="text-xl font-black italic text-blue-500 uppercase tracking-tighter mb-2">CTT-OS</p>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest leading-relaxed">
                    Powered by the infrastructure of <a href="https://doulfis.gr" class="text-blue-400 hover:underline">doulfis.gr</a><br>
                    © 2026 cryptoteam.gr - All Rights Reserved.
                </p>
            </div>
            <div class="flex gap-6 text-slate-500">
                <a href="#" class="hover:text-blue-500 transition-colors"><i class="fab fa-telegram text-2xl"></i></a>
                <a href="#" class="hover:text-blue-500 transition-colors"><i class="fab fa-twitter text-2xl"></i></a>
                <a href="#" class="hover:text-blue-500 transition-colors"><i class="fab fa-discord text-2xl"></i></a>
            </div>
        </div>
    </footer>

    <script>
        AOS.init({
            duration: 1000,
            once: true,
            easing: 'ease-out-cubic'
        });
    </script>
</body>
</html>