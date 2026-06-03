<?php

namespace Database\Seeders;

use App\Enums\InclusionType;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\NaceAteco;
use App\Models\Province;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use Illuminate\Database\Seeder;

class JobDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Job Categories
        $categories = [
            ['name' => 'Dirigente', 'description' => 'Personale dirigenziale'],
            ['name' => 'Quadro', 'description' => 'Quadri aziendali'],
            ['name' => 'Impiegato', 'description' => 'Personale impiegatizio'],
            ['name' => 'Operaio', 'description' => 'Personale operaio'],
        ];

        foreach ($categories as $category) {
            JobCategory::create($category);
        }

        // Job Levels
        $levels = [
            ['name' => '1° Livello', 'description' => 'Primo livello di inquadramento'],
            ['name' => '2° Livello', 'description' => 'Secondo livello di inquadramento'],
            ['name' => '3° Livello', 'description' => 'Terzo livello di inquadramento'],
            ['name' => '4° Livello', 'description' => 'Quarto livello di inquadramento'],
            ['name' => '5° Livello', 'description' => 'Quinto livello di inquadramento'],
            ['name' => 'B1', 'description' => 'Livello B1'],
            ['name' => 'B2', 'description' => 'Livello B2'],
        ];

        foreach ($levels as $level) {
            JobLevel::create($level);
        }

        // Job Roles
        $roles = [
            ['name' => 'Lavoratore', 'description' => 'Lavoratore dipendente'],
            ['name' => 'Preposto', 'description' => 'Preposto alla sicurezza'],
            ['name' => 'Dirigente', 'description' => 'Dirigente aziendale'],
            ['name' => 'Datore di Lavoro', 'description' => 'Datore di lavoro responsabile'],
            ['name' => 'RSPP', 'description' => 'Responsabile Servizio Prevenzione e Protezione'],
            ['name' => 'ASPP', 'description' => 'Addetto Servizio Prevenzione e Protezione'],
            ['name' => 'ASPP', 'description' => 'Addetto Servizio Prevenzione e Protezione'],
            ['name' => 'Altro', 'description' => 'Altro è da usare per esempio per docenti che non sono nell’organizzazione di Sodexo.'],
        ];

        foreach ($roles as $role) {
            JobRole::create($role);
        }

        // Job Tasks (Mansioni)
        // $tasks = [
        //     ['name' => 'Responsabile mensa / direttore di servizio / site manager', 'description' => 'Coordinamento del personale, gestione delle emergenze e organizzazione generale del servizio'],
        //     ['name' => 'Preposto di mensa / capo turno / capo area operativa', 'description' => 'Sorveglianza operativa della cucina e controllo del rispetto delle procedure di lavoro'],
        //     ['name' => 'Titolare / gestore asilo nido', 'description' => 'Responsabilità organizzativa generale, pianificazione gestionale e relazioni con famiglie, fornitori e personale'],

        // ];

        $tasks = [
            // --- MANAGEMENT & DIREZIONE ---
            ['code' => 'MNG_001', 'name' => 'Responsabile mensa / direttore di servizio / site manager', 'description' => 'Coordinamento del personale, gestione delle emergenze e organizzazione generale del servizio'],
            ['code' => 'MNG_002', 'name' => 'Preposto di mensa / capo turno / capo area operativa', 'description' => 'Sorveglianza operativa della cucina e controllo del rispetto delle procedure di lavoro'],
            ['code' => 'MNG_003', 'name' => 'Titolare / gestore asilo nido', 'description' => 'Responsabilità organizzativa generale, pianificazione gestionale e relazioni con famiglie, fornitori e personale'],
            ['code' => 'MNG_004', 'name' => 'Direttore / responsabile di struttura', 'description' => 'Coordinamento del personale interno, organizzazione delle attività educative e controllo dell\'applicazione delle procedure della struttura'],
            ['code' => 'MNG_005', 'name' => 'Preposto / referente di turno', 'description' => 'Controllo operativo continuativo del personale in struttura e verifica del rispetto delle procedure operative interne'],

            // --- AREA EDUCATIVA, PEDAGOGICA & SANITARIA ---
            ['code' => 'EDU_001', 'name' => 'Coordinatore pedagogico', 'description' => 'Attività tecnica di progettazione educativa, supervisione pedagogica del team e supporto alle sezioni'],
            ['code' => 'EDU_002', 'name' => 'Educatore / educatrice asilo nido', 'description' => 'Accudimento e conduzione delle attività didattiche, ludiche e relazionali con i bambini delle sezioni'],
            ['code' => 'EDU_003', 'name' => 'Educatore sezione lattanti', 'description' => 'Cura specifica dei neonati, gestione dei momenti del cambio, dell\'alimentazione neonatale e del riposo'],
            ['code' => 'EDU_004', 'name' => 'Educatore sezione divezzi / semidivezzi', 'description' => 'Assistenza continuativa, sorveglianza e guida dei bambini più grandi nelle attività di autonomia e socializzazione'],
            ['code' => 'EDU_005', 'name' => 'Educatore di sostegno / assistenza a minori con disabilità', 'description' => 'Assistenza ravvicinata personalizzata e supporto educativo-relazionale mirato a minori con esigenze complesse o disabilità'],
            ['code' => 'EDU_006', 'name' => 'Assistente all’infanzia / operatore socio-educativo', 'description' => 'Supporto diretto ai bambini nelle attività quotidiane, nella cura dell\'igiene personale e nei pasti'],
            ['code' => 'EDU_007', 'name' => 'Ausiliario di sezione', 'description' => 'Attività di supporto alla sezione per il riordino dei materiali didattici, pulizia leggera e assistenza ai bambini'],
            ['code' => 'EDU_008', 'name' => 'Addetto accoglienza bambini / uscita', 'description' => 'Accoglienza dei bambini al mattino, gestione dei flussi di ingresso/uscita e interfaccia iniziale con le famiglie'],
            ['code' => 'EDU_009', 'name' => 'Addetto cambio pannolini / igiene bambini', 'description' => 'Cura dell\'igiene intima dei bambini e applicazione rigorosa delle procedure di igienizzazione dei fasciatoi'],
            ['code' => 'EDU_010', 'name' => 'Addetto nanna / riposo bambini', 'description' => 'Allestimento della stanza del riposo, sorveglianza e monitoraggio dei bambini durante il sonno'],
            ['code' => 'EDU_011', 'name' => 'Addetto attività ludico-motorie', 'description' => 'Pianificazione e conduzione di giochi dinamici e percorsi motori negli spazi interni ed esterni della struttura'],
            ['code' => 'EDU_012', 'name' => 'Addetto spazio esterno / giardino', 'description' => 'Vigilanza e sorveglianza attiva dei bambini durante i giochi all\'aperto e controllo visivo delle attrezzature esterne'],
            ['code' => 'EDU_013', 'name' => 'Psicologo / consulente pedagogico esterno', 'description' => 'Attività di consulenza, osservazione specialistica dei bambini in sezione e supporto formativo al personale'],
            ['code' => 'MED_003', 'name' => 'Infermiere / personale sanitario presente in struttura', 'description' => 'Gestione dei presidi sanitari della struttura, monitoraggio della salute dei bambini e interventi di primo soccorso specialistico'],
            ['code' => 'EDU_014', 'name' => 'Volontario / tirocinante / stagista', 'description' => 'Supporto affiancato alle attività educative e operative sotto la supervisione del personale tutor della struttura'],

            // --- CUCINA & RISTORAZIONE ---
            ['code' => 'KIT_001', 'name' => 'Capo cuoco', 'description' => 'Coordinamento della squadra di cucina, gestione dei ritmi di lavoro e preparazione delle pietanze'],
            ['code' => 'KIT_002', 'name' => 'Cuoco / cuoca interno/a', 'description' => 'Preparazione, cottura dei pasti e gestione della linea tramite l\'utilizzo di attrezzature professionali da cucina'],
            ['code' => 'KIT_003', 'name' => 'Aiuto cuoco', 'description' => 'Supporto nella preparazione degli alimenti, taglio e utilizzo di piccoli macchinari da cucina'],
            ['code' => 'KIT_004', 'name' => 'Addetto preparazione pasti', 'description' => 'Lavoro manuale di taglio, preparazione preliminare e manipolazione degli ingredienti'],
            ['code' => 'KIT_005', 'name' => 'Addetto diete speciali / allergeni', 'description' => 'Gestione della tracciabilità degli alimenti e preparazione di pasti specifici per intolleranze'],
            ['code' => 'KIT_006', 'name' => 'Addetto preparazione biberon / pappe', 'description' => 'Utilizzo dello scaldabiberon e preparazione di alimenti liquidi o semiliquidi specifici per i lattanti'],

            // --- SERVIZIO MENSA & DISTRIBUZIONE ---
            ['code' => 'SRV_001', 'name' => 'Addetto sporzionamento / distribuzione pasti', 'description' => 'Distribuzione delle porzioni, gestione dei carrelli termici e relazione diretta con l\'utenza'],
            ['code' => 'SRV_002', 'name' => 'Addetto mensa / self-service / refettorio', 'description' => 'Attività di riordino della sala, movimentazione leggera e assistenza ai clienti del self-service'],
            ['code' => 'SRV_003', 'name' => 'Addetto sala mensa / riordino refettorio', 'description' => 'Pulizia delle superfici e allestimento della sala con la movimentazione di tavoli, sedie e vassoi'],
            ['code' => 'SRV_004', 'name' => 'Addetto mensa / sporzionamento pasti (asilo)', 'description' => 'Porzionatura dei pasti caldi, movimentazione dei vassoi e supporto ai bambini per l\'autonomia durante il pranzo'],

            // --- PULIZIE & LAVANDERIA ---
            ['code' => 'CLN_001', 'name' => 'Addetto lavaggio stoviglie', 'description' => 'Utilizzo delle macchine lavastoviglie industriali e lavaggio manuale di piatti, bicchieri e posate'],
            ['code' => 'CLN_002', 'name' => 'Addetto lavaggio attrezzature cucina', 'description' => 'Pulizia e disinfestazione delle pentole e delle componenti smontabili dei macchinari della cucina'],
            ['code' => 'CLN_003', 'name' => 'Addetto pulizie cucina', 'description' => 'Pulizia approfondita e igienizzazione dei piani di lavoro e delle superfici della cucina'],
            ['code' => 'CLN_004', 'name' => 'Addetto sanificazione locali', 'description' => 'Applicazione dei protocolli di disinfezione dei locali, generalmente in orario di chiusura al pubblico'],
            ['code' => 'CLN_005', 'name' => 'Addetto pulizie ordinarie', 'description' => 'Lavaggio dei pavimenti, pulizia delle superfici e spolvero degli ambienti comuni e delle sezioni'],
            ['code' => 'CLN_006', 'name' => 'Addetto sanificazione ambienti e giochi', 'description' => 'Applicazione di procedure igieniche specifiche per la disinfezione profonda dei giocattoli e degli arredi usati dai bambini'],
            ['code' => 'CLN_007', 'name' => 'Addetto lavanderia interna', 'description' => 'Utilizzo di lavatrici e asciugatrici per il lavaggio, la disinfezione e la piegatura della biancheria interna'],
            ['code' => 'CLN_008', 'name' => 'Guardarobiere / addetto biancheria', 'description' => 'Gestione dello stoccaggio, riordino e distribuzione dei teli, bavaglini e lenzuolini puliti della struttura'],

            // --- LOGISTICA & LOGISTICA ALIMENTARE ---
            ['code' => 'LOG_001', 'name' => 'Magazziniere alimentare', 'description' => 'Ricevimento, stoccaggio sulle scaffalature e movimentazione delle merci deperibili e non'],
            ['code' => 'LOG_002', 'name' => 'Addetto ricevimento merci', 'description' => 'Operazioni di scarico e controllo quantitativo e qualitativo dei colli in arrivo dai fornitori'],
            ['code' => 'LOG_003', 'name' => 'Addetto controllo scadenze / inventario derrate', 'description' => 'Verifica periodica dei livelli di magazzino, controllo delle date di scadenza e conteggio scorte'],
            ['code' => 'LOG_004', 'name' => 'Addetto celle frigorifere', 'description' => 'Stoccaggio, prelievo e gestione della rotazione dei prodotti alimentari all\'interno delle celle frigo'],
            ['code' => 'LOG_005', 'name' => 'Addetto confezionamento pasti', 'description' => 'Utilizzo di macchine termosaldatrici e confezionatrici per sigillare i pasti pronti'],
            ['code' => 'LOG_006', 'name' => 'Addetto veicolazione pasti', 'description' => 'Movimentazione e carico dei contenitori termici e dei carrelli destinati al trasporto esterno'],
            ['code' => 'LOG_007', 'name' => 'Autista consegna pasti', 'description' => 'Consegna dei pasti tramite automezzi aziendali e scarico della merce presso i punti di consegna'],
            ['code' => 'LOG_008', 'name' => 'Magazziniere / addetto approvvigionamenti', 'description' => 'Stoccaggio, controllo delle scorte e distribuzione dei prodotti per l\'igiene e dei materiali didattici'],

            // --- TRASPORTO ---
            ['code' => 'TRN_001', 'name' => 'Autista scuolabus / pulmino nido', 'description' => 'Conduzione del mezzo per il trasporto degli alunni, controllo dei sistemi di ritenuta e gestione del tragitto casa-scuola'],
            ['code' => 'TRN_002', 'name' => 'Accompagnatore scuolabus / pulmino', 'description' => 'Assistenza e vigilanza dei bambini durante le operazioni di salita/discesa e per l\'intera durata del viaggio'],

            // --- AMMINISTRAZIONE & SERVIZI GENERALI ---
            ['code' => 'ADM_001', 'name' => 'Cassiere mensa / addetto ticket/badge', 'description' => 'Gestione della cassa, della transazioni di pagamento e controllo dei badge degli utenti'],
            ['code' => 'ADM_002', 'name' => 'Addetto prenotazione pasti / accoglienza utenti', 'description' => 'Attività amministrativa di sportello per la registrazione delle prenotazioni e accoglienza clienti'],
            ['code' => 'ADM_003', 'name' => 'Impiegato amministrativo / segreteria', 'description' => 'Gestione della corrispondenza, fatturazione, pratiche di iscrizione, archiviazione dati e mansioni generali d\'ufficio'],
            ['code' => 'ADM_004', 'name' => 'Reception / front office famiglie', 'description' => 'Accoglienza dei genitori alla reception, gestione del centralino telefonico e controllo degli accessi pedonali'],
            ['code' => 'ADM_005', 'name' => 'Custode / addetto apertura e chiusura struttura', 'description' => 'Sorveglianza della struttura, ispezione finale dei locali e gestione dei sistemi di allarme in apertura e chiusura'],

            // --- QUALITÀ & SICUREZZA AZIENDALE ---
            ['code' => 'QA_001',  'name' => 'Responsabile HACCP / qualità', 'description' => 'Controllo della documentazione igienico-sanitaria ed esecuzione di audit periodici nelle aree operative'],
            ['code' => 'QA_002',  'name' => 'Addetto controllo temperature / tracciabilità / non conformità', 'description' => 'Monitoraggio costante dei termometri di celle e cucine e registrazione dei dati di tracciabilità'],
            ['code' => 'MED_001', 'name' => 'Dietista / nutrizionista', 'description' => 'Elaborazione dei piani alimentari, sviluppo dei menu e supervisione tecnica dei reparti di distribuzione'],
            ['code' => 'MED_002', 'name' => 'Tecnico menu / referente diete', 'description' => 'Sviluppo dei menu approvati ed esecuzione di sopralluoghi tecnici nelle aree di ristorazione'],

            // --- MANUTENZIONE ---
            ['code' => 'MNT_001', 'name' => 'Manutentore attrezzature cucina', 'description' => 'Interventi tecnici di riparazione e manutenzione sulle macchine e attrezzature della cucina'],
            ['code' => 'MNT_002', 'name' => 'Addetto piccole manutenzioni', 'description' => 'Esecuzione di piccole riparazioni generiche e interventi di ripristino all\'interno della struttura'],
            ['code' => 'MNT_003', 'name' => 'Tecnico impianti cucina', 'description' => 'Manutenzione specializzata degli impianti elettrici, idraulici, del gas e dei sistemi di aspirazione'],
            ['code' => 'MNT_004', 'name' => 'Referente fornitori tecnici', 'description' => 'Gestione dei rapporti con le ditte esterne e supervisione dei sopralluoghi tecnici nel sito'],
            ['code' => 'MNT_005', 'name' => 'Manutentore interno', 'description' => 'Interventi rapidi di riparazione edile o impiantistica leggera sulle strutture e negli spazi d\'infanzia'],
            ['code' => 'MNT_006', 'name' => 'Tecnico manutenzione esterno', 'description' => 'Esecuzione di interventi specialistici programmati su impianti complessi, infissi o grandi strutture gioco esterni'],
            ['code' => 'MNT_007', 'name' => 'Addetto controllo giochi / arredi / spazi', 'description' => 'Ispezione visiva periodica della stabilità dei mobili e dell\'integrità dei giochi per garantire la massima sicurezza strutturale'],

            // --- INCARICHI SPECIALI DI EMERGENZA (TRASVERSALI) ---
            ['code' => 'SAF_001', 'name' => 'Addetto antincendio', 'description' => 'Gestione delle procedure di evacuazione e intervento tempestivo in caso di emergency incendio'],
            ['code' => 'SAF_002', 'name' => 'Addetto primo soccorso', 'description' => 'Incarico di primo intervento di assistenza sanitaria in caso di infortunio o malore nel sito'],
            ['code' => 'SAF_003', 'name' => 'RLS', 'description' => 'Rappresentanza dei lavoratori per gli aspetti di consultazione sulla prevenzione aziendale'],
            ['code' => 'SAF_004', 'name' => 'Addetto antincendio / emergenze (asilo)', 'description' => 'Coordinamento delle prove di evacuazione dei bambini, controllo periodico dei presidi antincendio della struttura scolastica'],
        ];

        foreach ($tasks as $task) {
            JobTask::create($task);
        }

        // Job Sectors
        // Crea un settore per ogni sezione NACE/ATECO (lettere A-U)
        $sections = NaceAteco::where('hierarchy', 1)->orderBy('code')->get();

        // Settori sodexò
        $added_sections = [
            ['title_it' => "SCUOLE"],
            ['title_it' => "AZIENDE"],
            ['title_it' => "SANITÀ"],
            ['title_it' => "SEDE E FILIALI"],
        ];

        foreach ($added_sections as $section) {
            $sections->push((object) $section);
        }

        if ($sections->isEmpty()) {
            $this->command->warn('Nessuna sezione NACE/ATECO trovata. I settori non saranno creati.');
        } else {
            foreach ($sections as $section) {
                // Crea il settore usando il titolo italiano della sezione
                $sector = JobSector::create([
                    'name' => $section->title_it,
                    'description' => $section->description ?? "",
                ]);

                if(isset($section->code)) {
                    // Associa il settore alla sezione ATECO con tipo inclusione SECTION
                    $sector->naceAtecoCodes()->attach($section->code, [
                        'inclusion_type' => InclusionType::SECTION->value,
                    ]);
                }

                $this->command->info("✓ Creato settore '{$sector->name}' collegato alla sezione " . ($section->code ?? 'N/A'));
            }
        }

        // Job Units (Unità Lavorative)
        // Get Italy country ID
        $italy = WorldCountry::where('code', 'it')->first();

        if (! $italy) {
            $this->command->warn('Italia non trovata. Saltata creazione unità lavorative.');

            return;
        }

        $units = [
            [
                'name' => 'Sede Milano Centro',
                'province_code' => 'MI',
                'city_name' => 'Milano',
                'address' => 'Via Roma 123',
                'postal_code' => '20100',
            ],
            [
                'name' => 'Sede Roma EUR',
                'province_code' => 'RM',
                'city_name' => 'Roma',
                'address' => 'Via Cristoforo Colombo 456',
                'postal_code' => '00144',
            ],
            [
                'name' => 'Stabilimento Torino',
                'province_code' => 'TO',
                'city_name' => 'Torino',
                'address' => 'Corso Francia 789',
                'postal_code' => '10141',
            ],
        ];

        foreach ($units as $unitData) {
            // Find province
            $province = Province::where('code', $unitData['province_code'])
                ->where('country_id', $italy->id)
                ->first();

            if (! $province) {
                $this->command->warn("Provincia {$unitData['province_code']} non trovata per {$unitData['name']}");

                continue;
            }

            // Find city
            $city = WorldCity::where('name', $unitData['city_name'])
                ->where('country_id', $italy->id)
                ->where('province_id', $province->id)
                ->first();

            if (! $city) {
                $this->command->warn("Città {$unitData['city_name']} non trovata per {$unitData['name']}");

                continue;
            }

            JobUnit::create([
                'name' => $unitData['name'],
                'country_id' => $italy->id,
                'region_id' => $province->region_id,
                'province_id' => $province->id,
                'city_id' => $city->id,
                'address' => $unitData['address'],
                'postal_code' => $unitData['postal_code'],
            ]);
        }

        $this->command->info('✓ Unità lavorative create correttamente');
    }
}
