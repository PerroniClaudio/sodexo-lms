# Sistema di Gestione Rischi Sicurezza (Accordo Stato-Regioni)

## Panoramica

Il sistema calcola dinamicamente i livelli di rischio sulla sicurezza secondo il principio del **rischio maggiore**, utilizzando codici ATECO (NACE) e mappature personalizzate di settori e mansioni.

## Architettura

### Tabelle del Database

#### `nace_ateco`
Contiene l'albero completo dei codici ATECO con gerarchia e livelli di rischio.

**Campi principali:**
- `code` (PK): Il codice ATECO (es. 'Q', '86', '86.90', '86.90.11')
- `section`: Lettera della macro-sezione (es. 'Q')
- `hierarchy`: Livello gerarchico (1=Sezione, 2=Divisione, 3=Gruppo, 4=Classe NACE, 5=Categoria, 6=Sottocategoria)
- `title_it` / `title_en`: Descrizione del codice
- `risk`: Livello di rischio (low, medium, high)

#### `job_sectors`
Settori aziendali personalizzati.

**Campi:**
- `id` (PK)
- `name`: Nome del settore
- `code`: Codice univoco
- `description`: Descrizione

#### `job_titles`
Mansioni dei lavoratori.

**Campi:**
- `id` (PK)
- `name`: Nome della mansione
- `code`: Codice univoco
- `description`: Descrizione

#### `job_sector_nace_ateco` (PIVOT)
Mappa i settori ai codici ATECO con tipo di inclusione.

**Campi:**
- `job_sector_id`: FK a job_sectors
- `nace_ateco_code`: FK a nace_ateco
- `inclusion_type`: Tipo di inclusione (section, division, group, class, category, full_code)

**Esempio:**
Un settore "Sanità" può includere:
- Intera sezione 'Q' (tipo: section)
- Divisione '86' (tipo: division)
- Codice specifico '86.90.11' (tipo: full_code)

#### `job_sector_job_title` (PIVOT)
Mappa il rischio specifico di una mansione dentro un settore.

**Campi:**
- `job_sector_id`: FK a job_sectors
- `job_title_id`: FK a job_titles
- `title_risk_level`: Rischio della mansione nel settore (low, medium, high)

## Funzioni di Calcolo

### 1. `getSectorRiskLevel(int $jobSectorId): RiskLevel`

Calcola il **rischio nativo** del settore personalizzato.

**Logica:**
1. Recupera tutti i codici ATECO associati al settore dalla pivot `job_sector_nace_ateco`
2. Per ogni inclusione, espande i codici figli in base al tipo:
   - `section`: Tutti i codici con quella sezione
   - `division`, `group`, `class`, `category`: Tutti i codici figli
   - `full_code`: Solo quel codice specifico
3. Recupera i livelli di rischio di tutti i codici espansi
4. Restituisce il **rischio più alto** trovato

**Esempio:**
```php
use App\Services\RiskCalculationService;

$service = new RiskCalculationService();
$sectorRisk = $service->getSectorRiskLevel($jobSectorId);
// Ritorna: RiskLevel::HIGH
```

**Helper nel modello:**
```php
$jobSector = JobSector::find(1);
$risk = $jobSector->getRiskLevel();
```

### 2. `getEffectiveWorkerRisk(int $jobSectorId, int $jobTitleId): RiskLevel`

Calcola il **rischio effettivo** di un lavoratore.

**Logica:**
1. Calcola il rischio del settore (via `getSectorRiskLevel`)
2. Recupera il rischio specifico della mansione dalla pivot `job_sector_job_title`
3. Restituisce il **rischio più alto** tra settore e mansione

**Esempio:**
```php
$service = new RiskCalculationService();
$workerRisk = $service->getEffectiveWorkerRisk($jobSectorId, $jobTitleId);
```

**Helper nel modello:**
```php
$jobSector = JobSector::find(1);
$risk = $jobSector->getEffectiveWorkerRisk($jobTitleId);
```

**Caso d'uso:**
- Settore "Sanità" ha rischio MEDIUM (basato su codici ATECO)
- Mansione "Infermiere" ha rischio HIGH (per esposizione biologica)
- **Risultato:** HIGH (il maggiore tra i due)

### 3. `findSectorByAtecoCode(string $fullAtecoCode): ?JobSector`

Trova il settore partendo dal codice ATECO completo a 6 cifre (risalita gerarchica).

**Logica:**
1. Costruisce la catena gerarchica dal codice specifico alla sezione
   - Es. '86.90.11' → '86.90' → '86' → 'Q'
2. Cerca nella pivot `job_sector_nace_ateco` in ordine di specificità
3. Restituisce il primo settore trovato

**Esempio:**
```php
$service = new RiskCalculationService();
$sector = $service->findSectorByAtecoCode('86.90.11');
// Potrebbe trovare un settore mappato a '86.90.11', '86', o 'Q'
```

