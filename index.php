
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>CT-OS | The Future of Pair Trading by cryptoteam.gr</title>
    <meta name="description" content="CT-OS: The Ultimate Statistical Arbitrage Engine by cryptoteam.gr">
    <meta property="og:title" content="CT-OS | The Future of Pair Trading">
    <meta property="og:description" content="Automated Pair Trading with Beta Neutral strategy.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://cryptoteam.gr">

    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        
        /* Digital Glass Effect */
        .glass {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(30, 41, 59, 0.5);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        /* Digital Glow & Scale on Hover (Only for Cards) */
        .glass-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 0 40px rgba(59, 130, 246, 0.25);
            background: rgba(30, 41, 59, 0.8);
        }

        /* Scanner Line Animation (Only for Cards) */
        .glass-card::after {
            content: "";
            position: absolute;
            top: -100%;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #3b82f6, transparent);
            transition: 0.5s;
            opacity: 0;
        }

        .glass-card:hover::after {
            top: 100%;
            opacity: 1;
            transition: 1.2s cubic-bezier(0.4, 0, 0.2, 1);
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

        .nav-link { transition: all 0.3s ease; }
        .nav-link:hover { color: #3b82f6; }

        .greek-info-link {
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 4px 12px;
            border-radius: 8px;
            background: rgba(16, 185, 129, 0.05);
            transition: all 0.3s ease;
        }
        .greek-info-link:hover {
            background: rgba(16, 185, 129, 0.15);
            border-color: #10b981;
            color: #10b981;
        }
    </style> 
</head>
<body class="overflow-x-hidden">

    <div class="hero-glow"></div>

    <nav class="absolute w-full z-50 glass py-4 px-4 md:px-6">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row gap-4 justify-between items-center text-white">
            
            <div class="flex items-center gap-2">
                <span class="text-2xl font-black italic text-blue-500 uppercase tracking-tighter">CT-OS</span>
                <span class="bg-blue-500/10 text-blue-400 text-[10px] px-2 py-0.5 rounded border border-blue-500/20 font-bold uppercase animate-pulse">BETA</span>
            </div>

            <div class="flex flex-wrap justify-center gap-4 md:gap-8 text-[10px] md:text-sm font-semibold uppercase tracking-widest text-slate-400 items-center">
                <a href="#architecture" class="nav-link hover:text-blue-400 transition-colors">Architecture</a>
                <a href="#security" class="nav-link hover:text-blue-400 transition-colors">Security</a>
                <a href="#management" class="nav-link hover:text-blue-400 transition-colors">Management</a>
                <a href="info.php" class="nav-link greek-info-link text-[9px] md:text-xs uppercase tracking-widest font-black">Greek Info</a>
            </div>

            <a href="login.php" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-full text-[10px] md:text-xs font-bold uppercase tracking-widest transition-all shadow-lg shadow-blue-600/20 hover:scale-105 active:scale-95">
                Terminal Login
            </a>
        </div>
    </nav>

    <section class="pt-48 pb-20 px-6">
        <div class="max-w-7xl mx-auto text-center">
            <h2 class="text-blue-400 text-sm font-black uppercase tracking-[0.4em] mb-4" data-aos="fade-down">CryptoTeam Operating System</h2>
            <h1 class="text-5xl md:text-7xl font-black mb-8 leading-tight">
                The Ultimate <br> <span class="text-gradient underline decoration-blue-500/30">Statistical Arbitrage</span> Engine
            </h1>
            <p class="max-w-2xl mx-auto text-slate-400 text-lg mb-12" data-aos="fade-up" data-aos-delay="200">
                An integrated automated trading ecosystem designed to exploit statistical deviations in the crypto market using a Beta Neutral strategy.
            </p>
            <div class="flex flex-col md:flex-row justify-center gap-6" data-aos="fade-up" data-aos-delay="300">
                <div class="glass glass-card p-4 rounded-2xl flex items-center gap-4">
                    <i class="fas fa-microchip text-emerald-400 text-2xl"></i>
                    <div class="text-left">
                        <p class="text-[10px] text-slate-500 uppercase font-bold">Execution Speed</p>
                        <p class="mono text-sm font-bold tracking-tighter">Under 350ms</p>
                    </div>
                </div>
                <div class="glass glass-card p-4 rounded-2xl flex items-center gap-4">
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
        <div class="max-w-7xl mx-auto text-center mb-20">
            <h3 class="text-3xl md:text-4xl font-black mb-4 uppercase tracking-widest italic text-white">System Architecture</h3>
            <div class="w-20 h-1 bg-blue-600 mx-auto"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 max-w-7xl mx-auto">
            <div class="glass glass-card p-8 rounded-3xl group cursor-default" data-aos="fade-up">
                <div class="w-14 h-14 bg-blue-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:rotate-[360deg] transition-all duration-700">
                    <i class="fas fa-radar text-blue-500 text-2xl group-hover:text-white transition-colors"></i>
                </div>
                <h4 class="text-xl font-bold mb-4 uppercase tracking-tighter italic text-white">Scanner Core</h4>
                <p class="text-slate-400 text-sm leading-relaxed mb-4">The "heart" of the system. Constantly scans the market, calculating <strong>Z-Score</strong> via historical data.</p>
            </div>

            <div class="glass glass-card p-8 rounded-3xl group cursor-default" data-aos="fade-up" data-aos-delay="100">
                <div class="w-14 h-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-emerald-600 group-hover:rotate-[360deg] transition-all duration-700">
                    <i class="fas fa-engine text-emerald-500 text-2xl group-hover:text-white transition-colors"></i>
                </div>
                <h4 class="text-xl font-bold mb-4 uppercase tracking-tighter italic text-white">Execution Engine</h4>
                <p class="text-slate-400 text-sm leading-relaxed mb-4">Converts signals into Market Orders with <strong>Rollback Logic</strong> for maximum capital security.</p>
            </div>

            <div class="glass glass-card p-8 rounded-3xl group cursor-default" data-aos="fade-up" data-aos-delay="200">
                <div class="w-14 h-14 bg-red-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-red-600 group-hover:rotate-[360deg] transition-all duration-700">
                    <i class="fas fa-eye text-red-500 text-2xl group-hover:text-white transition-colors"></i>
                </div>
                <h4 class="text-xl font-bold mb-4 uppercase tracking-tighter italic text-white">Monitor & Closer</h4>
                <p class="text-slate-400 text-sm leading-relaxed mb-4">Monitors <strong>PnL</strong> in real-time and executes automatic exit (TP/SL) based on targets.</p>
            </div>

            <div class="glass glass-card p-8 rounded-3xl group cursor-default" data-aos="fade-up" data-aos-delay="300">
                <div class="w-14 h-14 bg-purple-500/10 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-purple-600 group-hover:rotate-[360deg] transition-all duration-700">
                    <i class="fas fa-terminal text-purple-500 text-2xl group-hover:text-white transition-colors"></i>
                </div>
                <h4 class="text-xl font-bold mb-4 uppercase tracking-tighter italic text-white">Terminal UI</h4>
                <p class="text-slate-400 text-sm leading-relaxed mb-4">High-aesthetic interface offering full control over settings and visualization.</p>
            </div>
        </div>
    </section>

    <section id="security" class="py-24 px-6">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center gap-16">
            <div class="md:w-1/2 text-white" data-aos="fade-right">
                <h2 class="text-4xl font-black mb-8 uppercase italic underline decoration-emerald-500/50 font-black">Strategy & Security</h2>
                <div class="space-y-8 text-slate-400 leading-relaxed">
                    <div class="flex gap-6">
                        <div class="flex-shrink-0 w-12 h-12 glass rounded-xl flex items-center justify-center text-blue-500 font-black">01</div>
                        <div>
                            <h5 class="text-lg font-bold mb-2 uppercase italic tracking-tighter text-white font-bold">Beta Neutrality Logic</h5>
                            <p>Focuses on market equilibrium between correlated assets.</p>
                        </div>
                    </div>
                    <div class="flex gap-6">
                        <div class="flex-shrink-0 w-12 h-12 glass rounded-xl flex items-center justify-center text-emerald-500 font-black">02</div>
                        <div>
                            <h5 class="text-lg font-bold mb-2 uppercase italic tracking-tighter text-white font-bold">Ghost Buster Technology</h5>
                            <p>Automatic synchronization to completely eliminate "ghost" positions.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="md:w-1/2">
                <div class="glass p-2 rounded-[2rem] shadow-2xl shadow-blue-500/10">
                    <img src="https://images.unsplash.com/photo-1639762681485-074b7f938ba0?q=80&w=2832&auto=format&fit=crop" class="rounded-[1.8rem] opacity-80" alt="Cyber Security">
                </div>
            </div>
        </div>
    </section>

    <footer class="py-20 px-6 border-t border-slate-900 text-white">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="text-center md:text-left font-bold">
                <p class="text-xl font-black italic text-blue-500 uppercase tracking-tighter mb-2">CT-OS</p>
                <p class="text-[10px] text-slate-500 uppercase tracking-widest leading-relaxed">
                    Powered by <a href="https://doulfis.gr" class="text-blue-400 hover:underline underline-offset-4">doulfis.gr</a><br>
                    © 2026 cryptoteam.gr - All Rights Reserved.
                </p>
            </div>
            <div class="flex gap-6 text-white text-2xl">
                <a href="https://t.me/Doulfis" target="_blank" class="hover:text-blue-500 transition-all transform hover:-translate-y-1"><i class="fab fa-telegram"></i></a>
                <a href="https://x.com/doulfis" target="_blank" class="hover:text-blue-400 transition-all transform hover:-translate-y-1"><i class="fa-brands fa-x-twitter"></i></a>
                <a href="https://discord.gg/7fTb8ZCcxg" target="_blank" class="hover:text-blue-500 transition-all transform hover:-translate-y-1"><i class="fab fa-discord"></i></a>
            </div>
        </div>
    </footer>

    <script>
        AOS.init({ duration: 1000, once: true, easing: 'ease-out-cubic' });

        const cards = document.querySelectorAll('.glass-card');
        cards.forEach(card => {
            card.addEventListener('mousemove', e => {
                let rect = card.getBoundingClientRect();
                let x = e.clientX - rect.left;
                let y = e.clientY - rect.top;
                let angleX = (y - rect.height / 2) / 12;
                let angleY = (rect.width / 2 - x) / 12;
                card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg) scale(1.02)`;
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)`;
            });
        });
    </script>
</body>
</html>