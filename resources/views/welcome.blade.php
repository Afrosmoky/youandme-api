<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ja i Ty — aplikacja dla dwojga. Zostańcie testerami</title>
    <meta name="description" content="Codzienne pytanie, rytuał tygodnia i wspólne wspomnienia. Ja i Ty — aplikacja mobilna dla par. Zapisz się do testów: pierwsze 200 par otrzyma konto Premium na zawsze.">
    <meta property="og:title" content="Ja i Ty — są pytania, których sobie nie zadajecie. Jeszcze.">
    <meta property="og:description" content="Aplikacja mobilna dla par: codzienne pytanie, rytuał tygodnia, wspólne wspomnienia. Pierwsze 200 par testerów otrzyma Premium na zawsze.">
    <meta property="og:url" content="https://jaity.app/">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pl_PL">
    <meta name="theme-color" content="#0E0E0E">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='0.9em' font-size='90' fill='%23D4AF37'%3E%26hearts%3B%3C/text%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Belleza&family=Alegreya:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#0E0E0E',
                        inkSoft: '#161313',
                        wineDeep: '#1A1212',
                        wine: '#6B0F1A',
                        gold: '#D4AF37',
                        goldLight: '#E8C56C',
                        cream: '#F5EDE0',
                        sand: '#A89882'
                    },
                    fontFamily: {
                        headline: ['Belleza', 'sans-serif'],
                        body: ['Alegreya', 'serif']
                    }
                }
            }
        }
    </script>

    <style>
        html { scroll-behavior: smooth; }
        body { background-color: #0E0E0E; }
        ::selection { background: #D4AF37; color: #0E0E0E; }

        .gold-glow {
            text-shadow: 0 0 18px rgba(212, 175, 55, 0.45), 0 0 50px rgba(212, 175, 55, 0.18);
            animation: glowPulse 4s ease-in-out infinite alternate;
        }
        @keyframes glowPulse {
            from { text-shadow: 0 0 14px rgba(212,175,55,0.35), 0 0 40px rgba(212,175,55,0.12); }
            to   { text-shadow: 0 0 22px rgba(212,175,55,0.55), 0 0 60px rgba(212,175,55,0.22); }
        }

        .reveal {
            opacity: 0;
            transform: translateY(26px);
            transition: opacity 0.9s ease, transform 0.9s ease;
        }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-d1 { transition-delay: 0.15s; }
        .reveal-d2 { transition-delay: 0.3s; }
        .reveal-d3 { transition-delay: 0.45s; }

        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none; }
            .gold-glow { animation: none; }
            html { scroll-behavior: auto; }
        }

        .corner { position: absolute; width: 56px; height: 56px; border-color: rgba(212,175,55,0.35); }
        .corner-tl { top: 1.5rem; left: 1.5rem; border-top: 1px solid; border-left: 1px solid; border-color: inherit; }
        .corner-tr { top: 1.5rem; right: 1.5rem; border-top: 1px solid; border-right: 1px solid; border-color: inherit; }
        .corner-bl { bottom: 1.5rem; left: 1.5rem; border-bottom: 1px solid; border-left: 1px solid; border-color: inherit; }
        .corner-br { bottom: 1.5rem; right: 1.5rem; border-bottom: 1px solid; border-right: 1px solid; border-color: inherit; }

        .hero-radial {
            background: radial-gradient(ellipse 70% 50% at 50% 42%, rgba(212,175,55,0.07), transparent 70%);
        }

        input, select { color-scheme: dark; }
    </style>
</head>
<body class="font-body text-cream antialiased overflow-x-hidden">