**Helper nel modello:**
```php
$naceAteco = NaceAteco::find('86.90.11');
$sector = $naceAteco->findJobSector();
```

### 4. `getSectionForCode(string $atecoCode): ?NaceAteco`

Ottimizzazione: recupera l'anagrafica completa della macro-sezione.

**Logica:**
1. Legge il campo `section` del codice fornito
2. Cerca e restituisce il record con `code` = quella lettera

**Esempio:**
```php
$service = new RiskCalculationService();
$section = $service->getSectionForCode('86.90.11');
// Ritorna il record con code='Q' (Sanità e assistenza sociale)
```

**Helper nel modello:**
```php
$naceAteco = NaceAteco::find('86');
$section = $naceAteco->getSectionRecord();
echo $section->title_it; // "Sanità e assistenza sociale"
```

## Enum `InclusionType`

Definisce il livello di granularità dell'inclusione ATECO:

- `SECTION`: Intera sezione (es. 'Q')
- `DIVISION`: Divisione (es. '86')
- `GROUP`: Gruppo
- `NACE_CLASS`: Classe NACE (4 cifre)
- `CATEGORY`: Categoria (es. '86.90')
- `FULL_CODE`: Codice completo (6 cifre, es. '86.90.11')

## Enum `RiskLevel`

Livelli di rischio con metodi di confronto:

- `LOW`: Rischio basso
- `MEDIUM`: Rischio medio
- `HIGH`: Rischio alto

**Metodi utili:**
```php
$risk1 = RiskLevel::MEDIUM;
$risk2 = RiskLevel::HIGH;

$risk1->max($risk2); // RiskLevel::HIGH
$risk1->isHigherThan($risk2); // false
$risk1->order(); // 2
```

## Esempi d'Uso Completi

### Creare un Settore con Codici ATECO

```php
use App\Models\JobSector;
use App\Enums\InclusionType;

// Crea settore personalizzato
$sector = JobSector::create([
    'name' => 'Sanità Privata',
    'code' => 'SANITA_PRIV',
    'description' => 'Strutture sanitarie private',
]);

// Includi intera divisione 86
$sector->naceAtecoCodes()->attach('86', [
    'inclusion_type' => InclusionType::DIVISION->value,
]);

// Aggiungi anche un codice specifico da un'altra sezione
$sector->naceAtecoCodes()->attach('87.10.00', [
    'inclusion_type' => InclusionType::FULL_CODE->value,
]);
```

### Mappare Rischio Mansione in Settore

```php
use App\Models\JobSector;
use App\Models\JobTitle;
use App\Enums\RiskLevel;

$sector = JobSector::find(1);
$jobTitle = JobTitle::where('code', 'INFERMIERE')->first();

// Definisci che "Infermiere" ha rischio HIGH nel settore Sanità
$sector->jobTitles()->attach($jobTitle->id, [
    'title_risk_level' => RiskLevel::HIGH->value,
]);
```

### Calcolare il Rischio di un Lavoratore

```php
use App\Models\User;

$user = User::find(1);
$sectorId = $user->job_sector_id;
$titleId = $user->job_title_id;

// Calcola rischio effettivo
$risk = $user->jobSector->getEffectiveWorkerRisk($titleId);

echo $risk->label(); // "Rischio Alto"
echo $risk->badgeColor(); // "badge-error"
```

### Trovare Settore da Codice ATECO Aziendale

```php
use App\Services\RiskCalculationService;

$companyAtecoCode = '86.90.11'; // Codice ATECO a 6 cifre dell'azienda

$service = new RiskCalculationService();
$sector = $service->findSectorByAtecoCode($companyAtecoCode);

if ($sector) {
    echo "Settore: {$sector->name}";
    echo "Rischio: " . $sector->getRiskLevel()->label();
}
```

## Migrazioni

Le migrazioni sono state create nell'ordine:

1. `2026_05_20_145538_create_nace_ateco_table` - Tabella codici ATECO
2. `2026_05_21_125719_create_job_sector_nace_ateco_table` - Pivot settori-ATECO
3. `2026_05_21_125725_create_job_sector_job_title_table` - Pivot settori-mansioni
4. `2026_05_21_125956_remove_nace_ateco_code_from_job_sectors_table` - Rimozione vecchia relazione 1-a-1

## Test

I test sono disponibili in `tests/Unit/Services/RiskCalculationServiceTest.php`.

Esegui con:
```bash
php artisan test --filter=RiskCalculationServiceTest
```

## Note Tecniche

- Il sistema utilizza il **principio del rischio maggiore**: quando più codici ATECO o rischi mansione sono presenti, vince sempre quello più alto
- La risalita gerarchica permette mappature flessibili: un settore può essere mappato a livello generico (sezione) e il sistema troverà tutti i codici specifici
- L'ottimizzazione tramite il campo `section` permette di risalire velocemente alla macro-categoria senza query ricorsive
