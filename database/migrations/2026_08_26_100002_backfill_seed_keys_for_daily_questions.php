<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stamp seed_key on the daily-card deck (d001-d100), matching rows by the body they carry today.
     *
     * This is the one and only moment the text is trusted as an identifier. It
     * works because production was verified against these exact strings before
     * the migration ran (100 of 100 matched, in all four pools); from here on
     * the key is what a row is known by and the text is free to change.
     *
     * Same frozen map, same reason as the session deck: identity is stamped from
     * a snapshot of the text, once, while the text still matches.
     *
     * Fails loudly rather than half-stamping: a body that no longer matches, or
     * one that matches twice, aborts the migration and the deploy with it. On a
     * fresh database it is a no-op — there are no rows yet, and the seeder writes
     * the keys itself.
     */
    private const MAP = [
        'd001' => 'Co sprawiło, że na waszych twarzach pojawił się dziś uśmiech?',
        'd002' => 'Jaka pozytywna myśl mogłaby być mottem dzisiejszego dnia?',
        'd003' => 'Czego dziś potrzebujecie najbardziej: odpoczynku, rozmowy czy przytulenia?',
        'd004' => 'Jaka rzecz związana z partnerem potrafi cię rozbawić?',
        'd005' => 'Co z tego, co zrobił dziś partner, wymagało wysiłku godnego pochwały?',
        'd006' => 'Jaka wiadomość od partnera mogłaby sprawić, że twój dzień stałby się lepszy?',
        'd007' => 'Co dziś w twoim partnerze wydaje się szczególnie atrakcyjne?',
        'd008' => 'Którą porę dnia można byłoby nazwać „waszym czasem”?',
        'd009' => 'Czy mały gest, np. komplement ze strony partnera, jest w stanie poprawić twój nastrój?',
        'd010' => 'W jakie dowolne miejsce chciałbyś/chciałabyś przenieść się z partnerem w trudnych momentach? (Wyobraźcie sobie wspólnie to miejsce).',
        'd011' => 'Jaka wspólna aktywność sprawia, że wasz dzień staje się lepszy?',
        'd012' => 'Czego dawno nie mówiłeś/mówiłaś partnerowi, a czym chciałbyś/chciałabyś podzielić się dzisiaj?',
        'd013' => 'Co dzisiaj mogłoby sprawić, że poczułbyś/poczułabyś większą bliskość?',
        'd014' => 'Jaką rzecz można uznać za wasz wspólny, mały sukces?',
        'd015' => 'Dokończcie zdanie: „Zależy mi na tobie, dlatego trudno mi, gdy…”',
        'd016' => 'Dokończcie zdanie: „Myśl o tobie sprawia, że…”',
        'd017' => 'Które wspólne wspomnienie mogłoby zostać sceną z filmu?',
        'd018' => 'Za co najbardziej cenisz dziś partnera?',
        'd019' => 'Jaka wasza wspólna przyjemność jest lub mogłaby stać się rytuałem w waszym związku?',
        'd020' => 'Jakie zdanie partnera bywa motywacją do działania?',
        'd021' => 'Do jakiego momentu z dzisiejszego dnia moglibyście wrócić?',
        'd022' => 'Jaka jest wasza „supermoc”, dzięki której możecie radzić sobie z wyzwaniami?',
        'd023' => 'Jaka mała rzecz związana z partnerem najbardziej poprawia twój poranek?',
        'd024' => 'Jaka cecha partnera byłaby tak samo atrakcyjna za 20 lat?',
        'd025' => 'Co dziś sprawiło, że jesteście jeszcze bliżej niż wczoraj?',
        'd026' => 'Jaka sytuacja z waszego związku może stanowić zabawną anegdotę?',
        'd027' => 'W jaki sposób starasz się wspierać partnera po ciężkim dniu?',
        'd028' => 'Dokończcie zdanie: „Gdy źle się czujesz, chcę…”',
        'd029' => 'Jaka niepisana zasada w waszym związku jest pomocna w codzienności?',
        'd030' => 'Jakie hasło lub zdanie wypowiedziane przez partnera pomaga ci się uspokoić i odetchnąć?',
        'd031' => 'Jakie zachowanie partnera pozytywnie cię zaskoczyło?',
        'd032' => 'Jakie zachowanie partnera sprawia, że czujesz się bezpiecznie?',
        'd033' => 'Za co możesz dziś podziękować partnerowi?',
        'd034' => 'Po czym poznałbyś/poznałabyś partnera, słysząc, a nie widząc go?',
        'd035' => 'Co jest waszym komfortowym sposobem na odpoczynek, do którego lubisz wracać?',
        'd036' => 'W jaki sposób najbardziej lubisz okazywać czułość partnerowi?',
        'd037' => 'W czym jesteście wyjątkowo zgodni?',
        'd038' => 'Jakie jedzenie lub napój może poprawić wam nastrój?',
        'd039' => 'Jaka muzyka wprawia was w pozytywne emocje?',
        'd040' => 'Co sprawia, że masz większą ochotę na czas spędzony z partnerem?',
        'd041' => 'Dokończcie zdanie: „Rzeczą, którą uwielbiałem/uwielbiałam w tobie na początku i uwielbiam dziś, jest…”',
        'd042' => 'Dokończcie zdanie: „Nikt nie może z tobą konkurować, bo…”',
        'd043' => 'Dokończcie zdanie: „Nawet gdy się z tobą nie zgadzam, doceniam, że…”',
        'd044' => 'Co dziś zrobilibyście wspólnie, gdyby jutra miało nie być?',
        'd045' => 'Jaką rolę partnera doceniasz w związku? (np. odpowiedzialność za określone obowiązki, przejmowanie inicjatywy).',
        'd046' => 'Jakie zachowanie partnera sprawia, że czujesz się kochany/kochana?',
        'd047' => 'Czego nauczył cię wasz związek?',
        'd048' => 'Co dzisiaj nie jest już przeszkodą?',
        'd049' => 'Gdybyś nie miał/miała dziś żadnych ograniczeń, jak spędziłbyś/spędziłabyś resztę dnia z partnerem?',
        'd050' => 'Po czym można poznać wspólnie spędzony dzień za udany?',
        'd051' => 'Dokończcie zdanie: „Jesteś dla mnie ważny/ważna, dlatego chcę…”',
        'd052' => 'Jakie zachowanie partnera sprawia, że trudne rzeczy wydają się łatwiejsze?',
        'd053' => 'Jak wyglądałby idealny wspólny wieczór?',
        'd054' => 'Co dzisiaj mogłoby sprawić, że poczujesz większą więź z partnerem?',
        'd055' => 'Dokończcie zdanie: „Moim wyrazem miłości do ciebie jest…”',
        'd056' => 'Dokończcie zdanie: „Nawet gdy mam dużo na głowie, jesteś dla mnie priorytetem, bo…”',
        'd057' => 'Dokończcie zdanie: „Bez ciebie świat byłby nudny, bo…”',
        'd058' => 'Co sprawia, że czujesz się atrakcyjny/atrakcyjna w oczach partnera?',
        'd059' => 'Jakie pozytywne słowa partnera najbardziej zapadły ci w pamięć?',
        'd060' => 'Bez czego pozytywnego wasz związek nie byłby taki sam?',
        'd061' => 'Dokończcie zdanie: „Nie zawsze ci to mówię, ale chcę, byś wiedział/wiedziała, że…”',
        'd062' => 'W jaki sposób moglibyście dziś wzmocnić wasz związek?',
        'd063' => 'Jakie gesty lub drobne rzeczy ze strony partnera były dziś miłym akcentem dnia?',
        'd064' => 'Co w waszej rutynie jest elementem, którego nie mogłoby zabraknąć?',
        'd065' => 'Dokończcie zdanie: „Czasami o tym zapominam, ale wiedz, że doceniam…”',
        'd066' => 'Dokończcie zdanie: „Może nie zdajesz sobie z tego sprawy, ale poczułem się/poczułam się dziś kochany/kochana, gdy…”',
        'd067' => 'Jaki mały gest był dziś dla ciebie oznaką miłości ze strony partnera?',
        'd068' => 'Jaka część ciała partnera wydaje ci się dziś jeszcze bardziej atrakcyjna?',
        'd069' => 'Jaka rzecz ze strony partnera mogłaby sprawić, że czułbyś się/czułabyś się dzisiaj wyjątkowo?',
        'd070' => 'Jaka rzecz sprawia, że czas spędzony z partnerem staje się niezwykły?',
        'd071' => 'Co sprawia, że masz ochotę podejmować więcej inicjatywy w związku?',
        'd072' => 'Jaka zaleta partnera jest dla ciebie źródłem podziwu?',
        'd073' => 'Jakie działanie partnera miało dzisiaj duże i pozytywne znaczenie?',
        'd074' => 'Czego nie potrafiłbyś/nie potrafiłabyś odmówić partnerowi, nawet mając dużo na głowie?',
        'd075' => 'Jak wyobrażasz sobie idealną randkę?',
        'd076' => 'Która piosenka przychodzi ci na myśl, gdy myślisz o waszym związku?',
        'd077' => 'Które z was jest większą duszą towarzystwa i dlaczego?',
        'd078' => 'Co znalazłoby się w waszym idealnym, stworzonym razem miejscu?',
        'd079' => 'Jaka jedna rzecz jest uniwersalnym sposobem na uszczęśliwienie twojego partnera?',
        'd080' => 'Na jaki temat moglibyście rozmawiać z zainteresowaniem godzinami?',
        'd081' => 'Czego w waszym związku mogliby pozazdrościć inni?',
        'd082' => 'Jaki sposób mógłby rozwiązać lub szybko poprawić większość napięć między wami?',
        'd083' => 'Jaka rzecz sprawia, że masz większe zaufanie do partnera?',
        'd084' => 'Dokończcie zdanie: „Jesteś dla mnie spokojem, gdy…”',
        'd085' => 'Co zaimponowało ci w twoim partnerze?',
        'd086' => 'Co bez twojego partnera byłoby trudniejsze i dlaczego?',
        'd087' => 'Jaki wspólny sygnał, żart lub zdanie rozumiecie tylko wy?',
        'd088' => 'Dokończcie zdanie: „Wydajesz mi się niesamowitą osobą, gdy…”',
        'd089' => 'Co sprawia, że masz ochotę przytulić się do partnera?',
        'd090' => 'Co najbardziej wpływa na dobrą atmosferę między wami?',
        'd091' => 'Jaka rzecz mogłaby sprawić, że z dumą powiedziałbyś/powiedziałabyś: „To mój partner!”?',
        'd092' => 'Co zrobiliście w ostatnim czasie, co mogłoby powtarzać się częściej?',
        'd093' => 'Gdybyście trafili na bezludną wyspę, jak podzielilibyście między sobą obowiązki?',
        'd094' => 'Które słowa najlepiej was opisują?
• „znajdzie rozwiązanie na wszystko”
• „chodząca intuicja”
• „mistrz planowania”
• „ma precyzję chirurga”',
        'd095' => 'Gdyby twój partner pracował w FBI, jaka byłaby jego rola?',
        'd096' => 'Co byłoby dla ciebie najtrudniejsze, gdybyś zamienił się/zamieniła się w partnera na jeden dzień?',
        'd097' => 'Jak zaplanowałbyś/zaplanowałabyś czas z partnerem, wiedząc, że nie będziecie się widzieć po tym dniu przez długi czas?',
        'd098' => 'W jakiej sytuacji partner najczęściej ma rację, nawet jeśli trudno ci się z tym zgodzić?',
        'd099' => 'Dokończcie zdanie: „Rzecz, która sprawia, że jesteś dobrym partnerem/dobrą partnerką, to…”',
        'd100' => 'Jaki wspólny cel chciałbyś/chciałabyś zrealizować w najbliższym czasie?',
    ];

    public function up(): void
    {
        $total = DB::table('questions')->where('type', 'daily')->where('locale', 'pl')->count();

        if ($total === 0) {
            // Fresh database — nothing seeded yet.
            return;
        }

        // Checked before anything is written: if a body appears twice, the text
        // cannot say which row is meant, and the unique index would stop us
        // mid-way with a message about an index rather than about the content.
        $duplicated = DB::table('questions')
            ->where('type', 'daily')->where('locale', 'pl')
            ->whereIn('body', array_values(self::MAP))
            ->select('body')
            ->groupBy('body')
            ->havingRaw('count(*) > 1')
            ->pluck('body');

        if ($duplicated->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Daily deck backfill: %d entries appear more than once, so the text cannot identify a row. '
                .'First: %s',
                $duplicated->count(),
                mb_strimwidth((string) $duplicated->first(), 0, 70, '…'),
            ));
        }

        $matched = 0;

        foreach (self::MAP as $seedKey => $body) {
            if (DB::table('questions')->where('seed_key', $seedKey)->exists()) {
                // Already stamped: a re-run, not a second identity.
                $matched++;

                continue;
            }

            $matched += DB::table('questions')
                ->where('type', 'daily')->where('locale', 'pl')
                ->where('body', $body)
                ->whereNull('seed_key')
                ->update(['seed_key' => $seedKey]);
        }

        if ($matched !== count(self::MAP)) {
            throw new RuntimeException(sprintf(
                'Daily deck backfill matched %d of %d rows by body. The database no longer holds the '
                .'text this migration was frozen against — stop and compare before going further.',
                $matched,
                count(self::MAP),
            ));
        }

        // The count above says the file was fully matched; this says the POOL was.
        // A row nobody knew about would otherwise survive without a key, invisible
        // to a seeder that only ever looks things up by one — and stay that way.
        $unkeyed = DB::table('questions')
            ->where('type', 'daily')->where('locale', 'pl')
            ->whereNull('seed_key')
            ->count();

        if ($unkeyed > 0) {
            throw new RuntimeException(sprintf(
                'Daily deck backfill left %d rows without a seed_key. They are not in the frozen map, so '
                .'something put them there outside the seed — look before going further.',
                $unkeyed,
            ));
        }
    }

    public function down(): void
    {
        DB::table('questions')->whereIn('seed_key', array_keys(self::MAP))->update(['seed_key' => null]);
    }
};
