# Guida Creazione Utenti - Sodexo LMS

## Comando Base

```bash
php artisan user:create [email] [nome] [cognome] [codice_fiscale] [ruolo]
```

---

## Argomenti Disponibili

### Argomenti Posizionali (Opzionali)

| Argomento | Descrizione | Obbligatorio | Default |
|-----------|-------------|--------------|---------|
| `email` | Email dell'utente | No (verrà richiesto) | - |
| `name` | Nome dell'utente | No (verrà richiesto) | - |
| `surname` | Cognome dell'utente | No (verrà richiesto) | - |
| `fiscal_code` | Codice fiscale (16 caratteri) | No (verrà richiesto) | - |
| `role` | Ruolo utente | No | `user` |

### Opzioni Disponibili

| Opzione | Descrizione | Utilizzo |
|---------|-------------|----------|
| `--active` | Crea utente già attivo (salta email di verifica) | Per admin/test |
| `--password=PASSWORD` | Password personalizzata (solo con --active) | Default: `Sodexo@Learning.26` |
| `--job-unit=ID` | ID Unità lavorativa | **Obbligatorio per ruolo `user`** |
| `--job-title=ID` | ID Mansione | **Obbligatorio per ruolo `user`** |
| `--job-role=ID` | ID Ruolo lavorativo | **Obbligatorio per ruolo `user`** |
| `--job-sector=ID` | ID Settore | **Obbligatorio per ruolo `user`** |

---

## Ruoli Disponibili

- **`superadmin`** - Amministratore totale del sistema
- **`admin`** - Amministratore area back-office
- **`docente`** - Docente/Formatore
- **`tutor`** - Tutor di corso
- **`user`** - Utente discente (ruolo predefinito)

---

## ⚠️ Regole Importanti

### Campi Job Obbligatori

**SOLO per ruolo `user`** sono obbligatori i seguenti campi:
- `--job-unit` (Unità lavorativa)
- `--job-title` (Mansione)
- `--job-role` (Ruolo)
- `--job-sector` (Settore)

Per ruoli `superadmin`, `admin`, `docente`, `tutor` **NON sono richiesti**.

### Modalità di Creazione

#### 📧 **Modalità Normale** (senza `--active`)
- Stato: `PENDING` (In attesa di attivazione)
- L'utente riceve email di verifica
- Deve verificare email + impostare password
- Poi completa profilo (dati opzionali)
- Solo dopo diventa `ACTIVE`

#### 🔧 **Modalità Test/Setup** (con `--active`)
- Stato: `ACTIVE` (Subito attivo)
- Email già verificata
- Profilo già completato
- Login immediato
- Password default: `Sodexo@Learning.26`
- **NON invia email**

---

## Esempi Pratici

### 1. Superadmin (Setup Iniziale)
```bash
php artisan user:create \
  superadmin@sodexo.it \
  "Super" \
  "Admin" \
  SPRADM80A01H501Z \
  superadmin \
  --active
```
**Password**: `Sodexo@Learning.26`

### 2. Admin
```bash
php artisan user:create \
  admin@sodexo.it \
  "Mario" \
  "Rossi" \
  RSSMRA80A01H501Z \
  admin \
  --active
```

### 3. Docente
```bash
php artisan user:create \
  docente@sodexo.it \
  "Laura" \
  "Bianchi" \
  BNCLRA75M45F205Z \
  docente \
  --active
```

### 4. Tutor
```bash
php artisan user:create \
  tutor@sodexo.it \
  "Giuseppe" \
  "Verdi" \
  VRDGPP82C10H501Z \
  tutor \
  --active
```

### 5. Utente Normale (con campi job)
```bash
php artisan user:create \
  dipendente@sodexo.it \
  "Francesca" \
  "Neri" \
  NREFNC90D45F205Z \
  user \
  --job-unit=1 \
  --job-title=2 \
  --job-role=3 \
  --job-sector=1
```
**Nota**: Riceverà email di attivazione (non usare `--active` per utenti reali)

### 6. Utente Test (già attivo con password custom)
```bash
php artisan user:create \
  test@sodexo.it \
  "Test" \
  "User" \
  TSTUSR90A01H501Z \
  user \
  --active \
  --password="MiaPassword123!" \
  --job-unit=1 \
  --job-title=1 \
  --job-role=1 \
  --job-sector=1
```

### 7. Modalità Interattiva
```bash
php artisan user:create
```
Il comando chiederà tutti i dati uno per uno.

---

## 🔍 Verifica ID Job Disponibili

Prima di creare utenti con ruolo `user`, verifica gli ID disponibili:

