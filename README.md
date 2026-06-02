# Sip Street 7 🥤

**The Treat Starts Here.** — marketing website for Sip Street 7, an Australian fresh juice &
treats shop. Cold-pressed juices, green juice, Dutch mini pancakes, banana popsicles,
smoothies & fresh fruit plates. *Made fresh, served cold.*

## ✨ Features
- Fully animated, single-page site with smooth scrolling
- Responsive (desktop → mobile) with a mobile drawer menu
- 3-way order flow (online / call / order-at-window) in an accessible modal
- SEO + Open Graph/Twitter cards + `FoodEstablishment` structured data
- PWA-ready (web manifest + branded app icons)
- Privacy & Terms pages
- Accessibility: skip link, keyboard focus styles, modal focus trap, reduced-motion support

## 🛠️ Tech
Plain **HTML + CSS + JavaScript** — no build step. Smooth scroll via [Lenis](https://github.com/darkroomengineering/lenis),
scroll animation via [GSAP/ScrollTrigger](https://gsap.com/), reveals via `IntersectionObserver`.
All progressively enhanced — the site works even if the CDN libraries fail to load.
Fonts: Fredoka (display), Caveat (script accent), DM Sans (body).

## 🎨 Brand palette
| Colour | Hex |
| --- | --- |
| Forest Green | `#1a7a3e` |
| Deep / Dark Green | `#0d5226` / `#103a23` |
| Strawberry Red | `#e23b2e` |
| Lemon Yellow | `#f5bf1f` |
| Warm Cream | `#fbf4e6` |

## 🚀 Run locally
Just open `index.html` in a browser, or serve the folder:
```bash
npx serve .
```

## 📝 Going live — replace placeholders
- Real business details (address, phone, hours, socials) in `index.html` + footer + `script.js`
- Online-ordering link, phone & display number in the `SS7` config at the top of `script.js`
- The "Banana Popsicle" card photo (`assets/img/popsicle.jpg`) — currently a berry-popsicle stock shot
- Have `privacy.html` / `terms.html` reviewed before launch

---
© 2026 Sip Street 7. Made with 🍓 in Australia.
