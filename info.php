<?php
/**
 * CT-OS | copyright by cryptoteam.gr - info.php
 * ----------------------------------------------------------------
 * Σκοπός: Η επίσημη σελίδα πληροφοριών και εκπαίδευσης της CryptoTeam. 
 * Συνδυάζει την προσωπική εμπειρία του ιδρυτή (Γεώργιος Δουλφής) με τις 
 * εμπορικές και τεχνικές λεπτομέρειες της συνδρομητικής υπηρεσίας.
 */
 ?>
<!DOCTYPE html>
<html lang="el" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Γεώργιος Δουλφής | CryptoTeam Trading Technique</title>
    
    <meta name="description" content="Μάθετε τη γλώσσα του Trading με τον Γεώργιο Δουλφή. 30 χρόνια εμπειρίας στο Crypto Trading.">
    
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
        .glass {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(30, 41, 59, 0.5);
            transition: all 0.3s ease;
        }
        .glass:hover {
            border-color: #3b82f6;
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.15);
        }
        .text-gradient {
            background: linear-gradient(135deg, #3b82f6 0%, #10b981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-glow {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, rgba(2, 6, 23, 0) 70%);
            z-index: -1;
        }
        .border-accent { border-left: 4px solid #3b82f6; }
    </style>
</head>
<body class="overflow-x-hidden">

    <div class="hero-glow"></div>

    <nav class="w-full glass py-4 px-6 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="index.php" class="text-xl font-black italic text-blue-500 uppercase tracking-tighter">CT-OS</a>
            <div class="flex gap-6 items-center">
                <a href="https://t.me/Doulfis" target="_blank" class="text-blue-400 hover:text-white transition-colors"><i class="fab fa-telegram text-xl"></i></a>
                <a href="login.php" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-full text-xs font-bold uppercase tracking-widest transition-all">Terminal Login</a>
            </div>
        </div>
    </nav>

    <header class="pt-24 pb-16 px-6">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-4xl md:text-6xl font-black mb-6 leading-tight" data-aos="fade-up">
                Είναι μεγάλο ρίσκο να μην είσαι στην <span class="text-gradient underline decoration-blue-500/30 font-black">cryptoteam.gr</span>
            </h1>
            <p class="text-xl text-slate-400 italic mb-8" data-aos="fade-up" data-aos-delay="100">
                "Είμαι ο Γιώργος ο Δουλφής, 30 χρόνια Trader. Εδώ μαθαίνεις τη γλώσσα του Trading."
            </p>
            <div class="inline-block glass p-6 rounded-3xl border-accent" data-aos="zoom-in" data-aos-delay="200">
                <p class="text-sm font-bold uppercase tracking-widest text-blue-400 mb-2">Ετήσια Συνδρομή</p>
                <p class="text-5xl font-black text-white">124€ <span class="text-lg font-normal text-slate-500">/ έτος</span></p>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 space-y-24 pb-24">

        <section class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
            <div class="space-y-6" data-aos="fade-right">
                <h2 class="text-3xl font-black uppercase italic tracking-tighter border-b border-blue-500/30 pb-4 text-white">Η Εμπειρία στο Trading</h2>
                <p class="text-slate-300 leading-relaxed">
                    Θα σου εξηγήσω τι σημαίνει αυτό που λέμε <strong>ΕΜΠΕΙΡΙΑ</strong> στο Trading. Η διαίσθηση μπορεί να θεωρηθεί ως αποτέλεσμα της ταχείας επεξεργασίας πληροφοριών που έχουν αποθηκευτεί στον εγκέφαλο από προηγούμενες εμπειρίες.
                </p>
                <blockquote class="border-l-4 border-emerald-500 pl-6 italic text-slate-400">
                    "Μέλλον, Παρών και Παρελθόν ΕΙΝΑΙ ΟΛΑ ΠΑΡΕΛΘΟΝΤΑ. Απλώς ΔΙΑΒΑΖΩ λίγο πιο μεγάλο βάθος παρελθοντικών πληροφοριών που έχουν συμβεί ήδη!"
                </blockquote>
                <p class="text-slate-300">
                    Ο χρόνος που χρειάζεσαι για να φτάσεις σε ένα ικανοποιητικό επίπεδο σοβαρής εκμάθησης και να αρχίσεις να βλέπεις τον εαυτό σου ως Crypto Trader, είναι περίπου τα <strong>3 χρόνια</strong>.
                </p>
            </div>
            <div class="glass p-8 rounded-[2.5rem] relative overflow-hidden" data-aos="fade-left">
                <div class="relative z-10 space-y-4">
                    <h3 class="text-xl font-bold text-white uppercase tracking-widest"><i class="fas fa-graduation-cap text-blue-500 mr-3"></i>Τι θα μάθεις:</h3>
                    <ul class="space-y-3 text-slate-400">
                        <li class="flex items-center gap-3"><i class="fas fa-check text-emerald-500 text-xs"></i> Βασικές αρχές Οικονομίας</li>
                        <li class="flex items-center gap-3"><i class="fas fa-check text-emerald-500 text-xs"></i> Ιστορία & Τεχνολογία Blockchain</li>
                        <li class="flex items-center gap-3"><i class="fas fa-check text-emerald-500 text-xs"></i> Τα πάντα για το Bitcoin</li>
                        <li class="flex items-center gap-3"><i class="fas fa-check text-emerald-500 text-xs"></i> "Trading Technique" Cycle</li>
                    </ul>
                    <hr class="border-slate-800">
                    <div class="pt-4 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center text-xl font-black uppercase">ΓΔ</div>
                        <div>
                            <p class="text-xs font-bold uppercase text-blue-400">Founder</p>
                            <p class="text-sm font-black text-white">Γεώργιος Δουλφής</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-slate-950/50 rounded-[3rem] p-12 border border-slate-800" data-aos="fade-up">
            <h2 class="text-3xl font-black text-center mb-12 uppercase tracking-widest italic text-white font-black">24/7/360 Υπηρεσίες</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center space-y-4 p-6 glass rounded-3xl group hover:bg-blue-600/10">
                    <i class="fab fa-viber text-4xl text-purple-500 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-bold uppercase text-white">Viber Community</h4>
                    <p class="text-xs text-slate-400">24ωρη πρόσβαση στο υλικό μας και άμεση επικοινωνία στο 6945817567.</p>
                </div>
                <div class="text-center space-y-4 p-6 glass rounded-3xl group hover:bg-blue-600/10">
                    <i class="fab fa-discord text-4xl text-blue-500 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-bold uppercase text-white">VIP Discord</h4>
                    <p class="text-xs text-slate-400">Εξειδικευμένα VIP κανάλια για βαθιά ανάλυση της αγοράς.</p>
                </div>
                <div class="text-center space-y-4 p-6 glass rounded-3xl group hover:bg-blue-600/10">
                    <i class="fas fa-desktop text-4xl text-emerald-500 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-bold uppercase text-white">Anydesk Support</h4>
                    <p class="text-xs text-slate-400">Απομακρυσμένη τεχνική υποστήριξη στον υπολογιστή σας.</p>
                </div>
            </div>
        </section>

        <section id="payment" class="max-w-4xl mx-auto space-y-12">
            <div class="text-center" data-aos="fade-down">
                <h2 class="text-3xl font-black uppercase text-white">Τρόποι Πληρωμής</h2>
                <p class="text-slate-500 mt-2">Ετήσια συνδρομή 124€ - Εκδίδεται Τιμολόγιο Παροχής Υπηρεσιών</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="glass p-8 rounded-3xl space-y-4 border-accent" data-aos="fade-right">
                    <h4 class="font-black text-blue-500 uppercase tracking-widest"><i class="fas fa-university mr-2"></i> Τράπεζες</h4>
                    <div class="space-y-4 text-xs mono">
                        <div><p class="text-slate-500">NBG BANK</p><p class="text-white font-bold select-all">GR2801106350000063500277403</p></div>
                        <div><p class="text-slate-500">ALPHABANK</p><p class="text-white font-bold select-all">GR7701403970397002002003779</p></div>
                        <div><p class="text-slate-500">VIVA</p><p class="text-white font-bold select-all">GR9505700000000279553214582</p></div>
                        <div><p class="text-slate-500">IRIS (ΑΦΜ)</p><p class="text-white font-bold select-all">066589078</p></div>
                    </div>
                </div>
                
                <div class="glass p-8 rounded-3xl space-y-6 flex flex-col justify-center" data-aos="fade-left">
                    <a href="#" class="block w-full text-center py-4 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl font-black uppercase tracking-widest transition-all shadow-lg">
                        <i class="fab fa-paypal mr-2"></i> Paypal Donate
                    </a>
                    <a href="#" class="block w-full text-center py-4 bg-white hover:bg-slate-200 text-black rounded-2xl font-black uppercase tracking-widest transition-all shadow-lg">
                        <i class="fas fa-credit-card mr-2"></i> Πιστωτική / Χρεωστική
                    </a>
                    <p class="text-[10px] text-slate-500 italic text-center uppercase tracking-tighter leading-relaxed">
                        Αιτιολογία: Τεχνική Υποστήριξη – Remote Support Ετήσια. <br>
                        Το τιμολόγιο εκδίδεται από την επιχείρηση doulfis.gr
                    </p>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-800 pt-16 grid grid-cols-1 md:grid-cols-2 gap-12">
            <div class="space-y-6" data-aos="fade-right">
                <h3 class="text-2xl font-black uppercase text-white">Ταυτότητα Επιχείρησης</h3>
                <div class="mono text-sm space-y-2 text-slate-400">
                    <p class="text-white font-bold">DOULFIS.GR - ΣΥΣΤΗΜΑΤΑ ΠΛΗΡΟΦΟΡΙΚΗΣ</p>
                    <p>ΑΦΜ: 066589078</p>
                    <p>ΔΟΥ: ΚΕΦΟΔΕ ΑΤΤΙΚΗΣ</p>
                    <p>ΑΡ ΓΕΜΗ: 055499809000</p>
                </div>
                <div class="pt-4">
                    <p class="text-xs text-slate-500 uppercase leading-relaxed italic">
                        Η cryptoteam.gr είναι ανεξάρτητος πόρος ψηφιακών μέσων που καλύπτει την τεχνολογία blockchain και τις τάσεις fintech. Πιστεύουμε ότι ο αποκεντρωμένος κόσμος θα αναπτυχθεί εκθετικά.
                    </p>
                </div>
            </div>
            <div class="space-y-6" data-aos="fade-left">
                <h3 class="text-2xl font-black uppercase text-white">Επικοινωνία</h3>
                <div class="space-y-4">
                    <p class="flex items-center gap-4 text-slate-300"><i class="fas fa-envelope text-blue-500"></i> info@cryptoteam.gr</p>
                    <p class="flex items-center gap-4 text-slate-300"><i class="fas fa-phone text-blue-500"></i> 210 49 27 568</p>
                    <p class="flex items-center gap-4 text-slate-300"><i class="fab fa-viber text-purple-500"></i> 6945 817 567 (24ωρο)</p>
                </div>
            </div>
        </section>

        <section class="glass p-8 rounded-3xl bg-red-500/5 border-red-500/20" data-aos="fade-up">
            <p class="text-[11px] text-slate-500 leading-relaxed uppercase tracking-tighter">
                <strong class="text-red-500 mr-2">DISCLAIMER:</strong>
                Οι πληροφορίες στον ιστότοπο δεν αποτελούν επενδυτικές συμβουλές. H CryptoTeam.gr δεν συνιστά την αγορά ή πώληση cryptocurrency. Πραγματοποιήστε τη δική σας έρευνα και συμβουλευτείτε οικονομικό σύμβουλο. Η λειτουργία διέπεται από τον ισχύοντα Κώδικα Δεοντολογίας.
            </p>
        </section>

    </main>

    <footer class="py-12 border-t border-slate-900 bg-black/50 text-center">
        <p class="text-xl font-black italic text-blue-500 mb-2 uppercase">CT-OS</p>
        <p class="text-[10px] text-slate-600 uppercase tracking-widest font-black">
            Powered by <a href="https://doulfis.gr" class="text-blue-500 hover:underline">doulfis.gr</a> <br>
            © 2026 cryptoteam.gr - All Rights Reserved
        </p>
    </footer>

    <script>
        AOS.init({ duration: 1000, once: true });
    </script>
</body>
</html>