<!-- ===== HERO ===== -->
<header class="relative min-h-screen flex flex-col hero-radial">
    <span class="corner corner-tl" aria-hidden="true"></span>
    <span class="corner corner-tr" aria-hidden="true"></span>
    <span class="corner corner-bl" aria-hidden="true"></span>
    <span class="corner corner-br" aria-hidden="true"></span>

    <nav class="flex items-center justify-between px-8 sm:px-14 pt-10">
        <span class="font-headline text-2xl tracking-wide text-gold">Ja&nbsp;i&nbsp;Ty</span>
        <a href="#zapisy" class="hidden sm:inline-block text-sm text-sand hover:text-goldLight transition-colors tracking-wider uppercase">Zostańcie testerami</a>
    </nav>

    <div class="flex-1 flex flex-col items-center justify-center text-center px-6 pb-16">
        <p class="reveal text-sand tracking-[0.3em] uppercase text-xs sm:text-sm mb-7">Aplikacja dla dwojga</p>

        <h1 class="reveal reveal-d1 font-headline gold-glow text-gold leading-tight text-4xl sm:text-6xl lg:text-7xl max-w-4xl">
            Są pytania, których sobie nie&nbsp;zadajecie.<br>
            <span class="italic font-body text-cream text-3xl sm:text-5xl lg:text-6xl">Jeszcze.</span>
        </h1>

        <p class="reveal reveal-d2 mt-8 max-w-2xl text-lg sm:text-[22px] leading-relaxed text-cream/85">
            Ja i Ty podsuwa wam codziennie jedno pytanie i&nbsp;jeden pretekst do prawdziwej rozmowy —
            zanim wieczór zamieni się w&nbsp;scrollowanie osobno.
        </p>

        <div class="reveal reveal-d3 mt-12 flex flex-col items-center gap-4">
            <a href="#zapisy"
               class="inline-block bg-gold text-ink font-body font-bold text-lg px-10 py-4 rounded-sm tracking-wide
                  transition-all duration-300 hover:bg-goldLight hover:shadow-[0_0_30px_rgba(212,175,55,0.35)]
                  active:bg-wine active:text-cream">
                Zostańcie testerami
            </a>
            <p class="text-sm text-sand">Pierwsze 200 par otrzyma konto Premium na&nbsp;zawsze.</p>
        </div>
    </div>

    <a href="#czym-jest" aria-label="Przewiń niżej" class="absolute bottom-8 left-1/2 -translate-x-1/2 text-gold/60 hover:text-gold transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </a>
</header>

<!-- ===== CZYM JEST ===== -->
<section id="czym-jest" class="py-12 sm:py-16 px-6">
    <div class="max-w-5xl mx-auto">
        <h2 class="reveal font-headline text-gold text-3xl sm:text-[40px] text-center mb-4">Mały rytuał. Codziennie.</h2>
        <p class="reveal reveal-d1 text-center text-sand max-w-2xl mx-auto mb-16 text-lg">
            Trzy proste rzeczy, które robicie razem — w aplikacji zaprojektowanej tylko dla was dwojga.
        </p>

        <div class="grid sm:grid-cols-3 gap-6">
            <div class="reveal bg-inkSoft border border-gold/15 rounded-sm p-8 text-center transition-all duration-300 hover:border-gold/40 hover:-translate-y-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto text-gold mb-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                </svg>
                <h3 class="font-headline text-cream text-xl mb-3">Codzienna karta dnia</h3>
                <p class="text-sand text-[17px] leading-relaxed">Jedno pytanie dziennie, które otwiera rozmowy, na które zwykle „nie ma czasu".</p>
            </div>

            <div class="reveal reveal-d1 bg-inkSoft border border-gold/15 rounded-sm p-8 text-center transition-all duration-300 hover:border-gold/40 hover:-translate-y-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto text-gold mb-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                </svg>
                <h3 class="font-headline text-cream text-xl mb-3">Rytuał tygodnia</h3>
                <p class="text-sand text-[17px] leading-relaxed">Co tydzień jedno wspólne wyzwanie — mały rytuał, który zostaje z wami na dłużej.</p>
            </div>

            <div class="reveal reveal-d2 bg-inkSoft border border-gold/15 rounded-sm p-8 text-center transition-all duration-300 hover:border-gold/40 hover:-translate-y-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto text-gold mb-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                </svg>
                <h3 class="font-headline text-cream text-xl mb-3">Wasze wspomnienia</h3>
                <p class="text-sand text-[17px] leading-relaxed">Odpowiedzi i chwile zapisują się na waszej wspólnej osi — prywatnej kronice was dwojga.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== CO DOSTAJĄ TESTERZY ===== -->
