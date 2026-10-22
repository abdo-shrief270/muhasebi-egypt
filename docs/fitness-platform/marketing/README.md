# Qowa marketing brochures

Six A4 brochures, one per audience and language, for early-access marketing:

| Audience | English | Arabic |
|---|---|---|
| Gyms & fitness centres | `Qowa-Gyms-EN.pdf` | `Qowa-Gyms-AR.pdf` |
| Coaches & personal trainers | `Qowa-Coaches-EN.pdf` | `Qowa-Coaches-AR.pdf` |
| Trainees & athletes | `Qowa-Trainees-EN.pdf` | `Qowa-Trainees-AR.pdf` |

Each has six pages: cover, problems → solutions, features (two pages with app mock-ups), getting started + payments + trust + FAQ, plans and call to action.

The content follows `../PRODUCT_BUSINESS_BLUEPRINT.md`: features without a tag are in the edition being sold (coach P1, gym P2), **Coming soon / قريبًا** = the next phase, **On the roadmap / لاحقًا** = P3 or later. Prices are §7.1 (EGP, VAT included); the founders offers, trials and data rules are §6.10–6.11, §7–8 and §9.2. The app screens are illustrations; names in them are fictional.

Brand **Qowa / قوّة**, site `qowa.app`, phone `+20 155 544 0882` and `support@qowa.app` are set in one place at the top of `source/content.mjs` (shown in each brochure's call to action and footer).

## Rebuild

```sh
cd source
npm install                      # puppeteer, lucide-static icons, IBM Plex Sans Arabic
cp pconf.example.json pconf.json # point executablePath at a Chromium / Chrome binary
npm run build                    # writes the PDFs next to this README
node build.mjs .. coaches ar     # one audience / language only
```

Copy lives in `source/content.mjs` (English and Arabic side by side); layout, mock-ups and styles in `source/build.mjs`. The English text uses the Inter / Inter Display system fonts. The build checks that nothing runs into the footer or the mock-ups and prints `LAYOUT …` if a change makes a page overflow.
