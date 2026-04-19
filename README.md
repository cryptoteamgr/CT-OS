#  CT-OS | Crypto Team Operating System

Het **CT-OS** is een volledig systeem voor Statistical Arbitrage (Pair Trading) voor de crypto futures markt (Binance). Het gebruikt geavanceerde statistische indicatoren zoals **Z-Score** en **Beta Correlation** om trades te detecteren en uit te voeren tussen gecorreleerde muntparen.

##  Technische Kenmerken
- **Engine:** PHP 8.x / MySQL
- **Data Depth:** 500 hours (3+ weken historische data) voor maximale nauwkeurigheid.
- **Indicators:** 
  - Dynamic Z-Score Calculation
  - Beta Coefficient (Correlation Filtering)
  - Real-time Price Aggregation
  - Beta Weighting met Leverage Calculation
- **Interface:** Volledig Dashboard met Demo & Live Trading modes.
- **Security:** 2FA Authentication, Encrypted API Keys, Role-based Access

##  Bestandsstructuur
- `pair_scanner.php`: De "jager". Scant de markt voor signalen op basis van Z-Score.
- `auto_universe_sync.php`: De "boekhouder". Synchroniseert paren en berekent statistieken (Correlation/Beta).
- `price_aggregator.php`: De "verzamelaar". Verzamelt prijzen elk uur in de database.
- `binance_history_seeder.php`: Het initialisatiehulpmiddel (Quick Seeder) voor 500 candles.
- `terminal.php`: De User Interface van de Bot.
- `functions.php`: Gecentraliseerde berekeningen en utility functies.
- `get_pnl.php`: Real-time PnL en Beta Weighting berekeningen.
- `cron_monitor.php`: Trade monitoring en automatische sluiting.

##  Nieuwe Features (v13.0+)
- **Beta Weighting Fix**: Correcte berekening met leverage (Capital × Leverage)
- **Gecentraliseerde Berekeningen**: Alle berekeningen in `functions.php` voor consistentie
- **Timezone Fix**: Correcte tijdsregistratie voor trade sluiting
- **Admin Panel**: Volledige gebruikersbeheer en systeemconfiguratie
- **Broadcast System**: Real-time systeemmeldingen en alerts
- **Alert System**: Geavanceerde alerts voor Z-Score, correlatie, drawdown
- **API Rate Limiting**: Bescherming tegen API abuse

##  Installatie-instructies
1. Upload de bestanden naar je server.
2. Configureer de database in `db_config.php` (Uitgesloten van Git).
3. Voer de Quick Seeder uit om de 500-uur geschiedenis te vullen.
4. Configureer de Cron Jobs:
   - `price_aggregator.php` (Elke 1 uur)
   - `pair_scanner.php` (Elke 1 minuut)
   - `auto_universe_sync.php` (Elke 1 uur)
   - `cron_monitor.php` (Elke 5 minuten voor trade monitoring)

##  Risk Management
- De Bot opent geen trades als het **Beta** lager is dan **0.7**.
- Er is **Asset Exposure** bescherming (opent geen tweede trade in dezelfde munt).
- Ondersteunt Stop Loss en Take Profit op dollarniveau.
- **Beta Weighting**: Toont de werkelijke exposure met leverage (Capital × Leverage)

##  Troubleshooting
- **Beta Weighting incorrect**: Controleer of `functions.php` de `calculateBetaWeighting()` functie bevat
- **Trade sluiting niet geregistreerd**: Controleer timezone settings in `cron_monitor.php`
- **API Keys niet werkend**: Controleer of de keys correct geëncrypteerd zijn in de database
- **Dashboard toont geen trades**: Controleer of `get_pnl.php` correcte data teruggeeft

##  Security
- **2FA Authentication**: Google Authenticator ondersteuning
- **Encrypted API Keys**: Alle API keys zijn geëncrypteerd in de database
- **Role-based Access**: Admin-only gebieden met juiste authenticatie
- **SQL Injection Protection**: PDO prepared statements voor alle queries
- **XSS Protection**: Input sanitization en output escaping

---
*Developed by cryptoteam.gr*