<section class="py-12 sm:py-16 px-6 bg-gradient-to-b from-ink via-wineDeep to-ink">
    <div class="max-w-4xl mx-auto">
        <div class="reveal flex items-center justify-center gap-4 mb-4">
            <span class="h-px w-12 bg-wine"></span>
            <h2 class="font-headline text-gold text-3xl sm:text-[40px] text-center">Dla pierwszych par</h2>
            <span class="h-px w-12 bg-wine"></span>
        </div>
        <p class="reveal reveal-d1 text-center text-sand max-w-2xl mx-auto mb-16 text-lg">
            Budujemy Ja i Ty razem z parami, które wejdą do gry przed wszystkimi innymi.
        </p>

        <div class="space-y-10">
            <div class="reveal flex gap-6 items-start">
                <span class="font-headline text-gold/50 text-4xl leading-none select-none">01</span>
                <div>
                    <h3 class="font-headline text-cream text-xl mb-2">Dostęp przedpremierowy</h3>
                    <p class="text-sand text-[17px] leading-relaxed max-w-xl">Zagracie w Ja i Ty zanim aplikacja trafi do App Store i Google Play.</p>
                </div>
            </div>

            <div class="reveal reveal-d1 flex gap-6 items-start">
                <span class="font-headline text-gold/50 text-4xl leading-none select-none">02</span>
                <div>
                    <h3 class="font-headline text-cream text-xl mb-2">Premium na zawsze</h3>
                    <p class="text-sand text-[17px] leading-relaxed max-w-xl">Pierwsze 200 par testerów otrzyma dożywotnie konto Premium — wszystkie talie i funkcje, bez opłat, na zawsze.</p>
                </div>
            </div>

            <div class="reveal reveal-d2 flex gap-6 items-start">
                <span class="font-headline text-gold/50 text-4xl leading-none select-none">03</span>
                <div>
                    <h3 class="font-headline text-cream text-xl mb-2">Realny wpływ na produkt</h3>
                    <p class="text-sand text-[17px] leading-relaxed max-w-xl">Wasz feedback zdecyduje, jak będzie wyglądać finalna wersja — od pytań po funkcje.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== GRUPA IG ===== -->
<section class="py-12 sm:py-16 px-6 border-t border-gold/10">
    <div class="max-w-4xl mx-auto grid sm:grid-cols-2 gap-12 items-center">
        <div class="reveal text-center sm:text-left order-2 sm:order-1">
            <h2 class="font-headline text-gold text-3xl sm:text-[40px] mb-5">Dołączcie do grupy testerów</h2>
            <p class="text-sand text-lg leading-relaxed mb-4">
                Prowadzimy grupę testerów na Instagramie — wrzucamy tam nowości, pomagamy przy instalacji
                i pokazujemy kulisy bety. Zeskanujcie kod telefonem, żeby dołączyć do czatu.
            </p>
            <p class="text-sand/70 text-sm">Zeskanuj aparatem albo w aplikacji Instagram.</p>
        </div>
        <div class="reveal reveal-d1 flex justify-center order-1 sm:order-2">
            <img src="/storage/qr-testerzy.png"
                 alt="Kod QR — grupa testerów Ja i Ty na Instagramie"
                 width="387" height="621" loading="lazy"
                 class="w-[240px] sm:w-[260px] max-w-full rounded-xl border border-gold/20 shadow-[0_0_40px_rgba(212,175,55,0.15)]">
        </div>
    </div>
</section>

