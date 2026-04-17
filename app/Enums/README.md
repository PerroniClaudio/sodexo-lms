# Enum Utente - Stati e Onboarding

Questa directory contiene gli enum per la gestione degli stati utente e del processo di onboarding.

## UserStatus (Stato Account)

Rappresenta lo **stato generale dell'account utente**.

### Stati Disponibili

```php
PENDING          // Utente creato, aspetta verifica email
ONBOARDING       // Email verificata, sta completando il profilo
ACTIVE           // Utente operativo, può accedere alla piattaforma
UPDATE_REQUIRED  // Richiesto aggiornamento dati
SUSPENDED        // Account bloccato temporaneamente
```

### Quando si Usa

- **PENDING**: L'admin crea l'utente e invia email di verifica. L'utente rimane qui finché non clicca il link.
- **ONBOARDING**: Dopo verifica email, l'utente deve completare gli step (password, profilo).
- **ACTIVE**: Profilo completo, utente può usare la piattaforma normalmente.
- **UPDATE_REQUIRED**: L'admin richiede aggiornamento dati (es. per conformità normativa).
- **SUSPENDED**: Account temporaneamente disabilitato (es. fine contratto).

## OnboardingStep (Step di Onboarding)

Rappresenta lo **step specifico durante la fase ONBOARDING**.

⚠️ **Importante**: Questo campo è valorizzato SOLO quando `account_state = ONBOARDING`.

### Step Disponibili

```php
PASSWORD_SETUP       // Utente deve impostare la password
PROFILE_COMPLETION   // Utente deve completare i dati del profilo
```

### Progressione

Gli step si completano in sequenza:
1. **PASSWORD_SETUP** (50% completato)
2. **PROFILE_COMPLETION** (100% completato → diventa ACTIVE)

## Flusso Completo

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. ADMIN CREA UTENTE                                            │
│    account_state: PENDING                                        │
│    onboarding_step: null                                         │
│    email_verified_at: null                                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ Utente clicca link email
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. EMAIL VERIFICATA                                             │
│    account_state: ONBOARDING                                     │
│    onboarding_step: PASSWORD_SETUP                               │
│    email_verified_at: now()                                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ Utente imposta password
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. PASSWORD IMPOSTATA                                           │
│    account_state: ONBOARDING                                     │
│    onboarding_step: PROFILE_COMPLETION                           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ Utente completa profilo
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. PROFILO COMPLETATO                                           │
│    account_state: ACTIVE                                         │
│    onboarding_step: null                                         │
│    profile_completed_at: now()                                   │
└─────────────────────────────────────────────────────────────────┘
```

## Differenza tra PENDING e ONBOARDING

**PENDING:**
- Stato iniziale dopo creazione
- Email NON ancora verificata
- Link inviato ma non cliccato
- Può rimanere qui per giorni/settimane/mesi
- `onboarding_step` è sempre `null`

**ONBOARDING:**
- Email verificata ✅
- Utente sta completando il profilo
- `onboarding_step` indica lo step corrente
- Dovrebbe essere completato in pochi minuti

## Metodi Helper

### UserStatus

```php
$status = UserStatus::ACTIVE;

$status->label();                // -> "Attivo"
$status->badgeColor();           // -> "badge-success"
$status->canAccessPlatform();    // -> true
$status->needsOnboarding();      // -> false
$status->isBlocked();            // -> false
```

### OnboardingStep

```php
$step = OnboardingStep::PASSWORD_SETUP;

$step->label();                  // -> "Impostazione Password"
$step->next();                   // -> OnboardingStep::PROFILE_COMPLETION
$step->progressPercentage();     // -> 50
```

### User Model

```php
$user->canAccessPlatform();      // Verifica se può accedere
$user->needsOnboarding();        // Controlla se serve onboarding
$user->isBlocked();              // Account sospeso
$user->onboardingProgress();     // -> 50 (percentuale)

// Azioni
$user->moveToOnboarding();       // Sposta a ONBOARDING con step PASSWORD_SETUP
$user->advanceOnboardingStep();  // Passa allo step successivo
$user->markProfileAsCompleted(); // Completa onboarding → ACTIVE
$user->requestDataUpdate();      // -> UPDATE_REQUIRED
$user->markDataAsUpdated();      // -> ACTIVE
$user->suspend();                // -> SUSPENDED
$user->reactivate();             // -> ACTIVE
```

## Middleware

Il middleware `EnsureUserOnboarded` controlla:

1. ✅ **SUSPENDED** → Logout + redirect login con errore
2. ✅ **PENDING/ONBOARDING** → Redirect a onboarding flow
3. ✅ **UPDATE_REQUIRED** → Redirect a profile update con warning
4. ✅ **ACTIVE** → Accesso consentito

## Factory States

```php
// Stati per testing
User::factory()->pending()->create();
User::factory()->onboarding()->create();
User::factory()->updateRequired()->create();
User::factory()->suspended()->create();
```

## Note Importanti

- ⚠️ **EMAIL_VERIFICATION** non esiste più negli step (gestito da PENDING → ONBOARDING)
- ✅ `onboarding_step` è `null` quando non in fase ONBOARDING
- ✅ La verifica email è gestita da Laravel Fortify
- ✅ Il soft delete (`deleted_at`) è separato da SUSPENDED
