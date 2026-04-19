<?php
/**
 * CT-OS | copyright by cryptoteam.gr - wallet-connect.php
 * ----------------------------------------------------------------
 * Σκοπός: Η πύλη εισόδου Web3 (Universal Wallet Gateway). 
 * Επιτρέπει στους Operators να συνδέονται στο Terminal χρησιμοποιώντας 
 * το πρωτόκολλο WalletConnect V3 και την τεχνολογία SIWE (Sign-In with Ethereum).
 */
session_start();

// Έλεγχος αν υπάρχει ήδη ενεργή συνεδρία
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | SECURE GATEWAY</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/5.7.2/ethers.umd.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        
        body { 
            background: radial-gradient(circle at center, #0f172a 0%, #020617 100%); 
            font-family: 'Inter', sans-serif; 
            margin: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .terminal-border { 
            border: 1px solid rgba(59, 130, 246, 0.2); 
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.7); 
        }

        .glass-effect { 
            background: rgba(15, 23, 42, 0.8); 
            backdrop-filter: blur(12px); 
        }

        button:disabled { 
            opacity: 0.6; 
            cursor: not-allowed; 
        }

        .animate-ping-slow { 
            animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite; 
        }

        @keyframes ping {
            75%, 100% { transform: scale(2); opacity: 0; }
        }

        .hidden-btn { display: none; }
    </style>
</head>
<body class="min-h-screen p-4">

    <div class="max-w-md w-full glass-effect terminal-border p-10 rounded-[2.5rem] text-center">
        <div class="mb-10">
            <div class="w-24 h-24 bg-blue-600/10 rounded-3xl flex items-center justify-center mx-auto mb-6 border border-blue-500/20 shadow-inner">
                <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter italic uppercase">CT-OS v5.9</h1>
            <p class="text-slate-500 text-sm mt-2 font-medium tracking-wide uppercase">Universal Wallet Gateway</p>
        </div>

        <button id="connectBtn" class="group relative w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-5 rounded-2xl transition-all active:scale-95 shadow-2xl shadow-blue-600/20 overflow-hidden">
            <span id="btnText" class="relative z-10">CONNECT UNIVERSAL WALLET</span>
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
        </button>

        <button id="entryBtn" class="hidden-btn group relative w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-5 rounded-2xl transition-all active:scale-95 shadow-2xl shadow-emerald-600/20 overflow-hidden mt-4">
            <span class="relative z-10">ENTRY TO CT-OS</span>
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
        </button>

        <div class="mt-8 flex flex-col items-center gap-3">
            <div class="flex items-center gap-2 text-[10px] text-slate-500 uppercase tracking-[0.2em] font-bold">
                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-ping-slow"></span>
                Node Status: <span id="nodeStatus">Online / Secure-Mainnet</span>
            </div>
            <p class="text-[9px] text-slate-600 max-w-[200px] leading-relaxed uppercase tracking-tighter">
                Cryptographic Handshake Protocol Active. WalletConnect Cloud V3 Integrated.
            </p>
        </div>
    </div>

    <script type="module">
        import { createAppKit } from 'https://esm.sh/@reown/appkit'
        import { Ethers5Adapter } from 'https://esm.sh/@reown/appkit-adapter-ethers5'
        import { mainnet } from 'https://esm.sh/@reown/appkit/networks'

        const projectId = '5de6432ffe26a24fc4aa142e25dfd09b';

        const metadata = {
            name: 'CT-OS Terminal',
            description: 'Secure Operator Terminal',
            url: window.location.origin,
            icons: ['https://avatars.githubusercontent.com/u/37784886']
        }

        const modal = createAppKit({
            adapters: [new Ethers5Adapter()],
            networks: [mainnet],
            metadata,
            projectId,
            features: { 
                analytics: true,
                swaps: false,
                onramp: false
            },
            themeMode: 'dark'
        })

        let redirectUrl = 'index.php'; // Default redirect

        async function startTerminalAuth() {
            const btn = document.getElementById('connectBtn');
            const entryBtn = document.getElementById('entryBtn');
            const status = document.getElementById('btnText');
            const nodeStatus = document.getElementById('nodeStatus');

            try {
                await modal.open();
                const walletProvider = await modal.getWalletProvider();
                
                if (!walletProvider) return;

                btn.disabled = true;
                status.innerText = "INITIALIZING...";

                const provider = new ethers.providers.Web3Provider(walletProvider);
                const signer = provider.getSigner();
                const address = (await signer.getAddress()).toLowerCase();

                status.innerText = "SYNCHRONIZING...";
                
                const nRes = await fetch(`get_siwe_nonce.php?address=${address}`);
                const nData = await nRes.json();
                
                if (!nData.success) throw new Error(nData.message || "Nonce fetch failed.");

                const message = `Sign in to CT-OS Terminal\nAddress: ${address}\nNonce: ${nData.nonce}`;
                const signature = await signer.signMessage(message);

                status.innerText = "VERIFYING...";

                const vRes = await fetch('siwe_verify.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message, signature })
                });

                const vData = await vRes.json();
                
                if (vData.success) {
                    // Αλλαγή UI: Κρύβουμε το Connect, δείχνουμε το Entry
                    btn.classList.add('hidden-btn');
                    entryBtn.classList.remove('hidden-btn');
                    
                    nodeStatus.innerText = "ACCESS GRANTED - OPERATOR IDENTIFIED";
                    nodeStatus.classList.remove('text-slate-500');
                    nodeStatus.classList.add('text-emerald-500');

                    // Αποθήκευση του URL για το 2FA αν χρειάζεται
                    redirectUrl = vData.require_2fa ? 'verify_2fa.php' : 'index.php';
                    
                    // Αυτόματο κλείσιμο του modal αν έμεινε ανοιχτό
                    modal.close();
                } else {
                    throw new Error(vData.message || "Verification failed.");
                }

            } catch (err) {
                console.error("Auth Error:", err);
                btn.disabled = false;
                status.innerText = "RETRY CONNECTION";
                if (err.code !== 4001) {
                    alert("Security Alert: " + (err.message || "Unknown Error"));
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const loginBtn = document.getElementById('connectBtn');
            const entryBtn = document.getElementById('entryBtn');

            if (loginBtn) {
                loginBtn.onclick = (e) => {
                    e.preventDefault();
                    startTerminalAuth();
                };
            }

            if (entryBtn) {
                entryBtn.onclick = () => {
                    window.location.replace(redirectUrl);
                };
            }
        });
    </script>
</body>
</html>