<!-- ===== FORMULARZ ===== -->
<section id="zapisy" class="py-12 sm:py-16 px-6">
    <div class="max-w-xl mx-auto">
        <h2 class="reveal font-headline text-gold text-3xl sm:text-[40px] text-center mb-4">Zostańcie testerami</h2>
        <p class="reveal reveal-d1 text-center text-sand mb-12 text-lg">
            Zostawcie kontakt — odezwiemy się przed startem zamkniętej bety.
            Liczba miejsc z&nbsp;dożywotnim Premium jest ograniczona do&nbsp;200&nbsp;par.
        </p>

        <form id="tester-form" class="reveal reveal-d2 space-y-6" novalidate>
            <div>
                <label for="f-name" class="block text-sm tracking-wider uppercase text-sand mb-2">Imię</label>
                <input type="text" id="f-name" name="name" autocomplete="given-name"
                       class="w-full bg-inkSoft border border-gold/25 rounded-sm px-4 py-3 text-cream placeholder-sand/50
                        focus:outline-none focus:border-gold transition-colors"
                       placeholder="Twoje imię">
            </div>

            <div>
                <label for="f-email" class="block text-sm tracking-wider uppercase text-sand mb-2">E-mail <span class="text-gold">*</span></label>
                <input type="email" id="f-email" name="email" required autocomplete="email"
                       class="w-full bg-inkSoft border border-gold/25 rounded-sm px-4 py-3 text-cream placeholder-sand/50
                        focus:outline-none focus:border-gold transition-colors"
                       placeholder="adres@email.pl">
                <p id="email-error" class="hidden text-wine text-sm mt-2 brightness-[2.2]">Podaj poprawny adres e-mail.</p>
            </div>

            <div>
                <label for="f-device" class="block text-sm tracking-wider uppercase text-sand mb-2">Jakiego telefonu używacie? <span class="text-gold">*</span></label>
                <select id="f-device" name="device" required
                        class="w-full bg-inkSoft border border-gold/25 rounded-sm px-4 py-3 text-cream
                         focus:outline-none focus:border-gold transition-colors">
                    <option value="">— wybierz —</option>
                    <option>Android</option>
                    <option>iPhone (iOS)</option>
                </select>
                <p id="device-error" class="hidden text-wine text-sm mt-2 brightness-[2.2]">Wybierz system telefonu.</p>
            </div>

            <div>
                <label for="f-duration" class="block text-sm tracking-wider uppercase text-sand mb-2">Od jak dawna jesteście razem? <span class="normal-case text-sand/60">(opcjonalnie)</span></label>
                <select id="f-duration" name="duration"
                        class="w-full bg-inkSoft border border-gold/25 rounded-sm px-4 py-3 text-cream
                         focus:outline-none focus:border-gold transition-colors">
                    <option value="">— wybierz —</option>
                    <option>Mniej niż rok</option>
                    <option>1–3 lata</option>
                    <option>3–7 lat</option>
                    <option>Ponad 7 lat</option>
                </select>
            </div>

            <!-- honeypot -->
            <input type="text" name="website" id="f-website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

            <button type="submit" id="f-submit"
                    class="w-full bg-gold text-ink font-bold text-lg py-4 rounded-sm tracking-wide
                       transition-all duration-300 hover:bg-goldLight hover:shadow-[0_0_30px_rgba(212,175,55,0.35)]
                       disabled:opacity-60 disabled:cursor-wait">
                Zapisz nas na listę
            </button>

            <p class="text-xs text-sand/70 text-center leading-relaxed">
                Zapisując się, zgadzasz się na kontakt mailowy w sprawie testów aplikacji Ja i Ty.
                Żadnego spamu — tylko informacje o starcie bety.
            </p>
        </form>

        <div id="form-success" class="hidden text-center py-10">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-14 h-14 mx-auto text-gold mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="font-headline text-gold text-3xl mb-4">Jesteście na liście.</h3>
            <!-- domyślny / Android: mail w ciągu 24h -->
            <div id="success-default">
                <p class="text-cream/85 text-lg leading-relaxed max-w-md mx-auto">
                    Dziękujemy! W&nbsp;ciągu 24&nbsp;godzin wyślemy na Wasz adres e-mail
                    zaproszenie do&nbsp;testów wraz z&nbsp;instrukcją krok po&nbsp;kroku —
                    jak pobrać i&nbsp;uruchomić aplikację.
                </p>
                <p class="text-sand text-sm mt-5 max-w-md mx-auto">
                    Nie widzicie wiadomości? Zajrzyjcie do&nbsp;folderu spam i&nbsp;przeciągnijcie ją
                    do&nbsp;odebranych — dzięki temu kolejne na&nbsp;pewno do&nbsp;Was trafią.
                </p>
            </div>

            <!-- iPhone: od razu instalacja przez TestFlight -->
            <div id="success-ios" class="hidden max-w-md mx-auto text-left">
                <p class="text-cream/85 text-lg leading-relaxed text-center mb-6">
                    Macie iPhone? Nie musicie czekać na maila — zainstalujcie od&nbsp;razu przez TestFlight.
                </p>
                <div class="text-sand text-[15px] leading-relaxed space-y-3 mb-7">
                    <p><span class="text-gold font-bold">1.</span> Kliknijcie przycisk poniżej <b class="text-cream">na iPhonie</b>.</p>
                    <p><span class="text-gold font-bold">2.</span> Jeśli nie macie aplikacji <b class="text-cream">TestFlight</b>, App&nbsp;Store zaproponuje jej instalację — zainstalujcie.</p>
                    <p><span class="text-gold font-bold">3.</span> W&nbsp;TestFlight przy „Ja i Ty" kliknijcie <b class="text-cream">„Zainstaluj"</b>.</p>
                    <p><span class="text-gold font-bold">4.</span> Otwórzcie aplikację i&nbsp;załóżcie konto.</p>
                </div>
                <div class="text-center">
                    <a href="https://testflight.apple.com/join/4TBdgSgj" target="_blank" rel="noopener"
                       class="inline-block bg-gold text-ink font-bold text-lg px-10 py-4 rounded-sm tracking-wide
                          transition-all duration-300 hover:bg-goldLight hover:shadow-[0_0_30px_rgba(212,175,55,0.35)]">
                        Zainstaluj przez TestFlight
                    </a>
                </div>
                <p class="text-sand/70 text-sm text-center mt-4">
                    Coś nie działa? Napiszcie na <a href="mailto:kontakt@jaity.app" class="text-gold">kontakt@jaity.app</a>.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ===== TIMELINE ===== -->
