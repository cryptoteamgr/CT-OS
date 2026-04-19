<script>
let connectedAddress = null;

const btn = document.getElementById('connectBtn');
const btnText = document.getElementById('btnText');
const status = document.getElementById('status');

async function processFlow() {
    // 1. ΕΛΕΓΧΟΣ ANDROID / IOS REDIRECT
    if (typeof window.ethereum === 'undefined') {
        const currentUrl = window.location.host + window.location.pathname;
        
        if (/Android/i.test(navigator.userAgent)) {
            // FORCE ANDROID HANDSHAKE
            // Δοκιμή 1: Dapp Scheme (Πιο άμεσο)
            window.location.href = `dapp://${currentUrl}`;
            
            // Δοκιμή 2: Intent μετά από 500ms αν αποτύχει το πρώτο
            setTimeout(() => {
                window.location.href = `intent://${currentUrl}#Intent;scheme=https;package=io.metamask;end`;
            }, 500);
            return;
        } 
        else if (/iPhone|iPad/i.test(navigator.userAgent)) {
            // iOS Standard Deep Link
            window.location.href = `https://metamask.app.link/dapp/${currentUrl}`;
            return;
        } else {
            alert("MetaMask not found! Please install the extension.");
            return;
        }
    }

    const provider = new ethers.providers.Web3Provider(window.ethereum);

    try {
        // ΒΗΜΑ 1: ΣΥΝΔΕΣΗ
        if (!connectedAddress) {
            status.innerText = "OPENING METAMASK...";
            const accounts = await provider.send("eth_requestAccounts", []);
            connectedAddress = accounts[0].toLowerCase();
            
            btn.classList.replace('bg-orange-600', 'bg-green-600');
            btnText.innerText = "AUTHORIZE ACCESS";
            status.innerText = "LINKED. PRESS AGAIN TO SIGN.";
            return; 
        }

        // ΒΗΜΑ 2: ΥΠΟΓΡΑΦΗ
        btn.disabled = true;
        status.innerText = "FETCHING SECURITY NONCE...";
        
        const nonceRes = await fetch(`get_siwe_nonce.php?address=${connectedAddress}`);
        const nonceData = await nonceRes.json();
        if (!nonceData.success) throw new Error(nonceData.message);

        const domain = window.location.host;
        const message = `${domain} wants you to sign in with your Ethereum account:\n${connectedAddress}\n\nAuthorize CT-OS Terminal Access.\n\nNonce: ${nonceData.nonce}`;

        // Το alert βοηθάει το λειτουργικό να κρατήσει ενεργό το session κατά την εναλλαγή των apps
        alert("Action Required: Switch to MetaMask to sign the request.");
        
        const signature = await provider.send("personal_sign", [message, connectedAddress]);

        status.innerText = "VERIFYING IDENTITY...";
        const verifyResp = await fetch('siwe_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message, signature })
        });

        const result = await verifyResp.json();
        if (result.success) {
            status.innerText = "ACCESS GRANTED";
            window.location.replace('index.php');
        } else {
            throw new Error(result.message || "Invalid Signature");
        }

    } catch (e) {
        console.error(e);
        status.innerText = "CONNECTION INTERRUPTED";
        btn.disabled = false;
        btnText.innerText = "RETRY METAMASK";
    }
}

btn.onclick = (e) => {
    e.preventDefault();
    processFlow();
};

// Αυτόματο recovery (Σημαντικό για Android που "ξεχνάει" το session στην εναλλαγή)
document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === 'visible') {
        fetch('check_session.php')
            .then(res => res.json())
            .then(data => {
                if (data.loggedIn) window.location.replace('index.php');
            }).catch(() => {});
    }
});
</script>