### Unità Lavorative
```bash
php artisan tinker --execute="App\Models\JobUnit::select('id', 'name')->get()->each(fn(\$j) => echo \"ID: {\$j->id} - {\$j->name}\n\");"
```

### Mansioni
```bash
php artisan tinker --execute="App\Models\JobTitle::select('id', 'name')->get()->each(fn(\$j) => echo \"ID: {\$j->id} - {\$j->name}\n\");"
```

### Ruoli Lavorativi
```bash
php artisan tinker --execute="App\Models\JobRole::select('id', 'name')->get()->each(fn(\$j) => echo \"ID: {\$j->id} - {\$j->name}\n\");"
```

### Settori
```bash
php artisan tinker --execute="App\Models\JobSector::select('id', 'name')->get()->each(fn(\$j) => echo \"ID: {\$j->id} - {\$j->name}\n\");"
```

---

## ❌ Errori Comuni

### 1. Email già esistente
```
Errore di validazione:
  • The email has already been taken.
```
**Soluzione**: Usa un'email diversa

### 2. Codice fiscale già esistente
```
Errore di validazione:
  • The fiscal code has already been taken.
```
**Soluzione**: Usa un codice fiscale diverso

### 3. Campi job mancanti per utente normale
```
Errore durante la creazione dell'utente: L'unità lavorativa è obbligatoria per gli utenti. (and 3 more errors)
```
**Soluzione**: Aggiungi tutti i 4 parametri job:
```bash
--job-unit=1 --job-title=1 --job-role=1 --job-sector=1
```

### 4. Codice fiscale non valido
```
Errore di validazione:
  • The fiscal code must be 16 characters.
```
**Soluzione**: Il codice fiscale deve essere esattamente 16 caratteri

---

## 🏭 Uso del Factory per Test/Sviluppo

### Creare Utenti con Factory

Il `UserFactory` segue le stesse regole del comando:
- **Admin/Superadmin/Docente/Tutor**: campi job NULL
- **User normale**: richiede campi job (usa `->withJobData()`)

### Esempi Factory

#### Admin/Superadmin/Docente/Tutor (senza job data)
```php
use App\Models\User;

// Admin senza campi job
$admin = User::factory()->create();
$admin->assignRole('admin');

// Superadmin
$superadmin = User::factory()->create();
$superadmin->assignRole('superadmin');

// Docente
$docente = User::factory()->create();
$docente->assignRole('docente');

// Tutor
$tutor = User::factory()->create();
$tutor->assignRole('tutor');
```

#### User Normale (con job data)
```php
// User con dati job completi
$user = User::factory()->withJobData()->create();
$user->assignRole('user');

// User in stato PENDING (deve fare onboarding)
$pendingUser = User::factory()
    ->withJobData()
    ->pending()
    ->create();
$pendingUser->assignRole('user');
```

#### Stati Disponibili

```php
// Utente in pending (non ancora attivato)
->pending()

// Utente straniero/immigrato
->foreignerOrImmigrant()

// Utente con dati job (obbligatorio per role 'user')
->withJobData()
```

### Creare Batch di Utenti

```php
// 10 admin
User::factory()->count(10)->create()->each(fn($u) => $u->assignRole('admin'));

// 50 utenti normali con job data
User::factory()
    ->withJobData()
    ->count(50)
    ->create()
    ->each(fn($u) => $u->assignRole('user'));
```

---

## 📋 Checklist Creazione Utente

- [ ] Email univoca (non già usata)
- [ ] Codice fiscale valido (16 caratteri) e univoco
- [ ] Ruolo corretto: `superadmin`, `admin`, `docente`, `tutor`, o `user`
- [ ] Se ruolo = `user`, aggiungi i 4 parametri job obbligatori
- [ ] Se utente per setup/test, usa `--active`
- [ ] Se utente reale, NON usare `--active` (riceverà email)

---

## 📊 Riepilogo Utenti Creati

Visualizza tutti gli utenti:
```bash
php artisan tinker --execute="App\Models\User::all()->each(fn(\$u) => printf('ID: %d | Email: %-30s | Ruolo: %-12s | Stato: %s\n', \$u->id, \$u->email, \$u->getRoleNames()->first(), \$u->account_state->label()));"
```

---

## 🔐 Credenziali Default

**Per tutti gli utenti creati con `--active`:**
- Password: `Sodexo@Learning.26`
- Stato: Attivo (login immediato)

**Per utenti senza `--active`:**
- Devono completare attivazione via email
- Imposteranno la password durante l'attivazione
