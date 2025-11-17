# 🚂 Railway Deployment Setup - Fury of Sparta

## Persistent Storage Configuration

Questo progetto è configurato per usare **Railway Volumes** per mantenere i dati persistenti tra i deploy.

### 📦 File che verranno salvati nel volume:
- `licenses.json` - Database delle licenze
- `license_log.txt` - Log delle attività

---

## 🛠️ Come Configurare il Volume su Railway

### Opzione 1: Via Dashboard (Consigliato)

1. **Vai al tuo progetto su Railway**
   - Apri https://railway.app
   - Seleziona il progetto `fop-dashboard`

2. **Aggiungi un Volume**
   - Clicca sulla tua applicazione
   - Vai alla tab **"Variables"** o **"Settings"**
   - Cerca la sezione **"Volumes"**
   - Clicca su **"+ New Volume"**

3. **Configura il Volume**
   - **Mount Path**: `/data`
   - **Name**: `fop-persistent-storage` (opzionale)
   - Clicca **"Add"**

4. **Redeploy**
   - Railway farà automaticamente un redeploy
   - Il volume sarà montato in `/data`

### Opzione 2: Via Railway CLI

```bash
# Installa Railway CLI
npm i -g @railway/cli

# Login
railway login

# Link al progetto
railway link

# Aggiungi il volume
railway volume add --mount-path /data --name fop-persistent-storage

# Redeploy
railway up
```

---

## ✅ Verifica che il Volume Funzioni

Dopo il deploy, controlla i log dell'applicazione. Dovresti vedere:

```
🔱 Fury of Sparta - Initializing...
✅ Persistent volume detected at /data
📝 Creating initial licenses.json...
📝 Creating initial license_log.txt...
✅ Data directory initialized successfully
   - Licenses: /data/licenses.json
   - Logs: /data/license_log.txt
🚀 Starting Fury of Sparta Dashboard on port 8080...
```

Se vedi `⚠️ No persistent volume found`, il volume non è stato configurato correttamente.

---

## 🔐 Configurazione Variabili d'Ambiente (Opzionale)

Puoi anche configurare variabili d'ambiente su Railway:

- `PORT` - Porta del server (default: 8080)
- Altre variabili future se necessario

---

## 📍 URL del tuo progetto

Dopo il deploy, il tuo URL sarà:
```
https://[nome-progetto].up.railway.app
```

**Per MetaTrader WebRequest**, userai:
```
https://[nome-progetto].up.railway.app/admin-licenses.php
```

(In futuro creeremo un endpoint API dedicato come `/api.php`)

---

## 🆘 Troubleshooting

### Il volume non viene creato
- Assicurati di aver configurato il volume nella dashboard Railway
- Verifica che il mount path sia esattamente `/data`
- Controlla i log per messaggi di errore

### I dati non persistono
- Verifica che nei log appaia "Persistent volume detected"
- Controlla che il volume sia effettivamente montato su `/data`
- Riavvia l'applicazione su Railway

### Permessi di scrittura
- Lo script `start.sh` gestisce automaticamente i permessi
- Se hai problemi, controlla i log di Railway

---

## 📊 Backup dei Dati

Railway mantiene automaticamente i backup dei volumi, ma è buona pratica:

1. Scaricare periodicamente `licenses.json` tramite il dashboard
2. Esportare i log se necessario
3. Tenere una copia locale di backup

---

✅ **Tutto pronto!** Ora i tuoi dati rimarranno persistenti anche dopo i redeploy.