<section class="py-12 px-6 border-t border-gold/10">
    <div class="max-w-4xl mx-auto">
        <h2 class="reveal font-headline text-gold text-3xl sm:text-[40px] text-center mb-16">Co dalej?</h2>

        <div class="grid sm:grid-cols-3 gap-10 sm:gap-6 text-center relative">
            <div class="hidden sm:block absolute top-[7px] left-[16.7%] right-[16.7%] h-px bg-gradient-to-r from-gold/40 via-gold/20 to-gold/40" aria-hidden="true"></div>

            <div class="reveal relative">
                <span class="block w-3.5 h-3.5 mx-auto rounded-full bg-gold mb-5 relative z-10"></span>
                <h3 class="font-headline text-cream text-xl mb-2">Czerwiec</h3>
                <p class="text-sand text-[17px]">Zapisy par testerów — właśnie trwają.</p>
            </div>

            <div class="reveal reveal-d1 relative">
                <span class="block w-3.5 h-3.5 mx-auto rounded-full border border-gold bg-ink mb-5 relative z-10"></span>
                <h3 class="font-headline text-cream text-xl mb-2">Sierpień</h3>
                <p class="text-sand text-[17px]">Zamknięta beta dla zapisanych par.</p>
            </div>

            <div class="reveal reveal-d2 relative">
                <span class="block w-3.5 h-3.5 mx-auto rounded-full border border-gold/50 bg-ink mb-5 relative z-10"></span>
                <h3 class="font-headline text-cream text-xl mb-2">Przełom lata i jesieni</h3>
                <p class="text-sand text-[17px]">Premiera w App Store i Google Play.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== STOPKA ===== -->
<footer class="py-7 px-6 border-t border-gold/10 bg-gradient-to-b from-ink to-wineDeep">
    <div class="max-w-4xl mx-auto flex flex-col items-center gap-6 text-center">
        <span class="font-headline text-2xl text-gold">Ja&nbsp;i&nbsp;Ty</span>

        <div class="flex items-center gap-8">
            <!-- TODO: podmień linki gdy Wiktoria założy konta SM -->
            <a href="#" class="text-sand hover:text-goldLight transition-colors text-sm tracking-wider uppercase">Instagram</a>
            <span class="w-1 h-1 rounded-full bg-wine"></span>
            <a href="#" class="text-sand hover:text-goldLight transition-colors text-sm tracking-wider uppercase">TikTok</a>
            <span class="w-1 h-1 rounded-full bg-wine"></span>
            <a href="mailto:kontakt@jaity.app" class="text-sand hover:text-goldLight transition-colors text-sm tracking-wider uppercase">kontakt@jaity.app</a>
        </div>

        <p class="text-sand/60 text-sm">Startup w budowie — tworzony przez dwoje ludzi: programistę i&nbsp;studentkę psychologii.</p>
        <p class="text-sand/40 text-xs">© <span id="year">2026</span> Ja i Ty. Wszystkie prawa zastrzeżone.</p>
    </div>
