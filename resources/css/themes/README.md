# Temi della Piattaforma

Questa directory contiene la documentazione dei temi disponibili per la piattaforma LMS.

## Temi Disponibili

- **sodexo** - Tema principale per Sodexo (default)
- **example** - Tema di esempio per nuovi clienti

## Come Aggiungere un Nuovo Tema

### 1. Creare la Documentazione

Crea un nuovo file `nome-tema.md` in questa directory con la palette colori del cliente:

```markdown
# Tema Nome Cliente

## Palette Colori
- **Primary:** `#XXXXXX` - Descrizione
- **Secondary:** `#XXXXXX` - Descrizione
...
```

### 2. Aggiungere il Tema al CSS

Modifica `resources/css/app.css` e aggiungi un nuovo blocco `@plugin "daisyui/theme"`:

```css
@plugin "daisyui/theme" {
  name: "nome-tema";
  default: false;
  prefersdark: false;
  color-scheme: "light";
  --color-base-100: #FFFFFF;
  --color-base-200: #F5F5F5;
  --color-base-300: #E5E5E5;
  --color-base-content: #1A1A1A;
  --color-primary: #YOUR_PRIMARY_COLOR;
  --color-primary-content: #FFFFFF;
  --color-secondary: #YOUR_SECONDARY_COLOR;
  --color-secondary-content: #FFFFFF;
  --color-accent: #YOUR_ACCENT_COLOR;
  --color-accent-content: #FFFFFF;
  --color-neutral: #2A2A2A;
  --color-neutral-content: #FFFFFF;
  --color-info: oklch(88.263% 0.093 212.846);
  --color-info-content: oklch(17.652% 0.018 212.846);
  --color-success: oklch(87.099% 0.219 148.024);
  --color-success-content: oklch(17.419% 0.043 148.024);
  --color-warning: oklch(95.533% 0.134 112.757);
  --color-warning-content: oklch(19.106% 0.026 112.757);
  --color-error: oklch(68.22% 0.206 24.43);
  --color-error-content: oklch(13.644% 0.041 24.43);
  --radius-selector: 1rem;
  --radius-field: 0.5rem;
  --radius-box: 1rem;
  --size-selector: 0.3125rem;
  --size-field: 0.3125rem;
  --border: 1.5px;
  --depth: 1;
  --noise: 0;
}
```

### 3. Testare il Tema

Nel file `.env` locale, imposta:

```env
APP_THEME=nome-tema
```

Poi rebuilda il CSS:

```bash
npm run build
```

### 4. Configurare il Deploy

Per ogni deploy del cliente, imposta la variabile nel `.env` di produzione:

```env
APP_NAME="Nome Cliente LMS"
APP_THEME=nome-tema
APP_URL=https://lms.nomecliente.com
```

## Tool Utili

### Convertire Colori in OKLCH

DaisyUI usa il formato OKLCH per i colori di stato (info, success, warning, error).

Puoi usare [oklch.com](https://oklch.com/) per convertire i colori hex in OKLCH.

### Validare Contrasto

Assicurati che i colori abbiano un contrasto adeguato usando:
- [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)
- Target: almeno 4.5:1 per testo normale, 3:1 per testo grande

## Note Importanti

- Tutti i temi sono compilati insieme nel CSS finale
- Ogni tema aggiunge circa 1.5-2 KB al bundle CSS totale
- Fino a 25-30 temi sono gestibili senza problemi di performance
- Il tema viene switchato solo cambiando la variabile d'ambiente, non serve rebuild
