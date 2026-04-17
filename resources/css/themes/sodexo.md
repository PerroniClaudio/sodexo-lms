# Tema Sodexo

## Palette Colori

### Colori Principali
- **Primary:** `#2E348C` - Blu navy istituzionale
- **Secondary:** `#5D6BC0` - Blu medio per elementi secondari
- **Accent:** `#DA2020` - Rosso per call-to-action e alert

### Colori Base
- **Base 100:** `#F5F7FB` - Sfondo principale (bianco sporco)
- **Base 200:** `#E8EEF6` - Sfondo secondario
- **Base 300:** `#D6DEEB` - Sfondo terziario
- **Base Content:** `#27306F` - Testo principale

### Colori di Stato
- **Info:** `oklch(88.263% 0.093 212.846)` - Informazioni
- **Success:** `oklch(87.099% 0.219 148.024)` - Successo
- **Warning:** `oklch(95.533% 0.134 112.757)` - Avvisi
- **Error:** `oklch(68.22% 0.206 24.43)` - Errori

### Stile
- **Border Radius:** 1rem (box), 0.5rem (field)
- **Border Width:** 1.5px
- **Color Scheme:** Light

## Implementazione

Questo tema è definito in `resources/css/app.css` con il plugin DaisyUI.

```css
@plugin "daisyui/theme" {
  name: "sodexo";
  default: true;
  prefersdark: false;
  color-scheme: "light";
  --color-base-100: #F5F7FB;
  --color-primary: #2E348C;
  --color-accent: #DA2020;
  /* ... altri colori ... */
}
```

## Note
- Tema default della piattaforma
- Utilizzato quando `APP_THEME=sodexo` nel file `.env`