</footer>

<script>
    /* ============================================================
       KONFIGURACJA FORMULARZA — Google Forms (ukryty POST)
       ------------------------------------------------------------
       1. Google Form z 4 pytaniami: imię, e-mail, długość związku
          (krótka odpowiedź) + "Jakiego telefonu używacie?"
          (jednokrotny wybór: Android / iPhone (iOS)).
          NIE włączać "Zbieraj adresy e-mail", NIE wymagać logowania,
          NIE limitować do 1 odpowiedzi.
       2. Otwórz formularz → ⋮ → "Pobierz wstępnie wypełniony link"
          → wypełnij wszystkie pola → skopiuj link.
       3. Z linku wyciągnij: adres (zamień /viewform na /formResponse)
          oraz identyfikatory entry.XXXXXXX dla każdego pola.
       4. Wklej poniżej. Gotowe.
       ============================================================ */
    const FORM_CONFIG = {
        action: 'https://docs.google.com/forms/d/e/1FAIpQLSel79N29JlnJGgRUxKdiVWsOxyuDR0Efh_jNNfM8wA7R9-nKA/formResponse',
        fields: {
            name: 'entry.390699125',
            email: 'entry.865627962',
            duration: 'entry.615215330',
            device: 'entry.858243804'
        }
    };

    // --- Fade-in na scroll ---
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });
    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

    // --- Formularz ---
    const form = document.getElementById('tester-form');
    const successBox = document.getElementById('form-success');
    const emailInput = document.getElementById('f-email');
    const emailError = document.getElementById('email-error');
    const deviceInput = document.getElementById('f-device');
    const deviceError = document.getElementById('device-error');
    const submitBtn = document.getElementById('f-submit');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // honeypot — boty wypełniają ukryte pole
        if (document.getElementById('f-website').value !== '') return;

        const email = emailInput.value.trim();
        const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
        if (!emailOk) {
            emailError.classList.remove('hidden');
            emailInput.focus();
            return;
        }
        emailError.classList.add('hidden');

        // telefon wymagany — steruje instrukcją, którą dostaną w mailu
        if (!deviceInput.value) {
            deviceError.classList.remove('hidden');
            deviceInput.focus();
            return;
        }
        deviceError.classList.add('hidden');

        submitBtn.disabled = true;
        submitBtn.textContent = 'Zapisujemy…';

        if (FORM_CONFIG.action.includes('FORM_ID_TUTAJ')) {
            console.warn('FORM_CONFIG zawiera placeholder — zgłoszenie NIE zostało wysłane do Google Forms. Uzupełnij konfigurację przed wdrożeniem!');
        } else {
            const data = new FormData();
            data.append(FORM_CONFIG.fields.name, document.getElementById('f-name').value.trim());
            data.append(FORM_CONFIG.fields.email, email);
            data.append(FORM_CONFIG.fields.duration, document.getElementById('f-duration').value);
            data.append(FORM_CONFIG.fields.device, deviceInput.value);
            try {
                await fetch(FORM_CONFIG.action, { method: 'POST', mode: 'no-cors', body: data });
            } catch (err) {
                // no-cors i tak nie daje odczytu odpowiedzi; błędy sieci ignorujemy świadomie
                console.error('Błąd wysyłki:', err);
            }
        }

        // iPhone → pokaż od razu instalację przez TestFlight; reszta → mail w 24h
        if (deviceInput.value === 'iPhone (iOS)') {
            document.getElementById('success-default').classList.add('hidden');
            document.getElementById('success-ios').classList.remove('hidden');
        }

        form.classList.add('hidden');
        successBox.classList.remove('hidden');
        successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    document.getElementById('year').textContent = new Date().getFullYear();
</script>
</body>
</html>
